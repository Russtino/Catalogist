<?php
/**
 * Lookups between programs and courses, shared by the admin screens and blocks.
 */

defined( 'ABSPATH' ) || exit;

class Catalogist_Catalog {

	/** @var array Cached indexes, keyed by status list. */
	private static $indexes = array();

	/**
	 * A map term's items, accepting the pre-0.17 shape ( "courses" => [ IDs ] ).
	 *
	 * @return array[] [ [ 'type' => 'course', 'id' => 21 ], [ 'type' => 'choice', ... ] ]
	 */
	public static function term_items( $term ) {
		if ( isset( $term['items'] ) && is_array( $term['items'] ) ) {
			$items = array();
			foreach ( $term['items'] as $item ) {
				if ( is_array( $item ) ) {
					$item['type'] = isset( $item['type'] ) && 'choice' === $item['type'] ? 'choice' : 'course';
					$items[]      = $item;
				}
			}
			return $items;
		}

		$items = array();
		foreach ( isset( $term['courses'] ) ? (array) $term['courses'] : array() as $id ) {
			$items[] = array(
				'type' => 'course',
				'id'   => (int) $id,
			);
		}
		return $items;
	}

	/**
	 * A choice slot's options as lists of course IDs: [ [ 31, 32 ], [ 33 ] ].
	 * Accepts the pre-0.19 shape, where "options" listed single courses.
	 */
	public static function option_sets( $item ) {
		$sets = array();

		if ( isset( $item['optionSets'] ) && is_array( $item['optionSets'] ) ) {
			foreach ( $item['optionSets'] as $set ) {
				if ( is_array( $set ) && isset( $set['courses'] ) ) {
					$sets[] = array_map( 'intval', (array) $set['courses'] );
				}
			}
			return $sets;
		}

		foreach ( isset( $item['options'] ) ? (array) $item['options'] : array() as $id ) {
			$sets[] = array( (int) $id );
		}
		return $sets;
	}

	/**
	 * Every course a map refers to: placed courses and choice-slot options.
	 */
	public static function map_course_ids( $map ) {
		$ids = array();

		foreach ( (array) $map as $term ) {
			if ( ! is_array( $term ) ) {
				continue;
			}
			foreach ( self::term_items( $term ) as $item ) {
				if ( 'choice' === $item['type'] ) {
					foreach ( self::option_sets( $item ) as $set ) {
						$ids = array_merge( $ids, $set );
					}
				} elseif ( ! empty( $item['id'] ) ) {
					$ids[] = (int) $item['id'];
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Every course ID in any of a program's credential maps, including choice options.
	 */
	public static function program_course_ids( $program_id ) {
		$ids = array();

		foreach ( (array) get_post_meta( $program_id, '_catalogist_credentials', true ) as $credential ) {
			if ( ! empty( $credential['map'] ) && is_array( $credential['map'] ) ) {
				$ids = array_merge( $ids, self::map_course_ids( $credential['map'] ) );
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Which programs include each course: [ course ID => [ program ID, ... ] ].
	 *
	 * @param string[] $statuses Program statuses to include.
	 */
	public static function course_program_index( $statuses = array( 'publish' ) ) {
		$key = implode( ',', $statuses );
		if ( isset( self::$indexes[ $key ] ) ) {
			return self::$indexes[ $key ];
		}

		$program_ids = get_posts(
			array(
				'post_type'      => Catalogist_Post_Types::PROGRAM,
				'post_status'    => $statuses,
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		update_meta_cache( 'post', $program_ids );

		$index = array();
		foreach ( $program_ids as $program_id ) {
			foreach ( self::program_course_ids( $program_id ) as $course_id ) {
				$index[ $course_id ][] = $program_id;
			}
		}

		self::$indexes[ $key ] = $index;
		return $index;
	}
}
