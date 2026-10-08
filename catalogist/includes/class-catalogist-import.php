<?php
/**
 * Course import: the admin screen and the REST endpoints it calls.
 *
 * The spreadsheet is parsed in the browser. These endpoints receive clean rows
 * in small batches, so large catalogs don't hit PHP time limits.
 */

defined( 'ABSPATH' ) || exit;

class Catalogist_Import {

	const PAGE = 'catalogist-import';

	private static $hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * A capability name for the Course post type, e.g. 'edit_posts'.
	 */
	private static function cap( $name ) {
		$type = get_post_type_object( Catalogist_Post_Types::COURSE );
		return $type ? $type->cap->$name : 'edit_posts';
	}

	/**
	 * Importing can create and update any course, whoever wrote it, so it's
	 * limited to users who can edit other people's courses (Editors and
	 * Administrators by default).
	 */
	public static function can_import() {
		return current_user_can( self::cap( 'create_posts' ) ) && current_user_can( self::cap( 'edit_others_posts' ) );
	}

	// ---- Admin screen -----------------------------------------------------

	public static function add_menu_page() {
		self::$hook = add_submenu_page(
			'edit.php?post_type=' . Catalogist_Post_Types::PROGRAM,
			__( 'Import Courses', 'catalogist' ),
			__( 'Import Courses', 'catalogist' ),
			self::cap( 'edit_others_posts' ),
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function enqueue( $hook ) {
		if ( $hook !== self::$hook ) {
			return;
		}

		$asset_file = CATALOGIST_PLUGIN_DIR . 'build/import.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			'catalogist-import',
			plugins_url( 'build/import.js', CATALOGIST_PLUGIN_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( 'catalogist-import', 'catalogist' );

		wp_enqueue_style(
			'catalogist-import',
			plugins_url( 'build/import.css', CATALOGIST_PLUGIN_FILE ),
			array( 'wp-components' ),
			$asset['version']
		);
	}

	public static function render_page() {
		if ( ! self::can_import() ) {
			wp_die( esc_html__( 'You don’t have permission to import courses.', 'catalogist' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Courses', 'catalogist' ); ?></h1>
			<div
				id="catalogist-import-root"
				data-courses-url="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Catalogist_Post_Types::COURSE ) ); ?>"
				data-can-publish="<?php echo current_user_can( self::cap( 'publish_posts' ) ) ? '1' : '0'; ?>"
			>
				<noscript><?php esc_html_e( 'The course import requires JavaScript.', 'catalogist' ); ?></noscript>
			</div>
		</div>
		<?php
	}

	// ---- REST API ---------------------------------------------------------

	public static function register_routes() {

		register_rest_route(
			'catalogist/v1',
			'/course-codes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_course_codes' ),
				'permission_callback' => array( __CLASS__, 'can_import' ),
			)
		);

		register_rest_route(
			'catalogist/v1',
			'/import/courses',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'import_courses' ),
				'permission_callback' => array( __CLASS__, 'can_import' ),
				'args'                => array(
					'rows'     => array(
						'type'     => 'array',
						'required' => true,
						'maxItems' => 100,
					),
					'status'   => array(
						'type'    => 'string',
						'enum'    => array( 'draft', 'publish' ),
						'default' => 'draft',
					),
					'existing' => array(
						'type'    => 'string',
						'enum'    => array( 'update', 'skip' ),
						'default' => 'update',
					),
				),
			)
		);

		register_rest_route(
			'catalogist/v1',
			'/import/prerequisites',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'import_prerequisites' ),
				'permission_callback' => array( __CLASS__, 'can_import' ),
				'args'                => array(
					'rows' => array(
						'type'     => 'array',
						'required' => true,
						'maxItems' => 100,
					),
				),
			)
		);
	}

	/**
	 * "CPD 153", "CPD-153" and "cpd153" all become "CPD153". Matches codeKey() in JS.
	 */
	public static function code_key( $code ) {
		return strtoupper( preg_replace( '/[\s\-_.]+/', '', (string) $code ) );
	}

	/**
	 * Every non-trashed course by normalized code: [ key => [ id, code, title ] ].
	 */
	private static function code_index() {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one query instead of loading every course.
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, m.meta_value
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				WHERE p.post_type = %s AND p.post_status NOT IN ( 'trash', 'auto-draft' )
				ORDER BY p.ID ASC",
				'_catalogist_course_code',
				Catalogist_Post_Types::COURSE
			)
		);

		$index = array();
		foreach ( $rows as $row ) {
			$key = self::code_key( $row->meta_value );
			if ( '' !== $key ) {
				$index[ $key ] = array(
					'id'    => (int) $row->ID,
					'code'  => $row->meta_value,
					'title' => $row->post_title,
				);
			}
		}
		return $index;
	}

	/**
	 * Codes and titles of existing courses, for the import preview. Only
	 * courses the current user can edit are included.
	 */
	public static function get_course_codes() {
		$visible = array();
		foreach ( self::code_index() as $key => $course ) {
			if ( current_user_can( 'edit_post', $course['id'] ) ) {
				$visible[ $key ] = $course;
			}
		}
		return rest_ensure_response( (object) $visible );
	}

	public static function import_courses( WP_REST_Request $request ) {
		$status = 'publish' === $request['status'] && current_user_can( self::cap( 'publish_posts' ) ) ? 'publish' : 'draft';
		$update = 'skip' !== $request['existing'];
		$index  = self::code_index();

		$results = array();

		foreach ( (array) $request['rows'] as $row ) {
			$row   = is_array( $row ) ? $row : array();
			$line  = isset( $row['row'] ) ? (int) $row['row'] : 0;
			$code  = isset( $row['code'] ) ? sanitize_text_field( $row['code'] ) : '';
			$title = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
			$key   = self::code_key( $code );

			if ( '' === $key || '' === $title ) {
				$results[] = self::result( $line, 'error', __( 'Missing course code or title.', 'catalogist' ) );
				continue;
			}

			$existing = isset( $index[ $key ] ) ? $index[ $key ]['id'] : 0;

			if ( $existing && ! $update ) {
				$results[] = self::result( $line, 'skipped' );
				continue;
			}
			if ( $existing && ! current_user_can( 'edit_post', $existing ) ) {
				$results[] = self::result( $line, 'error', __( 'You don’t have permission to edit this course.', 'catalogist' ) );
				continue;
			}

			$post = array(
				'post_type'  => Catalogist_Post_Types::COURSE,
				'post_title' => $title,
			);

			// Page content is only written for new courses; updates change fields only.
			if ( $existing ) {
				$post['ID'] = $existing;
				$post_id    = wp_update_post( wp_slash( $post ), true );
				$state      = 'updated';
			} else {
				$post['post_status']  = $status;
				$post['post_content'] = "<!-- wp:catalogist/course-info /-->\n\n<!-- wp:catalogist/course-description /-->";
				$post_id              = wp_insert_post( wp_slash( $post ), true );
				$state                = 'created';
			}

			if ( is_wp_error( $post_id ) ) {
				$results[] = self::result( $line, 'error', $post_id->get_error_message() );
				continue;
			}

			// Later rows in this batch should match this course.
			$index[ $key ] = array(
				'id'    => $post_id,
				'code'  => $code,
				'title' => $title,
			);

			update_post_meta( $post_id, '_catalogist_course_code', wp_slash( $code ) );

			$hour_fields = array(
				'lectureHours' => '_catalogist_lecture_hours',
				'labHours'     => '_catalogist_lab_hours',
				'contactHours' => '_catalogist_contact_hours',
			);
			foreach ( $hour_fields as $field => $meta_key ) {
				if ( isset( $row[ $field ] ) && is_numeric( $row[ $field ] ) ) {
					update_post_meta( $post_id, $meta_key, (float) $row[ $field ] );
				}
			}

			if ( isset( $row['description'] ) && '' !== trim( (string) $row['description'] ) ) {
				update_post_meta( $post_id, '_catalogist_description', wp_slash( (string) $row['description'] ) );
			}

			if ( isset( $row['credits'] ) && is_numeric( $row['credits'] ) ) {
				update_post_meta( $post_id, '_catalogist_credits', (float) $row['credits'] );
			}

			if ( ! empty( $row['department'] ) ) {
				// A department named in the spreadsheet always applies.
				$term_id = self::department_term( sanitize_text_field( $row['department'] ) );
				if ( $term_id ) {
					wp_set_object_terms( $post_id, array( $term_id ), Catalogist_Taxonomies::DEPARTMENT );
				}
			} elseif ( ! empty( $row['departmentId'] ) ) {
				// A department from the course prefix fills in new courses and
				// existing ones without a department; it never replaces one.
				$term = get_term( (int) $row['departmentId'], Catalogist_Taxonomies::DEPARTMENT );
				$has  = 'updated' === $state ? wp_get_object_terms( $post_id, Catalogist_Taxonomies::DEPARTMENT, array( 'fields' => 'ids' ) ) : array();

				if ( $term && ! is_wp_error( $term ) && ! is_wp_error( $has ) && ! $has ) {
					wp_set_object_terms( $post_id, array( $term->term_id ), Catalogist_Taxonomies::DEPARTMENT );
				}
			}

			$results[] = self::result( $line, $state );
		}

		return rest_ensure_response( array( 'results' => $results ) );
	}

	/**
	 * Second pass: link prerequisite codes to course IDs. Codes that don't
	 * match a course are added to the prerequisite notes so nothing is lost.
	 */
	public static function import_prerequisites( WP_REST_Request $request ) {
		$index      = self::code_index();
		$unresolved = array();

		foreach ( (array) $request['rows'] as $row ) {
			$row  = is_array( $row ) ? $row : array();
			$code = isset( $row['code'] ) ? sanitize_text_field( $row['code'] ) : '';
			$key  = self::code_key( $code );
			$id   = isset( $index[ $key ] ) ? $index[ $key ]['id'] : 0;

			if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}

			$missing = array();

			if ( array_key_exists( 'codes', $row ) ) {
				$ids = array();

				foreach ( (array) $row['codes'] as $prereq_code ) {
					$prereq_code = sanitize_text_field( $prereq_code );
					$prereq_key  = self::code_key( $prereq_code );

					if ( '' === $prereq_key || $prereq_key === $key ) {
						continue; // A course can't be its own prerequisite.
					}
					if ( isset( $index[ $prereq_key ] ) ) {
						$ids[] = $index[ $prereq_key ]['id'];
					} else {
						$missing[] = $prereq_code;
					}
				}

				update_post_meta( $id, '_catalogist_prerequisites', $ids );
			}

			if ( array_key_exists( 'notes', $row ) ) {
				$notes = sanitize_text_field( (string) $row['notes'] );

				if ( $missing ) {
					$notes = trim(
						$notes . ' ' . sprintf(
							/* translators: %s: comma-separated course codes */
							__( 'Also requires: %s.', 'catalogist' ),
							implode( ', ', $missing )
						)
					);
				}
				update_post_meta( $id, '_catalogist_prerequisite_notes', wp_slash( $notes ) );
			}

			if ( $missing ) {
				$unresolved[] = array(
					'row'   => isset( $row['row'] ) ? (int) $row['row'] : 0,
					'code'  => $code,
					'codes' => $missing,
				);
			}
		}

		return rest_ensure_response( array( 'unresolved' => $unresolved ) );
	}

	private static function result( $row, $status, $message = '' ) {
		return array(
			'row'     => $row,
			'status'  => $status,
			'message' => $message,
		);
	}

	/**
	 * Find a department by name, creating it if the user is allowed to.
	 */
	private static function department_term( $name ) {
		if ( '' === $name ) {
			return 0;
		}

		$term = term_exists( $name, Catalogist_Taxonomies::DEPARTMENT );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$taxonomy = get_taxonomy( Catalogist_Taxonomies::DEPARTMENT );
		if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->manage_terms ) ) {
			return 0;
		}

		$term = wp_insert_term( $name, Catalogist_Taxonomies::DEPARTMENT );
		return is_wp_error( $term ) ? 0 : (int) $term['term_id'];
	}
}
