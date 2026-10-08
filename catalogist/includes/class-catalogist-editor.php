<?php
/**
 * Loads the block editor panels for programs and courses.
 */

defined( 'ABSPATH' ) || exit;

class Catalogist_Editor {

	public static function init() {
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ! in_array( $screen->post_type, array( Catalogist_Post_Types::PROGRAM, Catalogist_Post_Types::COURSE ), true ) ) {
			return;
		}

		$asset_file = CATALOGIST_PLUGIN_DIR . 'build/editor.asset.php';

		// Build output is missing: run `npm run build`.
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'catalogist-editor',
			plugins_url( 'build/editor.js', CATALOGIST_PLUGIN_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'catalogist-editor', 'catalogist' );

		if ( file_exists( CATALOGIST_PLUGIN_DIR . 'build/editor.css' ) ) {
			wp_enqueue_style(
				'catalogist-editor',
				plugins_url( 'build/editor.css', CATALOGIST_PLUGIN_FILE ),
				array( 'wp-components' ),
				$asset['version']
			);
		}
	}
}
