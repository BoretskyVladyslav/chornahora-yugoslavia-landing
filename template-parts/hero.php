<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hero_url = chornahora_asset_uri( 'images/hero.jpg' );
?>
<section id="cover" class="hero"<?php echo $hero_url ? ' style="background-image: url(\'' . esc_url( $hero_url ) . '\');"' : ''; ?>>
	<h1 class="sr-only">Олександр Ткаченко — Кривава агонія Югославії</h1>
	<div class="hero__inner" aria-hidden="true"></div>
</section>
