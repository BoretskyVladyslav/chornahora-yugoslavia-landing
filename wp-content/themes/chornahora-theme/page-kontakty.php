<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="main" class="site-main legal-page">
	<article class="section legal-page__section">
		<div class="container legal-page__inner">
			<h1 class="legal-page__title">Контакти</h1>
			<p>Звертайтесь, будь ласка, до нас, використовуючи:</p>
			<dl class="legal-page__contacts">
				<div>
					<dt>Видавництво</dt>
					<dd>ТОВ «Книжкове видавництво «Чорна гора»</dd>
				</div>
				<div>
					<dt>Адреса</dt>
					<dd>03113, м. Київ, вул. Дегтярівська, 43/1, оф. 24</dd>
				</div>
				<div>
					<dt>Телефон</dt>
					<dd><a href="tel:+380678876649">+38 (067) 887-66-49</a></dd>
				</div>
				<div>
					<dt>E-mail</dt>
					<dd><a href="mailto:<?php echo esc_attr( CHORNAHORA_ORDER_EMAIL ); ?>"><?php echo esc_html( CHORNAHORA_ORDER_EMAIL ); ?></a></dd>
				</div>
			</dl>
		</div>
	</article>
</main>
<?php
get_footer();
