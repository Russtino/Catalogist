<?php
/**
 * Registers the front-end blocks and provides helpers for their render files.
 */

defined( 'ABSPATH' ) || exit;

class Catalogist_Blocks {

	const BLOCKS = array( 'program-details', 'program-map', 'program-list', 'course-info', 'course-description', 'program-finder', 'course-finder' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'block_categories_all', array( __CLASS__, 'add_category' ) );

		// Before do_blocks() (priority 9), so the added block is rendered normally.
		add_filter( 'the_content', array( __CLASS__, 'add_missing_course_info' ), 8 );
	}

	/**
	 * On a single course page, add the Course Info and Course Description
	 * blocks if the content doesn't already include them (each has a setting).
	 * The saved content isn't changed.
	 */
	public static function add_missing_course_info( $content ) {
		if ( ! is_singular( Catalogist_Post_Types::COURSE ) || get_the_ID() !== get_queried_object_id() ) {
			return $content;
		}

		$post     = get_post();
		$has_info = has_block( 'catalogist/course-info', $post );
		$add_info = Catalogist_Settings::auto_course_info() && ! $has_info;
		$add_desc = Catalogist_Settings::auto_course_description()
			&& ! has_block( 'catalogist/course-description', $post )
			&& '' !== trim( (string) get_post_meta( $post->ID, '_catalogist_description', true ) );

		$description = "<!-- wp:catalogist/course-description /-->\n\n";

		// Description goes right after an existing Course Info block, if there is one.
		if ( $add_desc && $has_info ) {
			$placed = preg_replace( '#<!--\s+wp:catalogist/course-info\b[^>]*?/-->#', '$0' . "\n\n" . $description, $content, 1, $count );
			if ( $count ) {
				$content  = $placed;
				$add_desc = false;
			}
		}

		return ( $add_info ? "<!-- wp:catalogist/course-info /-->\n\n" : '' ) . ( $add_desc ? $description : '' ) . $content;
	}

	public static function register() {
		$asset_file = CATALOGIST_PLUGIN_DIR . 'build/blocks.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array(),
				'version'      => CATALOGIST_VERSION,
			);

		// One editor script and one stylesheet are shared by all blocks.
		// Each block.json refers to these handles, so they must be registered first.
		wp_register_script(
			'catalogist-blocks',
			plugins_url( 'build/blocks.js', CATALOGIST_PLUGIN_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( 'catalogist-blocks', 'catalogist' );

		wp_register_style(
			'catalogist-blocks',
			plugins_url( 'assets/blocks.css', CATALOGIST_PLUGIN_FILE ),
			array(),
			CATALOGIST_VERSION
		);

		// Front-end tabs for the Program Map block. Loaded only on pages that use it.
		wp_register_script(
			'catalogist-program-map-view',
			plugins_url( 'assets/program-map-view.js', CATALOGIST_PLUGIN_FILE ),
			array(),
			CATALOGIST_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_register_script(
			'catalogist-program-finder-view',
			plugins_url( 'assets/program-finder-view.js', CATALOGIST_PLUGIN_FILE ),
			array(),
			CATALOGIST_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		foreach ( self::BLOCKS as $block ) {
			register_block_type( CATALOGIST_PLUGIN_DIR . 'blocks/' . $block );
		}
	}

	public static function add_category( $categories ) {
		$categories[] = array(
			'slug'  => 'catalogist',
			'title' => __( 'Catalogist', 'catalogist' ),
			'icon'  => null,
		);
		return $categories;
	}

	/**
	 * The post a block should display: the block's context (templates, Query
	 * Loops) or the current post. Returns 0 if it isn't the expected type.
	 */
	public static function context_post_id( $block, $post_type ) {
		$post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();

		return ( $post_id && get_post_type( $post_id ) === $post_type ) ? $post_id : 0;
	}

	/**
	 * The credentials assigned to a program, in term order, each with its
	 * length, credit total, and map:
	 * [ [ 'term' => WP_Term, 'length' => '', 'credits' => 0.0, 'description' => '', 'map' => [] ], ... ]
	 *
	 * Saved entries for credentials no longer assigned are ignored.
	 */
	public static function program_credentials( $post_id ) {
		$terms = get_the_terms( $post_id, Catalogist_Taxonomies::CREDENTIAL );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		$saved = array();
		foreach ( (array) get_post_meta( $post_id, '_catalogist_credentials', true ) as $entry ) {
			if ( is_array( $entry ) && ! empty( $entry['credential'] ) ) {
				$saved[ (int) $entry['credential'] ] = $entry;
			}
		}

		$credentials = array();
		foreach ( $terms as $term ) {
			$entry         = isset( $saved[ $term->term_id ] ) ? $saved[ $term->term_id ] : array();
			$credentials[] = array(
				'term'    => $term,
				'length'  => isset( $entry['length'] ) ? (string) $entry['length'] : '',
				'credits' => isset( $entry['totalCredits'] ) ? (float) $entry['totalCredits'] : 0.0,
				'description' => isset( $entry['description'] ) ? (string) $entry['description'] : '',
				'pathways'    => isset( $entry['pathways'] ) ? array_values(
					array_filter(
						(array) $entry['pathways'],
						function ( $pathway ) {
							return is_array( $pathway ) && ! empty( $pathway['id'] ) && '' !== trim( (string) $pathway['name'] );
						}
					)
				) : array(),
				'map'     => isset( $entry['map'] ) && is_array( $entry['map'] ) ? $entry['map'] : array(),
			);
		}
		return $credentials;
	}

	/**
	 * Published courses for the given IDs, keyed by ID. Drafts are never shown publicly.
	 */
	public static function get_courses( $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );

		if ( ! $ids ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => Catalogist_Post_Types::COURSE,
				'post__in'       => $ids,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'post__in',
				'no_found_rows'  => true,
			)
		);

		$courses = array();
		foreach ( $posts as $post ) {
			$courses[ $post->ID ] = $post;
		}
		return $courses;
	}

	/**
	 * A list meta field as an array of strings.
	 */
	public static function get_list( $post_id, $key ) {
		$items = get_post_meta( $post_id, $key, true );
		return is_array( $items ) ? array_values( array_filter( array_map( 'strval', $items ), 'strlen' ) ) : array();
	}

	/**
	 * A short summary of a program for the Program Finder: its excerpt if one
	 * was written, otherwise its first paragraph. WordPress's automatic excerpt
	 * would also pick up headings such as "Program Map".
	 */
	public static function program_summary( $post, $words = 30 ) {
		if ( has_excerpt( $post ) ) {
			return wp_trim_words( get_the_excerpt( $post ), $words );
		}

		foreach ( parse_blocks( $post->post_content ) as $parsed ) {
			if ( 'core/paragraph' === $parsed['blockName'] ) {
				$text = trim( wp_strip_all_tags( $parsed['innerHTML'] ) );
				if ( '' !== $text ) {
					return wp_trim_words( $text, $words );
				}
			}
		}

		// Classic (non-block) content.
		return wp_trim_words( get_the_excerpt( $post ), $words );
	}

	public static function course_code( $course_id ) {
		return (string) get_post_meta( $course_id, '_catalogist_course_code', true );
	}

	public static function course_credits( $course_id ) {
		return (float) get_post_meta( $course_id, '_catalogist_credits', true );
	}

	/**
	 * "CPD 153 — Software Development", or just the title if there's no code.
	 */
	public static function course_label( $course ) {
		$code  = self::course_code( $course->ID );
		$title = get_the_title( $course );
		return $code ? $code . ' — ' . $title : $title;
	}

	public static function format_credits( $credits ) {
		$credits = (float) $credits;
		return floor( $credits ) === $credits ? number_format_i18n( $credits ) : number_format_i18n( $credits, 1 );
	}

	/**
	 * Comma-separated term names for a post, or an empty string.
	 */
	public static function term_list( $post_id, $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}
		return implode( ', ', wp_list_pluck( $terms, 'name' ) );
	}
}
