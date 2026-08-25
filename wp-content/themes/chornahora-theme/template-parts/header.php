<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<header class="site-header">
	<a class="site-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<img src="<?php echo esc_url( chornahora_asset_uri( 'images/logo.png' ) ); ?>" alt="logo" width="86" height="108">
	</a>
	<div class="container site-header__inner">
		<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Menu">
			<span></span>
		</button>
		<nav id="site-nav" class="site-nav" aria-label="Primary">
			<ul>
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Про нас</a></li>
				<li><a href="<?php echo esc_url( home_url( '/oplata-ta-dostavka/' ) ); ?>">Оплата та доставка</a></li>
				<li><a href="<?php echo esc_url( home_url( '/povernennya/' ) ); ?>">Повернення</a></li>
				<li><a href="<?php echo esc_url( home_url( '/politika-konfidenciynosti/' ) ); ?>">Політика конфіденційності</a></li>
				<li><a href="<?php echo esc_url( home_url( '/kontakty/' ) ); ?>">Контакти</a></li>
			</ul>
		</nav>
	</div>
</header>
