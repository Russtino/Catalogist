<?php
/**
 * Front-end output for the Course Info block.
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$catalogist_post_id = Catalogist_Blocks::context_post_id( $block, Catalogist_Post_Types::COURSE );
if ( ! $catalogist_post_id ) {
	return;
}

$catalogist_link    = ! empty( $attributes['linkPrerequisites'] );
$catalogist_code    = Catalogist_Blocks::course_code( $catalogist_post_id );
$catalogist_credits = Catalogist_Blocks::course_credits( $catalogist_post_id );
$catalogist_notes   = (string) get_post_meta( $catalogist_post_id, '_catalogist_prerequisite_notes', true );
$catalogist_prereqs = Catalogist_Blocks::get_courses( get_post_meta( $catalogist_post_id, '_catalogist_prerequisites', true ) );

// Build the prerequisite list; each item is already escaped.
$catalogist_prereq_items = array();
foreach ( $catalogist_prereqs as $catalogist_prereq ) {
	$catalogist_label          = esc_html( Catalogist_Blocks::course_label( $catalogist_prereq ) );
	$catalogist_prereq_items[] = $catalogist_link
		? '<a href="' . esc_url( get_permalink( $catalogist_prereq ) ) . '">' . $catalogist_label . '</a>'
		: $catalogist_label;
}
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<dl class="catalogist-details">
		<?php if ( $catalogist_code ) : ?>
			<div>
				<dt><?php esc_html_e( 'Course code', 'catalogist' ); ?></dt>
				<dd><?php echo esc_html( $catalogist_code ); ?></dd>
			</div>
		<?php endif; ?>
		<?php if ( $catalogist_credits > 0 ) : ?>
			<div>
				<dt><?php esc_html_e( 'Credit hours', 'catalogist' ); ?></dt>
				<dd><?php echo esc_html( Catalogist_Blocks::format_credits( $catalogist_credits ) ); ?></dd>
			</div>
		<?php endif; ?>
		<?php
		$catalogist_hours = array(
			'_catalogist_lecture_hours' => __( 'Lecture hours', 'catalogist' ),
			'_catalogist_lab_hours'     => __( 'Lab hours', 'catalogist' ),
			'_catalogist_contact_hours' => __( 'Contact hours', 'catalogist' ),
		);
		foreach ( $catalogist_hours as $catalogist_key => $catalogist_label ) :
			$catalogist_value = (float) get_post_meta( $catalogist_post_id, $catalogist_key, true );
			if ( $catalogist_value <= 0 ) {
				continue;
			}
			?>
			<div>
				<dt><?php echo esc_html( $catalogist_label ); ?></dt>
				<dd><?php echo esc_html( Catalogist_Blocks::format_credits( $catalogist_value ) ); ?></dd>
			</div>
		<?php endforeach; ?>
		<div>
			<dt><?php esc_html_e( 'Prerequisites', 'catalogist' ); ?></dt>
			<dd>
				<?php if ( $catalogist_prereq_items ) : ?>
					<ul class="catalogist-details__list">
						<?php foreach ( $catalogist_prereq_items as $catalogist_item ) : ?>
							<li><?php echo $catalogist_item; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( $catalogist_notes ) : ?>
					<p class="catalogist-details__note"><?php echo esc_html( $catalogist_notes ); ?></p>
				<?php endif; ?>
				<?php
				if ( ! $catalogist_prereq_items && ! $catalogist_notes ) {
					esc_html_e( 'None', 'catalogist' );
				}
				?>
			</dd>
		</div>
	</dl>
</div>
