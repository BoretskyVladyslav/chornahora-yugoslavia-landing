<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section id="order" class="section checkout-preview">
	<div class="container checkout-preview__layout">
		<div class="checkout-preview__cover">
			<img src="<?php echo esc_url( chornahora_asset_uri( 'images/cta-book.jpg' ) ); ?>" alt="Illustration" width="420" height="560" loading="lazy">
		</div>
		<div>
			<ul class="checkout-preview__meta">
				<li>Автор: Олександр Ткаченко</li>
				<li>Видавництво: «Чорна гора»</li>
				<li>Мова: українська</li>
				<li>Рік видання: 2026</li>
				<li>Обкладинка: тверда</li>
				<li>ISBN: 978-617-95046-6-2</li>
				<li>Доставка Новою Поштою в межах України</li>
			</ul>
			<div class="checkout-preview__buy">
				<p class="checkout-preview__price">
					<span class="checkout-preview__price-value">550</span>
					<span class="checkout-preview__price-currency">грн</span>
				</p>
				<a class="btn btn--primary" href="<?php echo esc_url( CHORNAHORA_CHECKOUT_URL ); ?>">ЗАМОВИТИ КНИГУ</a>
			</div>
		</div>
	</div>
</section>
