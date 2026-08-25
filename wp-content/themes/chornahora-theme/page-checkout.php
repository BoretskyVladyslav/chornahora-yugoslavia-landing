<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="main" class="site-main checkout-page">
	<div class="checkout-notice">
		<div class="container checkout-notice__inner">
			<p class="checkout-notice__text">
				<span class="checkout-notice__check" aria-hidden="true">✓</span>
				«Книга "Кривава агонія Югославії" (Олександр Ткаченко)» додано до кошика.
			</p>
			<a class="checkout-notice__btn" href="<?php echo esc_url( chornahora_checkout_url() ); ?>">Переглянути кошик</a>
		</div>
	</div>
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
