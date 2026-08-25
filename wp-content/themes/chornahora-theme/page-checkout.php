<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="main" class="site-main checkout-page">
	<div class="container">
		<?php
		get_template_part(
			'template-parts/checkout-steps',
			null,
			array(
				'current' => 1,
			)
		);
		get_template_part( 'template-parts/checkout-form' );
		?>
	</div>
</main>
<?php
get_footer();
