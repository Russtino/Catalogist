<?php
/**
 * Registers post meta for programs and courses.
 *
 * Keys start with an underscore so they stay out of the classic Custom Fields
 * panel; they're still readable and editable through the REST API and blocks.
 */

defined( 'ABSPATH' ) || exit;

class Catalogist_Meta {

	public static function register() {
		$course  = Catalogist_Post_Types::COURSE;
		$program = Catalogist_Post_Types::PROGRAM;

		// ---- Course fields ------------------------------------------------

		// e.g. "CPD 153"
		self::add_scalar( $course, '_catalogist_course_code', 'string', '', 'sanitize_text_field' );

		// Credit hours. Number, so 1.5-credit labs work.
		self::add_scalar( $course, '_catalogist_credits', 'number', 0, array( __CLASS__, 'sanitize_credits' ) );

		// Prerequisite course IDs.
		register_post_meta(
			$course,
			'_catalogist_prerequisites',
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_id_list' ),
				'auth_callback'     => array( __CLASS__, 'can_edit' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
				),
			)
		);

		// Free text for things IDs can't express, e.g. "or instructor permission".
		self::add_scalar( $course, '_catalogist_prerequisite_notes', 'string', '', 'sanitize_text_field' );

		// Plain-text description; blank lines separate paragraphs.
		self::add_scalar( $course, '_catalogist_description', 'string', '', 'sanitize_textarea_field' );

		// Optional hours, for catalogs that list more than credits.
		self::add_scalar( $course, '_catalogist_lecture_hours', 'number', 0, array( __CLASS__, 'sanitize_credits' ) );
		self::add_scalar( $course, '_catalogist_lab_hours', 'number', 0, array( __CLASS__, 'sanitize_credits' ) );
		self::add_scalar( $course, '_catalogist_contact_hours', 'number', 0, array( __CLASS__, 'sanitize_credits' ) );

		// ---- Program fields -----------------------------------------------

		// Federal CIP code; useful later for outcomes and wage data.
		self::add_scalar( $program, '_catalogist_cip_code', 'string', '', 'sanitize_text_field' );
		self::add_scalar( $program, '_catalogist_cip_title', 'string', '', 'sanitize_text_field' );

		// e.g. "August (fall semester)".
		self::add_scalar( $program, '_catalogist_start_terms', 'string', '', 'sanitize_text_field' );

		// Shown prominently when set, e.g. "Not accepting applications for Fall 2026."
		self::add_scalar( $program, '_catalogist_enrollment_notice', 'string', '', 'sanitize_textarea_field' );

		// One item per entry.
		self::add_list( $program, '_catalogist_outcomes' );
		self::add_list( $program, '_catalogist_careers' );
		self::add_list( $program, '_catalogist_certifications' );          // Earned by all graduates.
		self::add_list( $program, '_catalogist_certifications_optional' ); // Graduates can also earn.

		// Per-credential details. An AAS and a certificate in the same program
		// have their own length, credit total, and program map.
		// [
		//   {
		//     "credential": 12,               // catalogist_credential term ID
		//     "length": "4 semesters",
		//     "totalCredits": 64,
		//     "map": [ { "label": "Fall Semester 1", "items": [ ... ] }, ... ]   // see map_schema()
		//   }, ...
		// ]
		register_post_meta(
			$program,
			'_catalogist_credentials',
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_credentials' ),
				'auth_callback'     => array( __CLASS__, 'can_edit' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'properties'           => array(
								'credential'   => array( 'type' => 'integer' ),
								'length'       => array( 'type' => 'string' ),
								'totalCredits' => array( 'type' => 'number' ),
								'description'  => array( 'type' => 'string' ),
								// Optional routes through this credential, e.g. Coding and Networking.
								// Map entries name a pathway by its id; entries without one are shared.
								'pathways'     => array(
									'type'  => 'array',
									'items' => array(
										'type'                 => 'object',
										'properties'           => array(
											'id'          => array( 'type' => 'string' ),
											'name'        => array( 'type' => 'string' ),
											'description' => array( 'type' => 'string' ),
										),
										'additionalProperties' => false,
									),
								),
								'map'          => self::map_schema(),
							),
							'additionalProperties' => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Register a single-value string or number field.
	 */
	private static function add_scalar( $post_type, $key, $type, $default, $sanitize ) {
		register_post_meta(
			$post_type,
			$key,
			array(
				'type'              => $type,
				'single'            => true,
				'default'           => $default,
				'sanitize_callback' => $sanitize,
				'auth_callback'     => array( __CLASS__, 'can_edit' ),
				'show_in_rest'      => true,
			)
		);
	}

	/**
	 * Register a list of short text items.
	 */
	private static function add_list( $post_type, $key ) {
		register_post_meta(
			$post_type,
			$key,
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_list' ),
				'auth_callback'     => array( __CLASS__, 'can_edit' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
				),
			)
		);
	}

	public static function sanitize_list( $value ) {
		$items = array_map( 'sanitize_text_field', array_map( 'strval', (array) $value ) );
		return array_values( array_filter( $items, 'strlen' ) );
	}

	/**
	 * Underscore-prefixed meta is protected, so an explicit auth check is required.
	 */
	public static function can_edit( $allowed, $meta_key, $object_id ) {
		return current_user_can( 'edit_post', $object_id );
	}

	public static function sanitize_credits( $value ) {
		return max( 0, round( (float) $value, 2 ) );
	}

	public static function sanitize_id_list( $value ) {
		$ids = array_map( 'absint', (array) $value );
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * One entry per credential term; if a term appears twice, the later entry wins.
	 */
	public static function sanitize_credentials( $value ) {
		$clean = array();

		foreach ( (array) $value as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['credential'] ) ) {
				continue;
			}
			$credential = absint( $entry['credential'] );

			$clean[ $credential ] = array(
				'credential'   => $credential,
				'length'       => isset( $entry['length'] ) ? sanitize_text_field( $entry['length'] ) : '',
				'totalCredits' => isset( $entry['totalCredits'] ) ? self::sanitize_credits( $entry['totalCredits'] ) : 0,
				'description'  => isset( $entry['description'] ) ? sanitize_text_field( $entry['description'] ) : '',
				'pathways'     => isset( $entry['pathways'] ) ? self::sanitize_pathways( $entry['pathways'] ) : array(),
				'map'          => isset( $entry['map'] ) ? self::sanitize_program_map( $entry['map'] ) : array(),
			);
		}

		return array_values( $clean );
	}

	/**
	 * REST schema for a program map (also used by map templates):
	 *
	 * [
	 *   {
	 *     "label": "Fall Semester 1",
	 *     "items": [
	 *       { "type": "course", "id": 21 },
	 *       { "type": "choice", "label": "Science with lab", "credits": 4,
	 *         "optionSets": [ { "courses": [ 31, 32 ] }, { "courses": [ 33, 34 ] } ] },
	 *       { "type": "course", "id": 40, "pathway": "networking" }
	 *     ]
	 *   }, ...
	 * ]
	 *
	 * "courses" is the pre-0.17 shape (a plain list of course IDs). It's still
	 * accepted so older maps load; saving converts it to "items".
	 */
	public static function map_schema() {
		return array(
			'type'  => 'array',
			'items' => array(
				'type'                 => 'object',
				'properties'           => array(
					'label'   => array( 'type' => 'string' ),
					'items'   => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'properties'           => array(
								'type'    => array(
									'type' => 'string',
									'enum' => array( 'course', 'choice' ),
								),
								'id'      => array( 'type' => 'integer' ),
								'label'   => array( 'type' => 'string' ),
								'credits' => array( 'type' => 'number' ),
								// Each option is one course or a small group taken together (lecture + lab).
								'optionSets' => array(
									'type'  => 'array',
									'items' => array(
										'type'                 => 'object',
										'properties'           => array(
											'courses' => array(
												'type'  => 'array',
												'items' => array( 'type' => 'integer' ),
											),
											// Marks a group, so a group still being filled in isn't mistaken for a single course.
											'group'   => array( 'type' => 'boolean' ),
										),
										'additionalProperties' => false,
									),
								),
								'options' => array( // Pre-0.19: one course per option.
									'type'  => 'array',
									'items' => array( 'type' => 'integer' ),
								),
								'pathway' => array( 'type' => 'string' ), // Empty = shared by all pathways.
								'template' => array( 'type' => 'integer' ), // Map template that added this entry, if any.
							),
							'additionalProperties' => false,
						),
					),
					'courses' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
				),
				'additionalProperties' => false,
			),
		);
	}

	public static function sanitize_pathways( $value ) {
		$clean = array();

		foreach ( (array) $value as $pathway ) {
			if ( ! is_array( $pathway ) ) {
				continue;
			}
			$id   = isset( $pathway['id'] ) ? sanitize_key( $pathway['id'] ) : '';
			$name = isset( $pathway['name'] ) ? sanitize_text_field( $pathway['name'] ) : '';

			if ( '' !== $id && ! isset( $clean[ $id ] ) ) {
				$clean[ $id ] = array(
					'id'          => $id,
					'name'        => $name,
					'description' => isset( $pathway['description'] ) ? sanitize_text_field( $pathway['description'] ) : '',
				);
			}
		}

		return array_values( $clean );
	}

	public static function sanitize_program_map( $value ) {
		$clean = array();

		foreach ( (array) $value as $term ) {
			if ( ! is_array( $term ) ) {
				continue;
			}

			$items = array();
			$seen  = array();

			foreach ( Catalogist_Catalog::term_items( $term ) as $item ) {
				$pathway  = isset( $item['pathway'] ) ? sanitize_key( $item['pathway'] ) : '';
				$template = isset( $item['template'] ) ? absint( $item['template'] ) : 0;

				if ( 'choice' === $item['type'] ) {
					$sets = array();
					$raw  = isset( $item['optionSets'] ) && is_array( $item['optionSets'] ) ? array_values( $item['optionSets'] ) : array();
					foreach ( Catalogist_Catalog::option_sets( $item ) as $i => $courses ) {
						$courses = self::sanitize_id_list( $courses );
						if ( $courses ) {
							$sets[] = array(
								'courses' => $courses,
								'group'   => ! empty( $raw[ $i ]['group'] ) || count( $courses ) > 1,
							);
						}
					}

					$items[] = array(
						'type'       => 'choice',
						'label'      => isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '',
						'credits'    => isset( $item['credits'] ) ? self::sanitize_credits( $item['credits'] ) : 0,
						'optionSets' => $sets,
						'pathway'    => $pathway,
						'template'   => $template,
					);
					continue;
				}

				// The same course may sit in a term once per pathway.
				$id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
				if ( $id && ! isset( $seen[ $id . '|' . $pathway ] ) ) {
					$seen[ $id . '|' . $pathway ] = true;
					$items[]                      = array(
						'type'     => 'course',
						'id'       => $id,
						'pathway'  => $pathway,
						'template' => $template,
					);
				}
			}

			$clean[] = array(
				'label' => isset( $term['label'] ) ? sanitize_text_field( $term['label'] ) : '',
				'items' => $items,
			);
		}

		return $clean;
	}
}
