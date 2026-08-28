<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current = isset( $args['current'] ) ? (int) $args['current'] : 1;
$steps   = array(
	1 => 'Кошик',
	2 => 'Оплата',
	3 => 'Замовлення отримано',
);
?>
<nav class="checkout-steps" aria-label="Етапи оформлення замовлення">
	<ol class="checkout-steps__list">
		<?php foreach ( $steps as $index => $label ) : ?>
			<?php
			$class = 'checkout-steps__item';
			if ( $index === $current ) {
				$class .= ' is-active';
			} elseif ( $index < $current ) {
				$class .= ' is-done';
			}
			if ( 3 === $index ) {
				$class .= ' checkout-steps__item--received';
			}
			?>
			<li class="<?php echo esc_attr( $class ); ?>">
				<span class="checkout-steps__num" aria-hidden="true"><?php echo esc_html( (string) $index ); ?></span>
				<span class="checkout-steps__label"><?php echo esc_html( $label ); ?></span>
			</li>
		<?php endforeach; ?>
	</ol>
</nav>
