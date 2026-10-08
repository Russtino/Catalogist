<?php
/**
 * Front-end output for the Course Description block.
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$catalogist_post_id = Catalogist_Blocks::context_post_id( $block, Catalogist_Post_Types::COURSE );
if ( ! $catalogist_post_id ) {
	return;
}

$catalogist_description = trim( (string) get_post_meta( $catalogist_post_id, '_catalogist_description', true ) );
if ( '' === $catalogist_description ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<?php echo wpautop( esc_html( $catalogist_description ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped, then paragraphs added. ?>
</div>
