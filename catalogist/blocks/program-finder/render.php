<?php
/**
 * Front-end output for the Program Finder block.
 *
 * Every published program is rendered as a card, so the full list works
 * without JavaScript. The view script reveals the search form and filters
 * the cards in the browser, keeping the current filters in the URL.
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$catalogist_programs = get_posts(
	array(
		'post_type'      => Catalogist_Post_Types::PROGRAM,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	)
);

if ( ! $catalogist_programs ) {
	return;
}

// URL parameter => taxonomy, label, and the attribute that switches the filter on.
$catalogist_filters = array(
	'credential' => array( Catalogist_Taxonomies::CREDENTIAL, __( 'Credential', 'catalogist' ), 'showCredentialFilter' ),
	'career'     => array( Catalogist_Taxonomies::CAREER, __( 'Career area', 'catalogist' ), 'showCareerFilter' ),
	'delivery'   => array( Catalogist_Taxonomies::DELIVERY, __( 'Delivery', 'catalogist' ), 'showDeliveryFilter' ),
	'department' => array( Catalogist_Taxonomies::DEPARTMENT, __( 'Department', 'catalogist' ), 'showDepartmentFilter' ),
);

$catalogist_options = array_fill_keys( array_keys( $catalogist_filters ), array() ); // param => [ slug => name ]
$catalogist_cards   = array();

foreach ( $catalogist_programs as $catalogist_program ) {
	$catalogist_data   = array();
	$catalogist_search = array( get_the_title( $catalogist_program ) );

	foreach ( $catalogist_filters as $catalogist_param => $catalogist_filter ) {
		$catalogist_terms = get_the_terms( $catalogist_program, $catalogist_filter[0] );
		$catalogist_slugs = array();

		if ( $catalogist_terms && ! is_wp_error( $catalogist_terms ) ) {
			foreach ( $catalogist_terms as $catalogist_term ) {
				$catalogist_slugs[]                                 = $catalogist_term->slug;
				$catalogist_options[ $catalogist_param ][ $catalogist_term->slug ] = $catalogist_term->name;
				$catalogist_search[]                                = $catalogist_term->name;
			}
		}
		$catalogist_data[ $catalogist_param ] = implode( ' ', $catalogist_slugs );
	}

	$catalogist_excerpt  = Catalogist_Blocks::program_summary( $catalogist_program );
	$catalogist_search[] = $catalogist_excerpt;

	$catalogist_cards[] = array(
		'post'        => $catalogist_program,
		'excerpt'     => $catalogist_excerpt,
		'credentials' => Catalogist_Blocks::program_credentials( $catalogist_program->ID ),
		'notice'      => trim( (string) get_post_meta( $catalogist_program->ID, '_catalogist_enrollment_notice', true ) ),
		'data'        => $catalogist_data,
		// Lowercase, accent-free text the script matches search words against.
		'search'      => mb_strtolower( remove_accents( wp_strip_all_tags( implode( ' ', $catalogist_search ) ) ) ),
	);
}

$catalogist_uid         = wp_unique_id( 'catalogist-finder-' );
$catalogist_heading_tag = 'h' . min( 6, max( 2, (int) $attributes['headingLevel'] ) );
$catalogist_count       = count( $catalogist_cards );

// A filter is only useful with at least two choices.
$catalogist_active_filters = array();
foreach ( $catalogist_filters as $catalogist_param => $catalogist_filter ) {
	if ( ! empty( $attributes[ $catalogist_filter[2] ] ) && count( $catalogist_options[ $catalogist_param ] ) > 1 ) {
		asort( $catalogist_options[ $catalogist_param ], SORT_NATURAL | SORT_FLAG_CASE );
		$catalogist_active_filters[ $catalogist_param ] = $catalogist_filter[1];
	}
}
$catalogist_has_form = ! empty( $attributes['showSearch'] ) || $catalogist_active_filters;
?>
<div
	<?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>
	data-count-one="<?php /* translators: %d: number of results (one) */ echo esc_attr__( '%d program found', 'catalogist' ); ?>"
	data-count-other="<?php /* translators: %d: number of results */ echo esc_attr__( '%d programs found', 'catalogist' ); ?>"
>
	<?php if ( $catalogist_has_form ) : ?>
		<form class="catalogist-finder__form" role="search" aria-label="<?php esc_attr_e( 'Find a program', 'catalogist' ); ?>" hidden>
			<?php if ( ! empty( $attributes['showSearch'] ) ) : ?>
				<div class="catalogist-finder__field catalogist-finder__field--search">
					<label for="<?php echo esc_attr( $catalogist_uid . '-q' ); ?>"><?php esc_html_e( 'Search programs', 'catalogist' ); ?></label>
					<input type="search" id="<?php echo esc_attr( $catalogist_uid . '-q' ); ?>" name="q" autocomplete="off">
				</div>
			<?php endif; ?>

			<?php foreach ( $catalogist_active_filters as $catalogist_param => $catalogist_label ) : ?>
				<div class="catalogist-finder__field">
					<label for="<?php echo esc_attr( $catalogist_uid . '-' . $catalogist_param ); ?>"><?php echo esc_html( $catalogist_label ); ?></label>
					<select id="<?php echo esc_attr( $catalogist_uid . '-' . $catalogist_param ); ?>" name="<?php echo esc_attr( $catalogist_param ); ?>">
						<option value=""><?php esc_html_e( 'All', 'catalogist' ); ?></option>
						<?php foreach ( $catalogist_options[ $catalogist_param ] as $catalogist_slug => $catalogist_name ) : ?>
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
		/* translators: %d: number of programs */
		echo esc_html( sprintf( _n( '%d program', '%d programs', $catalogist_count, 'catalogist' ), $catalogist_count ) );
		?>
	</p>

	<ul class="catalogist-finder__results" role="list">
		<?php foreach ( $catalogist_cards as $catalogist_card ) : ?>
			<li
				class="catalogist-finder__item"
				data-search="<?php echo esc_attr( $catalogist_card['search'] ); ?>"
				<?php foreach ( $catalogist_card['data'] as $catalogist_param => $catalogist_slugs ) : ?>
					data-<?php echo esc_attr( $catalogist_param ); ?>="<?php echo esc_attr( $catalogist_slugs ); ?>"
				<?php endforeach; ?>
			>
				<article class="catalogist-finder__card">
					<<?php echo $catalogist_heading_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- h2–h6 only. ?> class="catalogist-finder__title">
						<a href="<?php echo esc_url( get_permalink( $catalogist_card['post'] ) ); ?>"><?php echo esc_html( get_the_title( $catalogist_card['post'] ) ); ?></a>
					</<?php echo $catalogist_heading_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

					<?php if ( $catalogist_card['credentials'] ) : ?>
						<ul class="catalogist-finder__credentials">
							<?php foreach ( $catalogist_card['credentials'] as $catalogist_credential ) : ?>
								<?php
								// "Associate of Applied Science · 4 semesters · 64 credits"
								$catalogist_parts = array( $catalogist_credential['term']->name );
								if ( '' !== $catalogist_credential['length'] ) {
									$catalogist_parts[] = $catalogist_credential['length'];
								}
								if ( $catalogist_credential['credits'] > 0 ) {
									$catalogist_parts[] = sprintf(
										/* translators: %s: credit hours */
										_n( '%s credit', '%s credits', (int) ceil( $catalogist_credential['credits'] ), 'catalogist' ),
										Catalogist_Blocks::format_credits( $catalogist_credential['credits'] )
									);
								}
								?>
								<li><?php echo esc_html( implode( ' · ', $catalogist_parts ) ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php if ( '' !== $catalogist_card['notice'] ) : ?>
						<p class="catalogist-finder__notice"><?php echo esc_html( $catalogist_card['notice'] ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $attributes['showExcerpt'] ) && $catalogist_card['excerpt'] ) : ?>
						<p class="catalogist-finder__excerpt"><?php echo esc_html( $catalogist_card['excerpt'] ); ?></p>
					<?php endif; ?>
				</article>
			</li>
		<?php endforeach; ?>
	</ul>

	<div class="catalogist-finder__empty" hidden>
		<p><?php esc_html_e( 'No programs match your search.', 'catalogist' ); ?></p>
		<button type="button" class="catalogist-finder__reset"><?php esc_html_e( 'Clear filters', 'catalogist' ); ?></button>
	</div>
</div>
