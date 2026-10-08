<?php
/**
 * Front-end output for the Program Details block.
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$catalogist_post_id = Catalogist_Blocks::context_post_id( $block, Catalogist_Post_Types::PROGRAM );
if ( ! $catalogist_post_id ) {
	return;
}

// Each credential with its details, e.g. "Associate of Applied Science: 4 semesters, 64 credits".
$catalogist_credential_html = '';
$catalogist_credentials     = Catalogist_Blocks::program_credentials( $catalogist_post_id );

if ( $catalogist_credentials ) {
	$catalogist_credential_html = '<ul class="catalogist-details__list">';

	foreach ( $catalogist_credentials as $catalogist_credential ) {
		$catalogist_parts = array();
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

		$catalogist_item = $catalogist_parts
			? sprintf(
				/* translators: 1: credential type, 2: details such as "4 semesters, 64 credits" */
				__( '%1$s: %2$s', 'catalogist' ),
				$catalogist_credential['term']->name,
				implode( ', ', $catalogist_parts )
			)
			: $catalogist_credential['term']->name;

		$catalogist_credential_html .= '<li>' . esc_html( $catalogist_item );
		if ( '' !== $catalogist_credential['description'] ) {
			$catalogist_credential_html .= '<span class="catalogist-details__description">' . esc_html( $catalogist_credential['description'] ) . '</span>';
		}
		if ( $catalogist_credential['pathways'] ) {
			$catalogist_credential_html .= '<ul class="catalogist-details__pathways" aria-label="' . esc_attr__( 'Pathways', 'catalogist' ) . '">';
			foreach ( $catalogist_credential['pathways'] as $catalogist_pathway ) {
				$catalogist_text = '' !== $catalogist_pathway['description']
					/* translators: 1: pathway name, 2: pathway description */
					? sprintf( __( '%1$s pathway: %2$s', 'catalogist' ), $catalogist_pathway['name'], $catalogist_pathway['description'] )
					/* translators: %s: pathway name */
					: sprintf( __( '%s pathway', 'catalogist' ), $catalogist_pathway['name'] );
				$catalogist_credential_html .= '<li>' . esc_html( $catalogist_text ) . '</li>';
			}
			$catalogist_credential_html .= '</ul>';
		}
		$catalogist_credential_html .= '</li>';
	}

	$catalogist_credential_html .= '</ul>';
}

// "11.1006 · Computer Support Specialist", either part optional.
$catalogist_cip = implode(
	' · ',
	array_filter(
		array(
			(string) get_post_meta( $catalogist_post_id, '_catalogist_cip_code', true ),
			(string) get_post_meta( $catalogist_post_id, '_catalogist_cip_title', true ),
		),
		'strlen'
	)
);

$catalogist_notice = trim( (string) get_post_meta( $catalogist_post_id, '_catalogist_enrollment_notice', true ) );

// Label => HTML. Every value is escaped before it goes in; empty rows are skipped.
$catalogist_rows = array(
	__( 'Credentials', 'catalogist' ) => $catalogist_credential_html,
	__( 'Start dates', 'catalogist' ) => esc_html( (string) get_post_meta( $catalogist_post_id, '_catalogist_start_terms', true ) ),
	__( 'Delivery', 'catalogist' )    => esc_html( Catalogist_Blocks::term_list( $catalogist_post_id, Catalogist_Taxonomies::DELIVERY ) ),
	__( 'Department', 'catalogist' )  => esc_html( Catalogist_Blocks::term_list( $catalogist_post_id, Catalogist_Taxonomies::DEPARTMENT ) ),
	__( 'Career area', 'catalogist' ) => esc_html( Catalogist_Blocks::term_list( $catalogist_post_id, Catalogist_Taxonomies::CAREER ) ),
	__( 'CIP code', 'catalogist' )    => esc_html( $catalogist_cip ),
);

$catalogist_rows = array_filter( $catalogist_rows, 'strlen' );
if ( ! $catalogist_rows && '' === $catalogist_notice ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<?php if ( '' !== $catalogist_notice ) : ?>
		<div class="catalogist-notice"><?php echo wpautop( esc_html( $catalogist_notice ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped, then paragraphs added. ?></div>
	<?php endif; ?>
	<?php if ( $catalogist_rows ) : ?>
	<dl class="catalogist-details">
		<?php foreach ( $catalogist_rows as $catalogist_label => $catalogist_html ) : ?>
			<div>
				<dt><?php echo esc_html( $catalogist_label ); ?></dt>
				<dd><?php echo $catalogist_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></dd>
			</div>
		<?php endforeach; ?>
	</dl>
	<?php endif; ?>
</div>
