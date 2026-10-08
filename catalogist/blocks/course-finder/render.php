<?php
/**
 * Front-end output for the Course Finder block.
 *
 * Every published course is rendered as a table row, so the full list works
 * without JavaScript. The shared finder script (the same one the Program
 * Finder uses) reveals the form and filters the rows in the browser.
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$catalogist_courses = get_posts(
	array(
		'post_type'      => Catalogist_Post_Types::COURSE,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
	)
);

if ( ! $catalogist_courses ) {
	return;
}

$catalogist_program_index = Catalogist_Catalog::course_program_index();
$catalogist_department_options = array(); // slug => name
$catalogist_program_options    = array(); // slug => title
$catalogist_rows               = array();

foreach ( $catalogist_courses as $catalogist_course ) {
	$catalogist_code   = Catalogist_Blocks::course_code( $catalogist_course->ID );
	$catalogist_title  = get_the_title( $catalogist_course );
	$catalogist_search = array( $catalogist_code, $catalogist_title );

	$catalogist_dept_names = array();
	$catalogist_dept_slugs = array();
	$catalogist_terms      = get_the_terms( $catalogist_course, Catalogist_Taxonomies::DEPARTMENT );
	if ( $catalogist_terms && ! is_wp_error( $catalogist_terms ) ) {
		foreach ( $catalogist_terms as $catalogist_term ) {
			$catalogist_dept_names[]                            = $catalogist_term->name;
			$catalogist_dept_slugs[]                            = $catalogist_term->slug;
			$catalogist_department_options[ $catalogist_term->slug ] = $catalogist_term->name;
		}
	}

	$catalogist_program_slugs = array();
	foreach ( isset( $catalogist_program_index[ $catalogist_course->ID ] ) ? $catalogist_program_index[ $catalogist_course->ID ] : array() as $catalogist_program_id ) {
		$catalogist_slug                          = get_post_field( 'post_name', $catalogist_program_id );
		$catalogist_program_slugs[]               = $catalogist_slug;
		$catalogist_program_options[ $catalogist_slug ] = get_the_title( $catalogist_program_id );
	}

	$catalogist_rows[] = array(
		'post'        => $catalogist_course,
		'code'        => $catalogist_code,
		'title'       => $catalogist_title,
		'credits'     => Catalogist_Blocks::course_credits( $catalogist_course->ID ),
		'departments' => implode( ', ', $catalogist_dept_names ),
		'data'        => array(
			'department' => implode( ' ', $catalogist_dept_slugs ),
			'program'    => implode( ' ', $catalogist_program_slugs ),
		),
		// Codes are also searchable without the space: "cpd153".
		'search'      => mb_strtolower(
			remove_accents( implode( ' ', array_merge( $catalogist_search, $catalogist_dept_names, array( str_replace( ' ', '', $catalogist_code ) ) ) ) )
		),
	);
}

// Order by course code (natural: CPD 90 before CPD 153), then title.
usort(
	$catalogist_rows,
	function ( $a, $b ) {
		return strnatcasecmp( $a['code'], $b['code'] ) ?: strnatcasecmp( $a['title'], $b['title'] );
	}
);

$catalogist_uid     = wp_unique_id( 'catalogist-course-finder-' );
$catalogist_count   = count( $catalogist_rows );
$catalogist_filters = array();

if ( ! empty( $attributes['showDepartmentFilter'] ) && count( $catalogist_department_options ) > 1 ) {
	asort( $catalogist_department_options, SORT_NATURAL | SORT_FLAG_CASE );
	$catalogist_filters['department'] = array( __( 'Department', 'catalogist' ), $catalogist_department_options );
}
if ( ! empty( $attributes['showProgramFilter'] ) && count( $catalogist_program_options ) > 0 ) {
	asort( $catalogist_program_options, SORT_NATURAL | SORT_FLAG_CASE );
	$catalogist_filters['program'] = array( __( 'Program', 'catalogist' ), $catalogist_program_options );
}

$catalogist_has_form  = ! empty( $attributes['showSearch'] ) || $catalogist_filters;
$catalogist_show_dept = ! empty( $attributes['showDepartmentColumn'] );
$catalogist_link      = ! empty( $attributes['linkCourses'] );
?>
<div
	<?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>
	data-count-one="<?php /* translators: %d: number of results (one) */ echo esc_attr__( '%d course found', 'catalogist' ); ?>"
	data-count-other="<?php /* translators: %d: number of results */ echo esc_attr__( '%d courses found', 'catalogist' ); ?>"
>
	<?php if ( $catalogist_has_form ) : ?>
		<form class="catalogist-finder__form" role="search" aria-label="<?php esc_attr_e( 'Find a course', 'catalogist' ); ?>" hidden>
			<?php if ( ! empty( $attributes['showSearch'] ) ) : ?>
				<div class="catalogist-finder__field catalogist-finder__field--search">
					<label for="<?php echo esc_attr( $catalogist_uid . '-q' ); ?>"><?php esc_html_e( 'Search by course code or title', 'catalogist' ); ?></label>
					<input type="search" id="<?php echo esc_attr( $catalogist_uid . '-q' ); ?>" name="course" autocomplete="off">
				</div>
			<?php endif; ?>

			<?php foreach ( $catalogist_filters as $catalogist_param => $catalogist_filter ) : ?>
				<div class="catalogist-finder__field">
					<label for="<?php echo esc_attr( $catalogist_uid . '-' . $catalogist_param ); ?>"><?php echo esc_html( $catalogist_filter[0] ); ?></label>
					<select id="<?php echo esc_attr( $catalogist_uid . '-' . $catalogist_param ); ?>" name="<?php echo esc_attr( $catalogist_param ); ?>">
						<option value=""><?php esc_html_e( 'All', 'catalogist' ); ?></option>
						<?php foreach ( $catalogist_filter[1] as $catalogist_slug => $catalogist_name ) : ?>
							<option value="<?php echo esc_attr( $catalogist_slug ); ?>"><?php echo esc_html( $catalogist_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endforeach; ?>

			<div class="catalogist-finder__field catalogist-finder__field--reset">
				<button type="reset" class="catalogist-finder__reset"><?php esc_html_e( 'Clear filters', 'catalogist' ); ?></button>
			</div>
		</form>
	<?php endif; ?>

	<p class="catalogist-finder__status" role="status">
		<?php
		/* translators: %d: number of courses */
		echo esc_html( sprintf( _n( '%d course', '%d courses', $catalogist_count, 'catalogist' ), $catalogist_count ) );
		?>
	</p>

	<div class="catalogist-finder__results catalogist-course-finder__scroll">
		<table class="catalogist-course-finder__table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Code', 'catalogist' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Title', 'catalogist' ); ?></th>
					<th scope="col" class="catalogist-num"><?php esc_html_e( 'Credits', 'catalogist' ); ?></th>
					<?php if ( $catalogist_show_dept ) : ?>
						<th scope="col"><?php esc_html_e( 'Department', 'catalogist' ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $catalogist_rows as $catalogist_row ) : ?>
					<tr
						class="catalogist-finder__item"
						data-search="<?php echo esc_attr( $catalogist_row['search'] ); ?>"
						data-department="<?php echo esc_attr( $catalogist_row['data']['department'] ); ?>"
						data-program="<?php echo esc_attr( $catalogist_row['data']['program'] ); ?>"
					>
						<td class="catalogist-course-finder__code"><?php echo esc_html( $catalogist_row['code'] ); ?></td>
						<td>
							<?php if ( $catalogist_link ) : ?>
								<a href="<?php echo esc_url( get_permalink( $catalogist_row['post'] ) ); ?>"><?php echo esc_html( $catalogist_row['title'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $catalogist_row['title'] ); ?>
							<?php endif; ?>
						</td>
						<td class="catalogist-num"><?php echo $catalogist_row['credits'] > 0 ? esc_html( Catalogist_Blocks::format_credits( $catalogist_row['credits'] ) ) : ''; ?></td>
						<?php if ( $catalogist_show_dept ) : ?>
							<td><?php echo esc_html( $catalogist_row['departments'] ); ?></td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="catalogist-finder__empty" hidden>
		<p><?php esc_html_e( 'No courses match your search.', 'catalogist' ); ?></p>
		<button type="button" class="catalogist-finder__reset"><?php esc_html_e( 'Clear filters', 'catalogist' ); ?></button>
	</div>
</div>
