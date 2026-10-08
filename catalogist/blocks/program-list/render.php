<?php
/**
 * Front-end output for the Program List block (outcomes, careers, or certifications).
 * Renders nothing, heading included, when the list is empty.
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$catalogist_post_id = Catalogist_Blocks::context_post_id( $block, Catalogist_Post_Types::PROGRAM );
if ( ! $catalogist_post_id ) {
	return;
}

$catalogist_type = isset( $attributes['list'] ) ? $attributes['list'] : 'outcomes';

// Groups of items, each with an optional label: [ [ label, items ], ... ].
$catalogist_groups = array();

switch ( $catalogist_type ) {
	case 'careers':
		$catalogist_default = __( 'Career opportunities', 'catalogist' );
		$catalogist_groups[] = array( '', Catalogist_Blocks::get_list( $catalogist_post_id, '_catalogist_careers' ) );
		break;

	case 'certifications':
		$catalogist_default  = __( 'Industry certifications', 'catalogist' );
		$catalogist_earned   = Catalogist_Blocks::get_list( $catalogist_post_id, '_catalogist_certifications' );
		$catalogist_optional = Catalogist_Blocks::get_list( $catalogist_post_id, '_catalogist_certifications_optional' );

		// Labels are only needed to tell the two groups apart.
		if ( $catalogist_earned ) {
			$catalogist_groups[] = array( $catalogist_optional ? __( 'Graduates earn:', 'catalogist' ) : '', $catalogist_earned );
		}
		if ( $catalogist_optional ) {
			$catalogist_groups[] = array( $catalogist_earned ? __( 'Graduates can also earn:', 'catalogist' ) : __( 'Graduates can earn:', 'catalogist' ), $catalogist_optional );
		}
		break;

	default:
		$catalogist_default  = __( 'Program outcomes', 'catalogist' );
		$catalogist_groups[] = array( '', Catalogist_Blocks::get_list( $catalogist_post_id, '_catalogist_outcomes' ) );
}

$catalogist_groups = array_filter(
	$catalogist_groups,
	function ( $group ) {
		return ! empty( $group[1] );
	}
);

if ( ! $catalogist_groups ) {
	return;
}

$catalogist_heading = trim( isset( $attributes['heading'] ) ? (string) $attributes['heading'] : '' );
$catalogist_heading = '' !== $catalogist_heading ? $catalogist_heading : $catalogist_default;
$catalogist_tag     = 'h' . min( 6, max( 2, isset( $attributes['headingLevel'] ) ? (int) $attributes['headingLevel'] : 2 ) );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'is-list-' . sanitize_html_class( $catalogist_type ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<<?php echo $catalogist_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- h2–h6 only. ?> class="catalogist-program-list__heading"><?php echo esc_html( $catalogist_heading ); ?></<?php echo $catalogist_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php foreach ( $catalogist_groups as $catalogist_group ) : ?>
		<?php if ( '' !== $catalogist_group[0] ) : ?>
			<p class="catalogist-program-list__label"><?php echo esc_html( $catalogist_group[0] ); ?></p>
		<?php endif; ?>
		<ul class="catalogist-program-list__items">
			<?php foreach ( $catalogist_group[1] as $catalogist_item ) : ?>
				<li><?php echo esc_html( $catalogist_item ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endforeach; ?>
</div>
