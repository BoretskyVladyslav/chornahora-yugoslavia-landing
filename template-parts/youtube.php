<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$video = wp_parse_args(
	$args ?? array(),
	array(
		'id'    => '',
		'start' => 0,
		'title' => 'YouTube',
	)
);

if ( '' === $video['id'] ) {
	return;
}

$thumb = 'https://i.ytimg.com/vi/' . rawurlencode( $video['id'] ) . '/hqdefault.jpg';
?>
<div
	class="yt-facade"
	data-youtube-id="<?php echo esc_attr( $video['id'] ); ?>"
	data-start="<?php echo esc_attr( (string) $video['start'] ); ?>"
	data-title="<?php echo esc_attr( $video['title'] ); ?>"
>
	<button class="yt-facade__play" type="button" aria-label="<?php echo esc_attr( $video['title'] ); ?>">
		<img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy" width="480" height="360">
	</button>
</div>
