<?php
/**
 * Plugin Name:       Catalogist – Program & Course Catalog for Colleges
 * Description:       Program and course catalog for colleges: credentials, semester-by-semester program maps, course pages, and searchable finders.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Russell
 * Plugin URI:        https://wordpress.org/plugins/catalogist/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       catalogist
 */

defined( 'ABSPATH' ) || exit;

define( 'CATALOGIST_VERSION', '1.0.0' );
define( 'CATALOGIST_PLUGIN_FILE', __FILE__ );
define( 'CATALOGIST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once CATALOGIST_PLUGIN_DIR . 'includes/class-catalogist-post-types.php';
require_once CATALOGIST_PLUGIN_DIR . 'includes/class-catalogist-taxonomies.php';
require_once CATALOGIST_PLUGIN_DIR . 'includes/class-catalogist-meta.php';
require_once CATALOGIST_PLUGIN_DIR . 'includes/class-catalogist-editor.php';
require_once CATALOGIST_PLUGIN_DIR . 'includes/class-catalogist-blocks.php';
require_once CATALOGIST_PLUGIN_DIR . 'includes/class-catalogist-settings.php';
require_once CATALOGIST_PLUGIN_DIR . 'includes/class-catalogist-import.php';
require_once CATALOGIST_PLUGIN_DIR . 'includes/class-catalogist-departments.php';
require_once CATALOGIST_PLUGIN_DIR . 'includes/class-catalogist-catalog.php';
require_once CATALOGIST_PLUGIN_DIR . 'includes/class-catalogist-admin-columns.php';
require_once CATALOGIST_PLUGIN_DIR . 'includes/class-catalogist-templates.php';

add_action( 'init', array( 'Catalogist_Post_Types', 'register' ) );
add_action( 'init', array( 'Catalogist_Taxonomies', 'register' ) );
add_action( 'init', array( 'Catalogist_Meta', 'register' ) );

Catalogist_Editor::init();
Catalogist_Blocks::init();
Catalogist_Settings::init();
Catalogist_Import::init();
Catalogist_Departments::init();
Catalogist_Admin_Columns::init();
Catalogist_Templates::init();

register_activation_hook( __FILE__, 'catalogist_activate' );
register_deactivation_hook( __FILE__, 'catalogist_deactivate' );

/**
 * Register content types, seed default terms, and refresh permalinks.
 */
function catalogist_activate() {
	Catalogist_Post_Types::register();
	Catalogist_Taxonomies::register();
	Catalogist_Taxonomies::insert_default_terms();
	flush_rewrite_rules();
}

/**
 * Remove our rewrite rules. Content is left untouched.
 */
function catalogist_deactivate() {
	unregister_post_type( Catalogist_Post_Types::PROGRAM );
	unregister_post_type( Catalogist_Post_Types::COURSE );
	flush_rewrite_rules();
}
