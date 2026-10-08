<?php
/**
 * Registers taxonomies used to classify programs and courses.
 */

defined( 'ABSPATH' ) || exit;

class Catalogist_Taxonomies {

	const CREDENTIAL = 'catalogist_credential';
	const DEPARTMENT = 'catalogist_department';
	const CAREER     = 'catalogist_career_area';
	const DELIVERY   = 'catalogist_delivery';

	public static function register() {
		$program = Catalogist_Post_Types::PROGRAM;
		$course  = Catalogist_Post_Types::COURSE;

		// Credential type: AAS, Technical Certificate, etc.
		self::add(
			self::CREDENTIAL,
			array( $program ),
			'credentials',
			array(
				'name'                     => __( 'Credential Types', 'catalogist' ),
				'singular_name'            => __( 'Credential Type', 'catalogist' ),
				'all_items'                => __( 'All Credential Types', 'catalogist' ),
				'edit_item'                => __( 'Edit Credential Type', 'catalogist' ),
				'add_new_item'             => __( 'Add New Credential Type', 'catalogist' ),
				// The rest replace WordPress's generic "Category" wording on the term screens.
				'view_item'                => __( 'View Credential Type', 'catalogist' ),
				'update_item'              => __( 'Update Credential Type', 'catalogist' ),
				'new_item_name'            => __( 'New Credential Type Name', 'catalogist' ),
				'search_items'             => __( 'Search Credential Types', 'catalogist' ),
				'parent_item'              => __( 'Parent Credential Type', 'catalogist' ),
				'parent_item_colon'        => __( 'Parent Credential Type:', 'catalogist' ),
				'parent_field_description' => __( 'Optional. Nest a credential type under another to group them.', 'catalogist' ),
				'not_found'                => __( 'No credential types found.', 'catalogist' ),
				'no_terms'                 => __( 'No credential types', 'catalogist' ),
				'back_to_items'            => __( '&larr; Go to Credential Types', 'catalogist' ),
				'items_list'               => __( 'Credential Types list', 'catalogist' ),
				'items_list_navigation'    => __( 'Credential Types list navigation', 'catalogist' ),
				'filter_by_item'           => __( 'Filter by credential type', 'catalogist' ),
				'item_link'                => __( 'Credential Type Link', 'catalogist' ),
				'item_link_description'    => __( 'A link to a credential type.', 'catalogist' ),
			)
		);

		// Department or division. Shared by programs and courses.
		self::add(
			self::DEPARTMENT,
			array( $program, $course ),
			'departments',
			array(
				'name'                     => __( 'Departments', 'catalogist' ),
				'singular_name'            => __( 'Department', 'catalogist' ),
				'all_items'                => __( 'All Departments', 'catalogist' ),
				'edit_item'                => __( 'Edit Department', 'catalogist' ),
				'add_new_item'             => __( 'Add New Department', 'catalogist' ),
				// The rest replace WordPress's generic "Category" wording on the term screens.
				'view_item'                => __( 'View Department', 'catalogist' ),
				'update_item'              => __( 'Update Department', 'catalogist' ),
				'new_item_name'            => __( 'New Department Name', 'catalogist' ),
				'search_items'             => __( 'Search Departments', 'catalogist' ),
				'parent_item'              => __( 'Parent Department', 'catalogist' ),
				'parent_item_colon'        => __( 'Parent Department:', 'catalogist' ),
				'parent_field_description' => __( 'Optional. Nest a department under another, such as a division.', 'catalogist' ),
				'not_found'                => __( 'No departments found.', 'catalogist' ),
				'no_terms'                 => __( 'No departments', 'catalogist' ),
				'back_to_items'            => __( '&larr; Go to Departments', 'catalogist' ),
				'items_list'               => __( 'Departments list', 'catalogist' ),
				'items_list_navigation'    => __( 'Departments list navigation', 'catalogist' ),
				'filter_by_item'           => __( 'Filter by department', 'catalogist' ),
				'item_link'                => __( 'Department Link', 'catalogist' ),
				'item_link_description'    => __( 'A link to a department.', 'catalogist' ),
			)
		);

		// Career area / career cluster, used by the program finder.
		self::add(
			self::CAREER,
			array( $program ),
			'career-areas',
			array(
				'name'                     => __( 'Career Areas', 'catalogist' ),
				'singular_name'            => __( 'Career Area', 'catalogist' ),
				'all_items'                => __( 'All Career Areas', 'catalogist' ),
				'edit_item'                => __( 'Edit Career Area', 'catalogist' ),
				'add_new_item'             => __( 'Add New Career Area', 'catalogist' ),
				// The rest replace WordPress's generic "Category" wording on the term screens.
				'view_item'                => __( 'View Career Area', 'catalogist' ),
				'update_item'              => __( 'Update Career Area', 'catalogist' ),
				'new_item_name'            => __( 'New Career Area Name', 'catalogist' ),
				'search_items'             => __( 'Search Career Areas', 'catalogist' ),
				'parent_item'              => __( 'Parent Career Area', 'catalogist' ),
				'parent_item_colon'        => __( 'Parent Career Area:', 'catalogist' ),
				'parent_field_description' => __( 'Optional. Nest a career area under a broader one, such as a career cluster.', 'catalogist' ),
				'not_found'                => __( 'No career areas found.', 'catalogist' ),
				'no_terms'                 => __( 'No career areas', 'catalogist' ),
				'back_to_items'            => __( '&larr; Go to Career Areas', 'catalogist' ),
				'items_list'               => __( 'Career Areas list', 'catalogist' ),
				'items_list_navigation'    => __( 'Career Areas list navigation', 'catalogist' ),
				'filter_by_item'           => __( 'Filter by career area', 'catalogist' ),
				'item_link'                => __( 'Career Area Link', 'catalogist' ),
				'item_link_description'    => __( 'A link to a career area.', 'catalogist' ),
			)
		);

		// Delivery mode: On Campus, Online, Hybrid.
		self::add(
			self::DELIVERY,
			array( $program ),
			'delivery',
			array(
				'name'                     => __( 'Delivery Modes', 'catalogist' ),
				'singular_name'            => __( 'Delivery Mode', 'catalogist' ),
				'all_items'                => __( 'All Delivery Modes', 'catalogist' ),
				'edit_item'                => __( 'Edit Delivery Mode', 'catalogist' ),
				'add_new_item'             => __( 'Add New Delivery Mode', 'catalogist' ),
				// The rest replace WordPress's generic "Category" wording on the term screens.
				'view_item'                => __( 'View Delivery Mode', 'catalogist' ),
				'update_item'              => __( 'Update Delivery Mode', 'catalogist' ),
				'new_item_name'            => __( 'New Delivery Mode Name', 'catalogist' ),
				'search_items'             => __( 'Search Delivery Modes', 'catalogist' ),
				'parent_item'              => __( 'Parent Delivery Mode', 'catalogist' ),
				'parent_item_colon'        => __( 'Parent Delivery Mode:', 'catalogist' ),
				'parent_field_description' => __( 'Optional. Nest a delivery mode under another to group them.', 'catalogist' ),
				'not_found'                => __( 'No delivery modes found.', 'catalogist' ),
				'no_terms'                 => __( 'No delivery modes', 'catalogist' ),
				'back_to_items'            => __( '&larr; Go to Delivery Modes', 'catalogist' ),
				'items_list'               => __( 'Delivery Modes list', 'catalogist' ),
				'items_list_navigation'    => __( 'Delivery Modes list navigation', 'catalogist' ),
				'filter_by_item'           => __( 'Filter by delivery mode', 'catalogist' ),
				'item_link'                => __( 'Delivery Mode Link', 'catalogist' ),
				'item_link_description'    => __( 'A link to a delivery mode.', 'catalogist' ),
			)
		);
	}

	/**
	 * Shared registration settings. Hierarchical gives editors a checkbox list
	 * instead of free-form tags, which keeps terms consistent.
	 */
	private static function add( $taxonomy, $post_types, $slug, $labels ) {
		register_taxonomy(
			$taxonomy,
			$post_types,
			array(
				'labels'            => $labels,
				'hierarchical'      => true,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'       => $slug,
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Seed common terms on activation. Existing terms are never overwritten.
	 */
	public static function insert_default_terms() {
		$defaults = array(
			self::CREDENTIAL => apply_filters(
				'catalogist_default_credential_terms',
				array( 'Associate of Applied Science', 'Associate of Science', 'Technical Certificate', 'Certificate' )
			),
			self::DELIVERY   => apply_filters(
				'catalogist_default_delivery_terms',
				array( 'On Campus', 'Online', 'Hybrid' )
			),
		);

		foreach ( $defaults as $taxonomy => $terms ) {
			foreach ( $terms as $term ) {
				if ( ! term_exists( $term, $taxonomy ) ) {
					wp_insert_term( $term, $taxonomy );
				}
			}
		}
	}
}
