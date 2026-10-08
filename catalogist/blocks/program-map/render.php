<?php
/**
 * Front-end output for the Program Map block.
 *
 * Shows the map for one credential, or for every credential that has a map.
 * When more than one is shown, each gets a heading with the credential name.
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$catalogist_post_id = Catalogist_Blocks::context_post_id( $block, Catalogist_Post_Types::PROGRAM );
if ( ! $catalogist_post_id ) {
	return;
}

$catalogist_only = isset( $attributes['credential'] ) ? (int) $attributes['credential'] : 0;

$catalogist_maps = array_filter(
	Catalogist_Blocks::program_credentials( $catalogist_post_id ),
	function ( $credential ) use ( $catalogist_only ) {
		return $credential['map'] && ( ! $catalogist_only || $credential['term']->term_id === $catalogist_only );
	}
);

if ( ! $catalogist_maps ) {
	return;
}

// Load every course across the shown maps (choice options included) with a single query.
$catalogist_ids = array();
foreach ( $catalogist_maps as $catalogist_credential ) {
	$catalogist_ids = array_merge( $catalogist_ids, Catalogist_Catalog::map_course_ids( $catalogist_credential['map'] ) );
}
$catalogist_courses = Catalogist_Blocks::get_courses( $catalogist_ids );

/**
 * A course title, linked to its page when links are on.
 */
$catalogist_course_link = function ( $course, $text ) use ( &$catalogist_link_courses ) {
	return $catalogist_link_courses
		? '<a href="' . esc_url( get_permalink( $course ) ) . '">' . esc_html( $text ) . '</a>'
		: esc_html( $text );
};

/**
 * One table row for a map entry: [ html, credits ], or null when its course
 * isn't published.
 */
$catalogist_row = function ( $item ) use ( $catalogist_courses, $catalogist_course_link ) {
	if ( 'choice' === $item['type'] ) {
		$credits = isset( $item['credits'] ) ? (float) $item['credits'] : 0;
		// Each option is one course, or a group taken together: "BI 101 — Biology + BI 102 — Biology Lab".
		$options    = array();
		$has_groups = false;
		foreach ( Catalogist_Catalog::option_sets( $item ) as $set ) {
			$links = array();
			foreach ( $set as $option_id ) {
				if ( isset( $catalogist_courses[ $option_id ] ) ) {
					$links[] = $catalogist_course_link( $catalogist_courses[ $option_id ], Catalogist_Blocks::course_label( $catalogist_courses[ $option_id ] ) );
				}
			}
			// A group is only shown complete, so a missing lab never makes a lecture look like a whole option.
			if ( ! $links || count( $links ) !== count( $set ) ) {
				continue;
			}
			if ( count( $links ) > 1 ) {
				// Courses taken together are stacked as one option.
				$has_groups = true;
				$options[]  = '<ul class="catalogist-program-map__group"><li>' . implode( '</li><li>', $links ) . '</li></ul>';
			} else {
				$options[] = $links[0];
			}
		}

		$label = '' !== (string) $item['label'] ? $item['label'] : __( 'Elective', 'catalogist' );
		$html  = '<tr class="catalogist-program-map__choice"><td class="catalogist-program-map__code"></td><td>';
		$html .= '<span class="catalogist-program-map__choice-label">' . esc_html( $label ) . '</span>';
		if ( $options ) {
			// One option per line, under a "Choose one:" label when there's a real choice.
			if ( count( $options ) > 1 ) {
				$html .= '<span class="catalogist-program-map__choose">' . esc_html__( 'Choose one:', 'catalogist' ) . '</span>';
			}
			$html .= '<ul class="catalogist-program-map__options' . ( $has_groups ? ' has-groups' : '' ) . '"><li>' . implode( '</li><li>', $options ) . '</li></ul>';
		}
		$html .= '</td><td class="catalogist-num">' . ( $credits > 0 ? esc_html( Catalogist_Blocks::format_credits( $credits ) ) : '' ) . '</td></tr>';

		return array( $html, $credits );
	}

	$id = isset( $item['id'] ) ? (int) $item['id'] : 0;
	if ( ! isset( $catalogist_courses[ $id ] ) ) {
		return null; // Deleted or unpublished.
	}

	$course  = $catalogist_courses[ $id ];
	$credits = Catalogist_Blocks::course_credits( $course->ID );
	$html    = '<tr><td class="catalogist-program-map__code">' . esc_html( Catalogist_Blocks::course_code( $course->ID ) ) . '</td>'
		. '<td>' . $catalogist_course_link( $course, get_the_title( $course ) ) . '</td>'
		. '<td class="catalogist-num">' . esc_html( Catalogist_Blocks::format_credits( $credits ) ) . '</td></tr>';

	return array( $html, $credits );
};

/**
 * "15" or "15–16" for a set of totals.
 */
$catalogist_range = function ( $totals ) {
	$min = min( $totals );
	$max = max( $totals );
	return $min === $max
		? Catalogist_Blocks::format_credits( $min )
		/* translators: 1: lowest credit total, 2: highest credit total */
		: sprintf( __( '%1$s–%2$s', 'catalogist' ), Catalogist_Blocks::format_credits( $min ), Catalogist_Blocks::format_credits( $max ) );
};

$catalogist_maps          = array_values( $catalogist_maps );
// Semesters per row: 0 = as many as fit, otherwise 1–4.
$catalogist_columns = isset( $attributes['columns'] ) ? min( 4, max( 0, (int) $attributes['columns'] ) ) : 0;
$catalogist_link_courses  = ! empty( $attributes['linkCourses'] );
$catalogist_show_totals   = ! empty( $attributes['showTermTotals'] );
$catalogist_show_headings = count( $catalogist_maps ) > 1;
$catalogist_heading_tag   = 'h' . min( 6, max( 2, isset( $attributes['headingLevel'] ) ? (int) $attributes['headingLevel'] : 3 ) );

// Tabs: the tab bar is rendered hidden and switched on by the view script,
// so without JavaScript the maps simply stack under their headings.
$catalogist_tabs = $catalogist_show_headings && ( ! isset( $attributes['display'] ) || 'stacked' !== $attributes['display'] );
$catalogist_uid  = wp_unique_id( 'catalogist-map-' );

$catalogist_wrapper = get_block_wrapper_attributes(
	$catalogist_tabs ? array( 'class' => 'catalogist-program-map--tabs' ) : array()
);
?>
<div <?php echo $catalogist_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<?php if ( $catalogist_tabs ) : ?>
		<div class="catalogist-program-map__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Credentials', 'catalogist' ); ?>" hidden>
			<?php foreach ( $catalogist_maps as $catalogist_i => $catalogist_credential ) : ?>
				<button
					type="button"
					role="tab"
					class="catalogist-program-map__tab"
					id="<?php echo esc_attr( $catalogist_uid . '-tab-' . $catalogist_i ); ?>"
					aria-controls="<?php echo esc_attr( $catalogist_uid . '-panel-' . $catalogist_i ); ?>"
					aria-selected="<?php echo 0 === $catalogist_i ? 'true' : 'false'; ?>"
					tabindex="<?php echo 0 === $catalogist_i ? '0' : '-1'; ?>"
				><?php echo esc_html( $catalogist_credential['term']->name ); ?></button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php
	foreach ( $catalogist_maps as $catalogist_i => $catalogist_credential ) :
		$catalogist_pathways = $catalogist_credential['pathways'];
		$catalogist_pw_ids   = wp_list_pluck( $catalogist_pathways, 'id' );
		$catalogist_pw_names = wp_list_pluck( $catalogist_pathways, 'name', 'id' );

		// Program totals: one per pathway, or a single '' total without pathways.
		$catalogist_grand = $catalogist_pw_ids ? array_fill_keys( $catalogist_pw_ids, 0 ) : array( '' => 0 );
		?>
		<div class="catalogist-program-map__credential" id="<?php echo esc_attr( $catalogist_uid . '-panel-' . $catalogist_i ); ?>">
			<?php if ( $catalogist_show_headings ) : ?>
				<<?php echo $catalogist_heading_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- h2–h6 only. ?> class="catalogist-program-map__heading"><?php echo esc_html( $catalogist_credential['term']->name ); ?></<?php echo $catalogist_heading_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php endif; ?>

			<?php if ( '' !== $catalogist_credential['description'] ) : ?>
				<p class="catalogist-program-map__description"><?php echo esc_html( $catalogist_credential['description'] ); ?></p>
			<?php endif; ?>

			<?php if ( $catalogist_pathways ) : ?>
				<?php
				$catalogist_descriptions = array( '' => '' );
				foreach ( $catalogist_pathways as $catalogist_pathway ) {
					$catalogist_descriptions[ $catalogist_pathway['id'] ] = $catalogist_pathway['description'];
				}
				?>
				<?php // Pathway switcher: shown by the view script; without it, every pathway is listed. ?>
				<div class="catalogist-pathways" hidden data-descriptions="<?php echo esc_attr( wp_json_encode( $catalogist_descriptions ) ); ?>">
					<span class="catalogist-pathways__label" id="<?php echo esc_attr( $catalogist_uid . '-pw-' . $catalogist_i ); ?>"><?php esc_html_e( 'Pathway:', 'catalogist' ); ?></span>
					<span class="catalogist-pathways__buttons" role="group" aria-labelledby="<?php echo esc_attr( $catalogist_uid . '-pw-' . $catalogist_i ); ?>">
						<button type="button" class="catalogist-pathways__button" data-pathway="" aria-pressed="true"><?php esc_html_e( 'All', 'catalogist' ); ?></button>
						<?php foreach ( $catalogist_pathways as $catalogist_pathway ) : ?>
							<button type="button" class="catalogist-pathways__button" data-pathway="<?php echo esc_attr( $catalogist_pathway['id'] ); ?>" aria-pressed="false"><?php echo esc_html( $catalogist_pathway['name'] ); ?></button>
						<?php endforeach; ?>
					</span>
					<p class="catalogist-pathways__description" hidden></p>
				</div>
			<?php endif; ?>

			<div class="catalogist-program-map__terms"<?php echo $catalogist_columns ? ' data-columns="' . esc_attr( $catalogist_columns ) . '"' : ''; ?>>
				<?php
				foreach ( $catalogist_credential['map'] as $catalogist_index => $catalogist_term ) :
					// Rows grouped as shared ('') and per pathway. Unknown pathways count as shared.
					$catalogist_groups = array_merge( array( '' => array() ), array_fill_keys( $catalogist_pw_ids, array() ) );
					$catalogist_sums   = array_merge( array( '' => 0 ), array_fill_keys( $catalogist_pw_ids, 0 ) );

					foreach ( Catalogist_Catalog::term_items( $catalogist_term ) as $catalogist_item ) {
						$catalogist_pw = isset( $catalogist_item['pathway'] ) && in_array( $catalogist_item['pathway'], $catalogist_pw_ids, true ) ? $catalogist_item['pathway'] : '';
						$catalogist_r  = $catalogist_row( $catalogist_item );
						if ( $catalogist_r ) {
							$catalogist_groups[ $catalogist_pw ][] = $catalogist_r[0];
							$catalogist_sums[ $catalogist_pw ]    += $catalogist_r[1];
						}
					}

					// Term total per pathway (shared + that pathway's own).
					$catalogist_term_totals = array();
					if ( $catalogist_pw_ids ) {
						foreach ( $catalogist_pw_ids as $catalogist_id ) {
							$catalogist_term_totals[ $catalogist_id ] = $catalogist_sums[''] + $catalogist_sums[ $catalogist_id ];
						}
					} else {
						$catalogist_term_totals[''] = $catalogist_sums[''];
					}
					foreach ( $catalogist_term_totals as $catalogist_id => $catalogist_total ) {
						$catalogist_grand[ $catalogist_id ] += $catalogist_total;
					}

					$catalogist_has_rows = (bool) array_filter( $catalogist_groups );
					$catalogist_term_label = ! empty( $catalogist_term['label'] )
						? $catalogist_term['label']
						/* translators: %d: term number */
						: sprintf( __( 'Term %d', 'catalogist' ), $catalogist_index + 1 );
					?>
					<table class="catalogist-program-map__term">
						<caption><?php echo esc_html( $catalogist_term_label ); ?></caption>
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Course', 'catalogist' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Title', 'catalogist' ); ?></th>
								<th scope="col" class="catalogist-num"><?php esc_html_e( 'Credits', 'catalogist' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php echo implode( '', $catalogist_groups[''] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rows escaped when built. ?>
							<?php if ( ! $catalogist_has_rows ) : ?>
								<tr><td colspan="3"><?php esc_html_e( 'No courses listed.', 'catalogist' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
						<?php foreach ( $catalogist_pw_ids as $catalogist_id ) : ?>
							<?php if ( $catalogist_groups[ $catalogist_id ] ) : ?>
								<tbody class="catalogist-program-map__pathway" data-pathway="<?php echo esc_attr( $catalogist_id ); ?>">
									<tr class="catalogist-program-map__pathway-heading">
										<th colspan="3" scope="colgroup">
											<?php
											/* translators: %s: pathway name, e.g. Coding */
											echo esc_html( sprintf( __( '%s pathway', 'catalogist' ), $catalogist_pw_names[ $catalogist_id ] ) );
											?>
										</th>
									</tr>
									<?php echo implode( '', $catalogist_groups[ $catalogist_id ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rows escaped when built. ?>
								</tbody>
							<?php endif; ?>
						<?php endforeach; ?>
						<?php if ( $catalogist_show_totals ) : ?>
							<?php
							// Text for "All" ('') and for each pathway; the switcher swaps them.
							$catalogist_texts = array( '' => $catalogist_range( $catalogist_term_totals ) );
							foreach ( $catalogist_pw_ids as $catalogist_id ) {
								$catalogist_texts[ $catalogist_id ] = Catalogist_Blocks::format_credits( $catalogist_term_totals[ $catalogist_id ] );
							}
							?>
							<tfoot>
								<tr>
									<th scope="row" colspan="2"><?php esc_html_e( 'Term total', 'catalogist' ); ?></th>
									<td class="catalogist-num" data-totals="<?php echo esc_attr( wp_json_encode( $catalogist_texts ) ); ?>"><?php echo esc_html( $catalogist_texts[''] ); ?></td>
								</tr>
							</tfoot>
						<?php endif; ?>
					</table>
				<?php endforeach; ?>
			</div>

			<?php
			// "Total: 62 credits", or per pathway: "Total credits: Coding 62 · Networking 63".
			if ( $catalogist_pw_ids ) {
				$catalogist_parts = array();
				$catalogist_texts = array();
				foreach ( $catalogist_pw_ids as $catalogist_id ) {
					/* translators: 1: pathway name, 2: credit hours */
					$catalogist_parts[]                 = sprintf( __( '%1$s %2$s', 'catalogist' ), $catalogist_pw_names[ $catalogist_id ], Catalogist_Blocks::format_credits( $catalogist_grand[ $catalogist_id ] ) );
					/* translators: 1: pathway name, 2: credit hours */
					$catalogist_texts[ $catalogist_id ] = sprintf( __( 'Total, %1$s pathway: %2$s credits', 'catalogist' ), $catalogist_pw_names[ $catalogist_id ], Catalogist_Blocks::format_credits( $catalogist_grand[ $catalogist_id ] ) );
				}
				/* translators: %s: per-pathway totals, e.g. "Coding 62 · Networking 63" */
				$catalogist_texts[''] = sprintf( __( 'Total credits: %s', 'catalogist' ), implode( ' · ', $catalogist_parts ) );
			} else {
				/* translators: %s: total credit hours */
				$catalogist_texts = array( '' => sprintf( __( 'Total: %s credits', 'catalogist' ), Catalogist_Blocks::format_credits( $catalogist_grand[''] ) ) );
			}
			?>
			<p class="catalogist-program-map__total" data-totals="<?php echo esc_attr( wp_json_encode( $catalogist_texts ) ); ?>"><?php echo esc_html( $catalogist_texts[''] ); ?></p>
		</div>
	<?php endforeach; ?>
</div>
