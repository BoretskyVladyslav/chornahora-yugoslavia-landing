<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="main" class="site-main">
	<?php
	get_template_part( 'template-parts/hero' );
	get_template_part( 'template-parts/intro' );
	get_template_part( 'template-parts/maps-slider' );
	get_template_part( 'template-parts/quote' );
	get_template_part( 'template-parts/contents' );
	get_template_part( 'template-parts/videos' );
	get_template_part( 'template-parts/checkout-preview' );
	?>
</main>
<?php
get_footer();
