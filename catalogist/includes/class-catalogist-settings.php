<?php
/**
 * Plugin settings: choose a WordPress page to serve as the programs landing page.
 *
 * When a Programs page is set, the built-in program archive is turned off so the
 * page can live at its own address (for example /programs/), and program URLs
 * are nested under it (/programs/computer-program-design/).
 */

defined( 'ABSPATH' ) || exit;

class Catalogist_Settings {

	const OPTION      = 'catalogist_programs_page';
	const COURSE_INFO = 'catalogist_auto_course_info';
	const COURSE_DESC = 'catalogist_auto_course_description';
	const MOVE_ACTION = 'catalogist_move_descriptions';
	const DELETE_DATA = 'catalogist_delete_data';
	const FLUSH  = 'catalogist_flush_rewrite_rules';
	const SLUG   = 'catalogist-settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'post_state' ), 10, 2 );

		// Program URLs depend on the page, so refresh permalinks whenever it changes.
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'schedule_flush' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'schedule_flush' ) );
		add_action( 'post_updated', array( __CLASS__, 'watch_page_update' ), 10, 3 );
		add_action( 'wp_trash_post', array( __CLASS__, 'watch_page' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'watch_page' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'watch_page' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
		add_action( 'admin_post_' . self::MOVE_ACTION, array( __CLASS__, 'move_descriptions' ) );
	}

	/**
	 * The Programs page ID, or 0 if none is set or the page isn't published.
	 */
	public static function programs_page_id() {
		$page_id = (int) get_option( self::OPTION, 0 );

		if ( $page_id && 'page' === get_post_type( $page_id ) && 'publish' === get_post_status( $page_id ) ) {
			return $page_id;
		}
		return 0;
	}

	// ---- Permalink upkeep -------------------------------------------------

	public static function schedule_flush() {
		update_option( self::FLUSH, 1, false );
	}

	public static function maybe_flush() {
		if ( get_option( self::FLUSH ) ) {
			delete_option( self::FLUSH );
			flush_rewrite_rules();
		}
	}

	public static function watch_page( $post_id ) {
		if ( (int) $post_id === (int) get_option( self::OPTION, 0 ) ) {
			self::schedule_flush();
		}
	}

	public static function watch_page_update( $post_id, $after, $before ) {
		if ( (int) $post_id !== (int) get_option( self::OPTION, 0 ) ) {
			return;
		}
		if ( $after->post_name !== $before->post_name
			|| $after->post_status !== $before->post_status
			|| $after->post_parent !== $before->post_parent ) {
			self::schedule_flush();
		}
	}

	// ---- Admin ------------------------------------------------------------

	public static function post_state( $states, $post ) {
		if ( $post->ID === self::programs_page_id() ) {
			$states['catalogist_programs_page'] = __( 'Programs Page', 'catalogist' );
		}
		return $states;
	}

	public static function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=' . Catalogist_Post_Types::PROGRAM,
			__( 'Catalogist Settings', 'catalogist' ),
			__( 'Settings', 'catalogist' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			self::SLUG,
			self::OPTION,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);

		add_settings_section( 'catalogist_pages', __( 'Pages', 'catalogist' ), '__return_false', self::SLUG );

		register_setting(
			self::SLUG,
			self::COURSE_INFO,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		add_settings_section( 'catalogist_courses', __( 'Course pages', 'catalogist' ), '__return_false', self::SLUG );

		register_setting(
			self::SLUG,
			self::COURSE_DESC,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		add_settings_field(
			self::COURSE_INFO,
			__( 'Course Info', 'catalogist' ),
			array( __CLASS__, 'render_course_info_field' ),
			self::SLUG,
			'catalogist_courses'
		);

		add_settings_field(
			self::COURSE_DESC,
			__( 'Course Description', 'catalogist' ),
			array( __CLASS__, 'render_course_description_field' ),
			self::SLUG,
			'catalogist_courses'
		);

		register_setting(
			self::SLUG,
			self::DELETE_DATA,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		add_settings_section( 'catalogist_uninstall', __( 'Uninstalling', 'catalogist' ), '__return_false', self::SLUG );

		add_settings_field(
			self::DELETE_DATA,
			__( 'Remove content', 'catalogist' ),
			array( __CLASS__, 'render_delete_data_field' ),
			self::SLUG,
			'catalogist_uninstall'
		);

		add_settings_field(
			self::OPTION,
			__( 'Programs page', 'catalogist' ),
			array( __CLASS__, 'render_page_field' ),
			self::SLUG,
			'catalogist_pages',
			array( 'label_for' => 'catalogist_programs_page' )
		);
	}

	public static function render_page_field() {
		wp_dropdown_pages(
			array(
				'name'              => esc_attr( self::OPTION ),
				'id'                => 'catalogist_programs_page',
				'selected'          => (int) get_option( self::OPTION, 0 ),
				'show_option_none'  => esc_html__( '— None (use the built-in program archive) —', 'catalogist' ),
				'option_none_value' => '0',
			)
		);

		echo '<p class="description">';
		esc_html_e( 'The page students land on to browse programs, usually with the Program Finder block. Program pages will be nested under it. Child pages of this page won’t be reachable, so keep other pages elsewhere.', 'catalogist' );
		echo '</p>';

		$page_id = self::programs_page_id();

		if ( ! $page_id ) {
			return;
		}

		// Example URLs, so the effect of the setting is visible.
		$base = trailingslashit( home_url( get_page_uri( $page_id ) ) );
		echo '<p>';
		printf(
			/* translators: 1: programs page URL, 2: example program URL */
			esc_html__( 'Programs page: %1$s — program pages: %2$s', 'catalogist' ),
			'<a href="' . esc_url( $base ) . '"><code>' . esc_html( $base ) . '</code></a>',
			'<code>' . esc_html( $base . 'program-name/' ) . '</code>'
		);
		echo '</p>';

		if ( ! has_block( 'catalogist/program-finder', $page_id ) ) {
			echo '<div class="notice notice-warning inline"><p>';
			printf(
				/* translators: %s: link to edit the page */
				esc_html__( 'This page doesn’t contain the Program Finder block yet. %s', 'catalogist' ),
				'<a href="' . esc_url( get_edit_post_link( $page_id ) ) . '">' . esc_html__( 'Edit the page', 'catalogist' ) . '</a>'
			);
			echo '</p></div>';
		}
	}

	public static function render_course_info_field() {
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::COURSE_INFO ); ?>" value="1" <?php checked( self::auto_course_info() ); ?>>
			<?php esc_html_e( 'Show the Course Info block on every course page', 'catalogist' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Course pages without the Course Info block (for example, courses created before it existed or copied from another site) get it automatically at the top. Pages that already include the block, anywhere, are unchanged. Turn this off if you remove the block from some courses on purpose.', 'catalogist' ); ?>
		</p>
		<?php
	}

	public static function render_course_description_field() {
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::COURSE_DESC ); ?>" value="1" <?php checked( self::auto_course_description() ); ?>>
			<?php esc_html_e( 'Show the course description on every course page', 'catalogist' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Course pages without the Course Description block get it automatically, right after Course Info. Only courses with a description in the Course Details panel are affected.', 'catalogist' ); ?>
		</p>
		<?php
	}

	public static function render_delete_data_field() {
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::DELETE_DATA ); ?>" value="1" <?php checked( (bool) get_option( self::DELETE_DATA, false ) ); ?>>
			<?php esc_html_e( 'Delete all programs, courses, map templates, credential types, departments, career areas and delivery modes when Catalogist is deleted', 'catalogist' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Off by default: deleting the plugin removes only its settings, and your content stays in the database in case you reinstall. This can’t be undone, so back up first.', 'catalogist' ); ?>
		</p>
		<?php
	}

	public static function auto_course_description() {
		return (bool) get_option( self::COURSE_DESC, true );
	}

	public static function auto_course_info() {
		return (bool) get_option( self::COURSE_INFO, true );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only result count.
		$moved = isset( $_GET['catalogist_moved'] ) ? absint( $_GET['catalogist_moved'] ) : null;
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( null !== $moved ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of courses */
								_n( 'Moved the description for %d course.', 'Moved descriptions for %d courses.', $moved, 'catalogist' ),
								$moved
							)
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::SLUG );
				do_settings_sections( self::SLUG );
				submit_button();
				?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Move course descriptions into the description field', 'catalogist' ); ?></h2>
			<p>
				<?php esc_html_e( 'For courses whose description was typed into the page content: the text of their paragraph blocks is copied into the Course Details description field, those paragraphs are removed from the page, and a Course Description block takes their place.', 'catalogist' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'Courses that already have a description in the field are skipped, and blocks other than paragraphs are left in place. Links and formatting inside the paragraphs become plain text. Each course keeps a revision, so a change can be undone from its Revisions screen.', 'catalogist' ); ?>
			</p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::MOVE_ACTION ); ?>">
				<?php wp_nonce_field( self::MOVE_ACTION ); ?>
				<?php submit_button( __( 'Move descriptions', 'catalogist' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * One-time tool: move paragraph text from course content into the description field.
	 */
	public static function move_descriptions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You don’t have permission to do this.', 'catalogist' ) );
		}
		check_admin_referer( self::MOVE_ACTION );

		$course_ids = get_posts(
			array(
				'post_type'      => Catalogist_Post_Types::COURSE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$moved = 0;

		foreach ( $course_ids as $course_id ) {
			if ( '' !== trim( (string) get_post_meta( $course_id, '_catalogist_description', true ) ) ) {
				continue;
			}

			$paragraphs = array();
			$kept       = array();

			foreach ( parse_blocks( get_post_field( 'post_content', $course_id, 'raw' ) ) as $parsed ) {
				if ( 'core/paragraph' === $parsed['blockName'] ) {
					$text = trim( html_entity_decode( wp_strip_all_tags( $parsed['innerHTML'] ), ENT_QUOTES, 'UTF-8' ) );
					if ( '' !== $text ) {
						$paragraphs[] = $text;
					}
					continue;
				}
				$kept[] = $parsed;
			}

			if ( ! $paragraphs ) {
				continue;
			}

			$description_block = array(
				'blockName'    => 'catalogist/course-description',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			);

			// Put the new block right after Course Info, or first if there isn't one.
			$position = 0;
			foreach ( $kept as $index => $parsed ) {
				if ( 'catalogist/course-info' === $parsed['blockName'] ) {
					$position = $index + 1;
					break;
				}
			}
			array_splice( $kept, $position, 0, array( $description_block ) );

			update_post_meta( $course_id, '_catalogist_description', wp_slash( implode( "\n\n", $paragraphs ) ) );
			wp_update_post(
				wp_slash(
					array(
						'ID'           => $course_id,
						'post_content' => serialize_blocks( $kept ),
					)
				)
			);
			++$moved;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => Catalogist_Post_Types::PROGRAM,
					'page'      => self::SLUG,
					'catalogist_moved' => $moved,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}
}
