<?php
/**
 * Runs when Catalogist is deleted from the Plugins screen.
 *
 * Settings are always removed. Programs, courses, map templates and their
 * terms are removed only if "Remove content" was turned on in Catalogist →
 * Settings; otherwise they stay in the database in case the plugin returns.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Clean up one site.
 */
function catalogist_uninstall_site() {
	if ( get_option( 'catalogist_delete_data' ) ) {
		$post_types = array( 'catalogist_program', 'catalogist_course', 'catalogist_template' );
		$taxonomies = array( 'catalogist_credential', 'catalogist_department', 'catalogist_career_area', 'catalogist_delivery' );

		// Delete in batches, including drafts, trash and revisions (removed with their posts).
		do {
			$ids = get_posts(
				array(
					'post_type'      => $post_types,
					'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private', 'trash', 'auto-draft', 'inherit' ),
					'posts_per_page' => 200,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			foreach ( $ids as $id ) {
				wp_delete_post( $id, true );
			}
		} while ( $ids );

		// The plugin isn't loaded during uninstall, so its taxonomies must be
		// registered briefly for WordPress to delete their terms.
		foreach ( $taxonomies as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy( $taxonomy, array() );
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			);
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term_id ) {
					wp_delete_term( $term_id, $taxonomy );
				}
			}
		}
	}

	foreach ( array(
		'catalogist_programs_page',
		'catalogist_auto_course_info',
		'catalogist_auto_course_description',
		'catalogist_flush_rewrite_rules',
		'catalogist_delete_data',
	) as $option ) {
		delete_option( $option );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $catalogist_site_id ) {
		switch_to_blog( $catalogist_site_id );
		catalogist_uninstall_site();
		restore_current_blog();
	}
} else {
	catalogist_uninstall_site();
}

// Program URLs disappear with the post types.
flush_rewrite_rules();
