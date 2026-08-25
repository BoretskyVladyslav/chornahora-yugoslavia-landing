<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="main" class="site-main checkout-page checkout-page--thank-you">
	<div class="container">
		<?php
		get_template_part(
			'template-parts/checkout-steps',
			null,
			array(
				'current' => 3,
			)
		);
		?>
		<section class="checkout-thankyou">
			<h1 class="checkout-thankyou__title">Замовлення отримано</h1>
			<p class="checkout-thankyou__text">Дякуємо. Ваше замовлення прийнято.</p>
			<a class="btn btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">На головну</a>
		</section>
	</div>
</main>
<?php
get_footer();
