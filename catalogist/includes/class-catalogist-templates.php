<?php
/**
 * Map templates: reusable sets of terms, courses and choice slots (for example,
 * an associate degree's general education requirements). Applying a template
 * copies it into a credential's map, where it can then be changed freely.
 */

defined( 'ABSPATH' ) || exit;

class Catalogist_Templates {

	const POST_TYPE = 'catalogist_template';
	const META      = '_catalogist_template_map';
	const NONCE     = 'catalogist_template_map_nonce';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'               => __( 'Map Templates', 'catalogist' ),
					'singular_name'      => __( 'Map Template', 'catalogist' ),
					'all_items'          => __( 'Map Templates', 'catalogist' ),
					'add_new_item'       => __( 'Add New Map Template', 'catalogist' ),
					'edit_item'          => __( 'Edit Map Template', 'catalogist' ),
					'new_item'           => __( 'New Map Template', 'catalogist' ),
					'search_items'       => __( 'Search Map Templates', 'catalogist' ),
					'not_found'          => __( 'No map templates found.', 'catalogist' ),
					'not_found_in_trash' => __( 'No map templates found in Trash.', 'catalogist' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=' . Catalogist_Post_Types::PROGRAM,
				// REST lets the program editor list templates; without 'editor'
				// support the template screen itself uses the simple classic form.
				'show_in_rest' => true,
				'supports'     => array( 'title', 'custom-fields' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META,
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( 'Catalogist_Meta', 'sanitize_program_map' ),
				'auth_callback'     => array( 'Catalogist_Meta', 'can_edit' ),
				'show_in_rest'      => array( 'schema' => Catalogist_Meta::map_schema() ),
			)
		);
	}

	public static function add_meta_box() {
		add_meta_box(
			'catalogist-template-map',
			__( 'Template map', 'catalogist' ),
			array( __CLASS__, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	public static function render_meta_box( $post ) {
		$map = get_post_meta( $post->ID, self::META, true );
		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<p>
			<?php esc_html_e( 'Build the terms, courses and choice slots this template adds, such as an associate degree’s general education requirements. In a program’s map editor, “Apply a template” copies these into the matching terms: the template’s first term goes into the map’s first term, and so on. After that, each program’s copy can be changed on its own; changing the template later doesn’t affect maps it was already applied to.', 'catalogist' ); ?>
		</p>
		<input type="hidden" name="catalogist_template_map" id="catalogist-template-map-input" value="<?php echo esc_attr( wp_json_encode( is_array( $map ) ? $map : array() ) ); ?>">
		<div id="catalogist-template-root"><noscript><?php esc_html_e( 'Editing a map template requires JavaScript.', 'catalogist' ); ?></noscript></div>
		<?php
	}

	public static function save( $post_id ) {
		if ( ! isset( $_POST['catalogist_template_map'], $_POST[ self::NONCE ] )
			|| ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE ] ), self::NONCE )
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Sanitized field by field by the map sanitizer.
		$map = json_decode( wp_unslash( $_POST['catalogist_template_map'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		update_post_meta( $post_id, self::META, wp_slash( Catalogist_Meta::sanitize_program_map( is_array( $map ) ? $map : array() ) ) );
	}

	public static function enqueue( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$asset_file = CATALOGIST_PLUGIN_DIR . 'build/template.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script( 'catalogist-template', plugins_url( 'build/template.js', CATALOGIST_PLUGIN_FILE ), $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'catalogist-template', 'catalogist' );
		wp_enqueue_style( 'catalogist-template', plugins_url( 'build/template.css', CATALOGIST_PLUGIN_FILE ), array( 'wp-components' ), $asset['version'] );
	}
}
