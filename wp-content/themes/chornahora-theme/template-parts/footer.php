<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<footer class="site-footer">
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
</footer>
