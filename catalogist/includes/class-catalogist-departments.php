<?php
/**
 * Course prefixes for departments: "CPD" and "CIS" courses belong to the
 * Computer Program Design department, and so on.
 *
 * Prefixes are stored on each Department term. They're used by the course
 * import and to file new courses automatically when no department is chosen.
 * Each prefix belongs to at most one department.
 */

defined( 'ABSPATH' ) || exit;

class Catalogist_Departments {

	const META  = 'catalogist_course_prefixes';
	const NONCE = 'catalogist_department_prefixes';

	/** @var array|null prefix => term ID, cached per request. */
	private static $index = null;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );

		$taxonomy = Catalogist_Taxonomies::DEPARTMENT;
		add_action( "{$taxonomy}_add_form_fields", array( __CLASS__, 'add_form_field' ) );
		add_action( "{$taxonomy}_edit_form_fields", array( __CLASS__, 'edit_form_field' ) );
		add_action( "created_{$taxonomy}", array( __CLASS__, 'save_form_field' ) );
		add_action( "edited_{$taxonomy}", array( __CLASS__, 'save_form_field' ) );
		add_filter( "manage_edit-{$taxonomy}_columns", array( __CLASS__, 'add_column' ) );
		add_filter( "manage_{$taxonomy}_custom_column", array( __CLASS__, 'render_column' ), 10, 3 );

		// File courses saved without a department under their prefix's department.
		add_action( 'rest_after_insert_' . Catalogist_Post_Types::COURSE, array( __CLASS__, 'after_rest_save' ) );
		add_action( 'save_post_' . Catalogist_Post_Types::COURSE, array( __CLASS__, 'after_save' ), 20 );

		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_meta() {
		register_term_meta(
			Catalogist_Taxonomies::DEPARTMENT,
			self::META,
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_prefixes' ),
				'auth_callback'     => array( __CLASS__, 'can_manage' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
				),
			)
		);
	}

	public static function can_manage() {
		$taxonomy = get_taxonomy( Catalogist_Taxonomies::DEPARTMENT );
		return $taxonomy && current_user_can( $taxonomy->cap->manage_terms );
	}

	// ---- Prefix helpers ---------------------------------------------------

	/**
	 * Accepts "cpd, CIS" or [ 'cpd', 'CIS' ] and returns [ 'CIS', 'CPD' ]: letters only, uppercase, unique.
	 */
	public static function sanitize_prefixes( $value ) {
		$items    = is_array( $value ) ? $value : explode( ',', (string) $value );
		$prefixes = array();

		foreach ( $items as $item ) {
			$prefix = self::clean_prefix( $item );
			if ( '' !== $prefix ) {
				$prefixes[] = $prefix;
			}
		}

		$prefixes = array_values( array_unique( $prefixes ) );
		sort( $prefixes );
		return $prefixes;
	}

	public static function clean_prefix( $prefix ) {
		return strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $prefix ) );
	}

	/**
	 * The letters a course code starts with: "CPD 153" => "CPD".
	 */
	public static function prefix_of( $code ) {
		return preg_match( '/^\s*([A-Za-z]+)/', (string) $code, $match ) ? strtoupper( $match[1] ) : '';
	}

	public static function get_prefixes( $term_id ) {
		$prefixes = get_term_meta( $term_id, self::META, true );
		return is_array( $prefixes ) ? $prefixes : array();
	}

	/**
	 * Every assigned prefix: [ 'CPD' => 12, 'CIS' => 12, 'WELD' => 15 ].
	 */
	public static function prefix_index() {
		if ( null !== self::$index ) {
			return self::$index;
		}

		self::$index = array();
		$terms       = get_terms(
			array(
				'taxonomy'   => Catalogist_Taxonomies::DEPARTMENT,
				'hide_empty' => false,
			)
		);

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				foreach ( self::get_prefixes( $term->term_id ) as $prefix ) {
					self::$index[ $prefix ] = $term->term_id;
				}
			}
		}
		return self::$index;
	}

	/**
	 * The department term ID for a course code, or 0.
	 */
	public static function department_for_code( $code ) {
		$index  = self::prefix_index();
		$prefix = self::prefix_of( $code );
		return isset( $index[ $prefix ] ) ? (int) $index[ $prefix ] : 0;
	}

	/**
	 * Give a prefix to a department (or to none, with 0), taking it away from
	 * whichever department had it before.
	 */
	public static function move_prefix( $prefix, $term_id ) {
		$prefix  = self::clean_prefix( $prefix );
		$term_id = (int) $term_id;

		if ( '' === $prefix ) {
			return;
		}

		$index = self::prefix_index();
		$owner = isset( $index[ $prefix ] ) ? (int) $index[ $prefix ] : 0;

		if ( $owner === $term_id ) {
			return;
		}
		if ( $owner ) {
			update_term_meta( $owner, self::META, array_values( array_diff( self::get_prefixes( $owner ), array( $prefix ) ) ) );
		}
		if ( $term_id ) {
			update_term_meta( $term_id, self::META, self::sanitize_prefixes( array_merge( self::get_prefixes( $term_id ), array( $prefix ) ) ) );
		}
		self::$index = null;
	}

	// ---- Auto-filing new courses -----------------------------------------

	public static function after_rest_save( $post ) {
		self::maybe_assign( $post->ID );
	}

	public static function after_save( $post_id ) {
		// REST saves are handled after their meta is written; see after_rest_save().
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		self::maybe_assign( $post_id );
	}

	private static function maybe_assign( $post_id ) {
		$current = wp_get_object_terms( $post_id, Catalogist_Taxonomies::DEPARTMENT, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $current ) || $current ) {
			return; // A department was chosen by hand; leave it alone.
		}

		$term_id = self::department_for_code( get_post_meta( $post_id, '_catalogist_course_code', true ) );
		if ( $term_id ) {
			wp_set_object_terms( $post_id, array( $term_id ), Catalogist_Taxonomies::DEPARTMENT );
		}
	}

	// ---- Department screens -----------------------------------------------

	private static function field_help() {
		return __( 'Comma-separated, for example: CPD, CIS. Courses whose code starts with one of these are placed in this department when no department is chosen, including during a course import. Each prefix belongs to one department.', 'catalogist' );
	}

	public static function add_form_field() {
		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<div class="form-field">
			<label for="catalogist-course-prefixes"><?php esc_html_e( 'Course prefixes', 'catalogist' ); ?></label>
			<input type="text" name="catalogist_course_prefixes" id="catalogist-course-prefixes" value="">
			<p><?php echo esc_html( self::field_help() ); ?></p>
		</div>
		<?php
	}

	public static function edit_form_field( $term ) {
		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<tr class="form-field">
			<th scope="row"><label for="catalogist-course-prefixes"><?php esc_html_e( 'Course prefixes', 'catalogist' ); ?></label></th>
			<td>
				<input type="text" name="catalogist_course_prefixes" id="catalogist-course-prefixes" value="<?php echo esc_attr( implode( ', ', self::get_prefixes( $term->term_id ) ) ); ?>">
				<p class="description"><?php echo esc_html( self::field_help() ); ?></p>
			</td>
		</tr>
		<?php
	}

	public static function save_form_field( $term_id ) {
		if ( ! isset( $_POST['catalogist_course_prefixes'], $_POST[ self::NONCE ] )
			|| ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE ] ), self::NONCE )
			|| ! self::can_manage() ) {
			return;
		}

		$wanted  = self::sanitize_prefixes( sanitize_text_field( wp_unslash( $_POST['catalogist_course_prefixes'] ) ) );
		$current = self::get_prefixes( $term_id );

		foreach ( array_diff( $current, $wanted ) as $prefix ) {
			self::move_prefix( $prefix, 0 );
		}
		foreach ( $wanted as $prefix ) {
			self::move_prefix( $prefix, $term_id );
		}
	}

	public static function add_column( $columns ) {
		$columns['catalogist_prefixes'] = __( 'Course prefixes', 'catalogist' );
		return $columns;
	}

	public static function render_column( $output, $column, $term_id ) {
		if ( 'catalogist_prefixes' === $column ) {
			return esc_html( implode( ', ', self::get_prefixes( $term_id ) ) );
		}
		return $output;
	}

	// ---- REST (used by the import screen) --------------------------------

	public static function register_routes() {
		register_rest_route(
			'catalogist/v1',
			'/departments',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_list' ),
				// Department names and prefixes are only needed by the course import.
				'permission_callback' => array( 'Catalogist_Import', 'can_import' ),
			)
		);

		register_rest_route(
			'catalogist/v1',
			'/department-prefixes',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_save_prefixes' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'assignments' => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			)
		);
	}

	public static function rest_list() {
		$terms = get_terms(
			array(
				'taxonomy'   => Catalogist_Taxonomies::DEPARTMENT,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		$departments = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$departments[] = array(
					'id'       => $term->term_id,
					'name'     => $term->name,
					'prefixes' => self::get_prefixes( $term->term_id ),
				);
			}
		}
		return rest_ensure_response( $departments );
	}

	/**
	 * { "CPD": 12, "WELD": 0 } — each prefix moves to that department (0 = none).
	 */
	public static function rest_save_prefixes( WP_REST_Request $request ) {
		foreach ( (array) $request['assignments'] as $prefix => $term_id ) {
			$term_id = (int) $term_id;
			if ( $term_id && ! get_term( $term_id, Catalogist_Taxonomies::DEPARTMENT ) ) {
				continue;
			}
			self::move_prefix( $prefix, $term_id );
		}
		return rest_ensure_response( array( 'saved' => true ) );
	}
}
