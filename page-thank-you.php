<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$order_id = '';

if ( isset( $_GET['order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$order_id = sanitize_text_field( wp_unslash( $_GET['order'] ) );
} elseif ( isset( $_POST['orderReference'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$order_id = sanitize_text_field( wp_unslash( $_POST['orderReference'] ) );
}

$order = '' !== $order_id ? Chornahora_Order_Processor::find_by_order_id( $order_id ) : null;

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
			<?php if ( $order ) : ?>
				<p class="checkout-thankyou__text">Дякуємо. Ваше замовлення прийнято.</p>
				<dl class="checkout-thankyou__summary">
					<div>
						<dt>Номер замовлення</dt>
						<dd><?php echo esc_html( $order['order_id'] ); ?></dd>
					</div>
					<div>
						<dt>ПІБ</dt>
						<dd><?php echo esc_html( $order['full_name'] ); ?></dd>
					</div>
					<div>
						<dt>Телефон</dt>
						<dd><?php echo esc_html( $order['phone'] ); ?></dd>
					</div>
					<div>
						<dt>Email</dt>
						<dd><?php echo esc_html( $order['email'] ); ?></dd>
					</div>
					<div>
						<dt>Місто</dt>
						<dd><?php echo esc_html( $order['city'] ); ?></dd>
					</div>
					<div>
						<dt>Відділення/поштомат</dt>
						<dd><?php echo esc_html( $order['warehouse_label'] ); ?></dd>
					</div>
					<div>
						<dt>Оплата</dt>
						<dd><?php echo esc_html( Chornahora_Order_Processor::payment_label( $order['payment'] ) ); ?></dd>
					</div>
					<div>
						<dt>Сума</dt>
						<dd><?php echo esc_html( (string) $order['amount'] ); ?> UAH</dd>
					</div>
				</dl>
			<?php else : ?>
				<p class="checkout-thankyou__text">Дякуємо. Ваше замовлення прийнято.</p>
			<?php endif; ?>
			<a class="btn btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">На головну</a>
		</section>
	</div>
</main>
<?php
get_footer();
