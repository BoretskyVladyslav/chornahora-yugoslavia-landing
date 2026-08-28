<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section id="contents" class="section contents">
	<div class="container contents__layout">
		<div class="contents__copy">
			<h2 class="section__title">ЗМІСТ</h2>
			<ul class="contents__list">
				<li>● Етнічна картина Західних Балкан – національний склад,<br>мовна специфіка, історичні фактори</li>
				<li>● Югославія у Другій світовій війні</li>
				<li>● Соціалістичний режим Тіто</li>
				<li>● Початок розпаду. Десятиденна війна у Словенії</li>
				<li>● Мовчазний вихід Македонії</li>
				<li>● Війна за незалежність Хорватії</li>
				<li>● Розгортання миротворчих місій ООН</li>
				<li>● Боснія: війна всіх проти всіх</li>
				<li>● Геноцид у Сребрениці, порятунок у Жепі</li>
				<li>● Операція НАТО «Обдумана сила»</li>
				<li>● Війна у Косові</li>
				<li>● Операція НАТО «Союзна сила»</li>
				<li>● Крах Мілошевича та кінець держави південних слов’ян</li>
				<li>● Чорногорія залишає Югославію</li>
				<li>● Незалежність Косова</li>
				<li>● Заморожені конфлікти Балкан</li>
			</ul>
		</div>
		<div class="contents__map">
			<?php $contents_map = chornahora_asset_uri( 'images/contents-map.jpg' ); ?>
			<?php if ( $contents_map ) : ?>
				<img src="<?php echo esc_url( $contents_map ); ?>" alt="Illustration" width="800" height="800" loading="lazy">
			<?php endif; ?>
		</div>
	</div>
	<div class="container">
		<?php get_template_part( 'template-parts/cta-buttons' ); ?>
	</div>
</section>
