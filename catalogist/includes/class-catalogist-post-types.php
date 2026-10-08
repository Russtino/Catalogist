<?php
/**
 * Registers the Program and Course post types.
 */

defined( 'ABSPATH' ) || exit;

class Catalogist_Post_Types {

	const PROGRAM = 'catalogist_program';
	const COURSE  = 'catalogist_course';

	public static function register() {
		// With a Programs page chosen, turn off the built-in archive so the page
		// owns its address, and nest program URLs under the page's path.
		$programs_page = Catalogist_Settings::programs_page_id();
		$program_slug  = $programs_page ? get_page_uri( $programs_page ) : 'programs';

		register_post_type(
			self::PROGRAM,
			array(
				'labels'        => array(
					'name'               => __( 'Programs', 'catalogist' ),
					'singular_name'      => __( 'Program', 'catalogist' ),
					'menu_name'          => __( 'Catalogist', 'catalogist' ),
					'all_items'          => __( 'All Programs', 'catalogist' ),
					'add_new_item'       => __( 'Add New Program', 'catalogist' ),
					'edit_item'          => __( 'Edit Program', 'catalogist' ),
					'new_item'           => __( 'New Program', 'catalogist' ),
					'view_item'          => __( 'View Program', 'catalogist' ),
					'search_items'       => __( 'Search Programs', 'catalogist' ),
					'not_found'          => __( 'No programs found.', 'catalogist' ),
					'not_found_in_trash' => __( 'No programs found in Trash.', 'catalogist' ),
				),
				'public'        => true,
				'has_archive'   => ! $programs_page,
				'show_in_rest'  => true,
				'menu_position' => 20,
				'menu_icon'     => 'dashicons-welcome-learn-more',
				'rewrite'       => array(
					'slug'       => apply_filters( 'catalogist_program_slug', $program_slug ),
					'with_front' => false,
				),
				// 'custom-fields' is required for registered meta to appear in the REST API.
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
				// Starting layout for new programs.
				'template'      => array(
					array( 'core/paragraph', array( 'placeholder' => __( 'Program overview…', 'catalogist' ) ) ),
					array( 'catalogist/program-details' ),
					array( 'core/heading', array( 'content' => __( 'Program Map', 'catalogist' ) ) ),
					array( 'catalogist/program-map' ),
					// Each list hides itself, heading included, until it has items.
					array( 'catalogist/program-list', array( 'list' => 'outcomes' ) ),
					array( 'catalogist/program-list', array( 'list' => 'careers' ) ),
					array( 'catalogist/program-list', array( 'list' => 'certifications' ) ),
				),
			)
		);

		register_post_type(
			self::COURSE,
			array(
				'labels'       => array(
					'name'               => __( 'Courses', 'catalogist' ),
					'singular_name'      => __( 'Course', 'catalogist' ),
					'all_items'          => __( 'Courses', 'catalogist' ),
					'add_new_item'       => __( 'Add New Course', 'catalogist' ),
					'edit_item'          => __( 'Edit Course', 'catalogist' ),
					'new_item'           => __( 'New Course', 'catalogist' ),
					'view_item'          => __( 'View Course', 'catalogist' ),
					'search_items'       => __( 'Search Courses', 'catalogist' ),
					'not_found'          => __( 'No courses found.', 'catalogist' ),
					'not_found_in_trash' => __( 'No courses found in Trash.', 'catalogist' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'show_in_rest' => true,
				// Nest Courses under the Catalogist menu.
				'show_in_menu' => 'edit.php?post_type=' . self::PROGRAM,
				'rewrite'      => array(
					'slug'       => apply_filters( 'catalogist_course_slug', 'courses' ),
					'with_front' => false,
				),
				'supports'     => array( 'title', 'editor', 'excerpt', 'revisions', 'custom-fields' ),
				// Starting layout for new courses.
				'template'     => array(
					array( 'catalogist/course-info' ),
					array( 'catalogist/course-description' ),
				),
			)
		);
	}
}
