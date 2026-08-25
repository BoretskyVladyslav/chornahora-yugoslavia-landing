<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = wp_parse_args(
	$args ?? array(),
	array(
		'modifier' => 'two',
		'videos'   => array(),
	)
);
?>
<div class="video-grid video-grid--<?php echo esc_attr( $args['modifier'] ); ?>">
	<?php
	foreach ( $args['videos'] as $video ) {
		get_template_part( 'template-parts/youtube', null, $video );
	}
	?>
</div>
