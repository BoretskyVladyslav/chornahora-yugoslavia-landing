<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_checkout_flow = function_exists( 'chornahora_is_checkout_flow' ) && chornahora_is_checkout_flow();
?>
<footer class="site-footer<?php echo $is_checkout_flow ? ' site-footer--checkout' : ''; ?>">
	<?php if ( $is_checkout_flow ) : ?>
		<div class="container site-footer__inner site-footer__inner--checkout">
			<div class="site-footer__brand">
				<a class="site-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<img src="<?php echo esc_url( chornahora_asset_uri( 'images/logo.png' ) ); ?>" alt="Чорна гора" width="64" height="80">
				</a>
			</div>
			<div class="site-footer__col">
				<h3 class="footer__title">Меню</h3>
				<nav aria-label="Footer">
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/oplata-ta-dostavka/' ) ); ?>">Оплата та доставка</a></li>
						<li><a href="<?php echo esc_url( home_url( '/povernennya/' ) ); ?>">Повернення</a></li>
						<li><a href="<?php echo esc_url( home_url( '/politika-konfidenciynosti/' ) ); ?>">Політика конфіденційності</a></li>
					</ul>
				</nav>
			</div>
			<div class="site-footer__col">
				<h3 class="footer__title">Звертайтесь, будь ласка, до нас, використовуючи:</h3>
				<ul class="site-footer__contacts">
					<li>
						<span>Пошта</span>
						<a href="mailto:<?php echo esc_attr( CHORNAHORA_ORDER_EMAIL ); ?>"><?php echo esc_html( CHORNAHORA_ORDER_EMAIL ); ?></a>
					</li>
					<li>
						<span>Телефон</span>
						<a href="tel:+380678876649"><?php echo esc_html( CHORNAHORA_CONTACT_PHONE ); ?></a>
					</li>
				</ul>
			</div>
		</div>
	<?php else : ?>
		<div class="container site-footer__inner">
			<div class="site-footer__brand">
				<a class="site-logo" href="https://chornahora.com.ua/">
					<img src="<?php echo esc_url( chornahora_asset_uri( 'images/logo.png' ) ); ?>" alt="logo" width="64" height="80">
				</a>
			</div>
			<div class="site-footer__cluster">
				<h3 class="footer__title">ТОВ «Книжкове видавництво «Чорна гора»</h3>
				<nav aria-label="Footer">
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/oplata-ta-dostavka/' ) ); ?>">Оплата та доставка</a></li>
						<li><a href="<?php echo esc_url( home_url( '/povernennya/' ) ); ?>">Повернення</a></li>
						<li><a href="<?php echo esc_url( home_url( '/politika-konfidenciynosti/' ) ); ?>">Політика  конфіденційності</a></li>
						<li><a href="<?php echo esc_url( home_url( '/kontakty/' ) ); ?>">Контакти</a></li>
					</ul>
				</nav>
				<p class="site-footer__copy">All rights Reserved, 2026</p>
			</div>
		</div>
	<?php endif; ?>
</footer>
