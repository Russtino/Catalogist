<?php
/**
 * Courses list screen: code, credits and program columns, sorting by code,
 * and filters for department and program.
 */

defined( 'ABSPATH' ) || exit;

class Catalogist_Admin_Columns {

	const PROGRAM_FILTER = 'catalogist_program_filter';

	public static function init() {
		$course = Catalogist_Post_Types::COURSE;

		add_filter( "manage_{$course}_posts_columns", array( __CLASS__, 'columns' ) );
		add_action( "manage_{$course}_posts_custom_column", array( __CLASS__, 'render_column' ), 10, 2 );
		add_filter( "manage_edit-{$course}_sortable_columns", array( __CLASS__, 'sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filters' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_query' ) );
	}

	/**
	 * The program filter's value: a program ID, 'none', or 0 for all programs.
	 */
	private static function selected_program() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$value = isset( $_GET[ self::PROGRAM_FILTER ] ) ? sanitize_key( wp_unslash( $_GET[ self::PROGRAM_FILTER ] ) ) : '';
		return 'none' === $value ? 'none' : absint( $value );
	}

	private static function program_statuses() {
		return array( 'publish', 'draft', 'pending', 'future', 'private' );
	}

	private static function is_course_list( $query = null ) {
		global $typenow;

		if ( ! is_admin() || Catalogist_Post_Types::COURSE !== $typenow ) {
			return false;
		}
		return ! $query || $query->is_main_query();
	}

	// ---- Columns ------------------------------------------------------------

	public static function columns( $columns ) {
		$ordered = array();

		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$ordered['catalogist_code'] = __( 'Code', 'catalogist' );
			}
			$ordered[ $key ] = $label;
			if ( 'title' === $key ) {
				$ordered['catalogist_credits']  = __( 'Credits', 'catalogist' );
				$ordered['catalogist_programs'] = __( 'Programs', 'catalogist' );
			}
		}
		return $ordered;
	}

	public static function render_column( $column, $post_id ) {
		switch ( $column ) {
			case 'catalogist_code':
				echo esc_html( Catalogist_Blocks::course_code( $post_id ) );
				break;

			case 'catalogist_credits':
				$credits = Catalogist_Blocks::course_credits( $post_id );
				echo $credits > 0 ? esc_html( Catalogist_Blocks::format_credits( $credits ) ) : '—';
				break;

			case 'catalogist_programs':
				$index = Catalogist_Catalog::course_program_index( self::program_statuses() );
				$links = array();

				foreach ( isset( $index[ $post_id ] ) ? $index[ $post_id ] : array() as $program_id ) {
					$url     = add_query_arg(
						array(
							'post_type'          => Catalogist_Post_Types::COURSE,
							self::PROGRAM_FILTER => $program_id,
						),
						admin_url( 'edit.php' )
					);
					$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $program_id ) ) . '</a>';
				}

				echo $links ? implode( ', ', $links ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				break;
		}
	}

	public static function sortable_columns( $columns ) {
		$columns['catalogist_code'] = 'catalogist_code';
		return $columns;
	}

	// ---- Filters ------------------------------------------------------------

	public static function filters( $post_type ) {
		if ( Catalogist_Post_Types::COURSE !== $post_type ) {
			return;
		}

		$department = get_taxonomy( Catalogist_Taxonomies::DEPARTMENT );

		// The taxonomy's query var filters the list with no extra code.
		echo '<label class="screen-reader-text" for="catalogist-filter-department">' . esc_html__( 'Filter by department', 'catalogist' ) . '</label>';
		wp_dropdown_categories(
			array(
				'taxonomy'        => Catalogist_Taxonomies::DEPARTMENT,
				'name'            => $department->query_var,
				'id'              => 'catalogist-filter-department',
				'value_field'     => 'slug',
				'selected'        => get_query_var( $department->query_var ),
				'show_option_all' => __( 'All departments', 'catalogist' ),
				'hierarchical'    => true,
				'hide_empty'      => false,
				'orderby'         => 'name',
			)
		);

		$programs = get_posts(
			array(
				'post_type'      => Catalogist_Post_Types::PROGRAM,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$selected = self::selected_program();
		?>
		<label class="screen-reader-text" for="catalogist-filter-program"><?php esc_html_e( 'Filter by program', 'catalogist' ); ?></label>
		<select name="<?php echo esc_attr( self::PROGRAM_FILTER ); ?>" id="catalogist-filter-program">
			<option value="0"><?php esc_html_e( 'All programs', 'catalogist' ); ?></option>
			<option value="none" <?php selected( $selected, 'none' ); ?>><?php esc_html_e( 'Not in any program', 'catalogist' ); ?></option>
			<?php foreach ( $programs as $program ) : ?>
				<option value="<?php echo esc_attr( $program->ID ); ?>" <?php selected( $selected, $program->ID ); ?>>
					<?php echo esc_html( get_the_title( $program ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public static function apply_query( $query ) {
		if ( ! self::is_course_list( $query ) ) {
			return;
		}

		$selected = self::selected_program();

		if ( 'none' === $selected ) {
			// Courses that appear in no program map, draft programs included.
			$used = array_keys( Catalogist_Catalog::course_program_index( self::program_statuses() ) );
			if ( $used ) {
				$query->set( 'post__not_in', $used );
			}
		} elseif ( $selected ) {
			$ids = Catalogist_Catalog::program_course_ids( $selected );
			$query->set( 'post__in', $ids ? $ids : array( 0 ) ); // array( 0 ) = no results.
		}

		if ( 'catalogist_code' === $query->get( 'orderby' ) ) {
			// Named clauses keep courses without a code in the list.
			$query->set(
				'meta_query',
				array(
					'relation'  => 'OR',
					'catalogist_code'  => array(
						'key'     => '_catalogist_course_code',
						'compare' => 'EXISTS',
					),
					'catalogist_nocode' => array(
						'key'     => '_catalogist_course_code',
						'compare' => 'NOT EXISTS',
					),
				)
			);
			$query->set( 'orderby', 'catalogist_code' );
		}
	}
}
