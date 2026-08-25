<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$slides = array(
	array(
		'file'    => 'slide-1.svg',
		'caption' => 'Республіки, що входили до складу Соціалістичної Федеративної Республіки Югославія в 1991 році',
	),
	array(
		'file'    => 'slide-2.svg',
		'caption' => 'Етнічна мапа Югославії у 1991 році',
	),
	array(
		'file'    => 'slide-3.svg',
		'caption' => 'Самопроголошені Сербські автономні області на території Хорватії, 1991 рік',
	),
	array(
		'file'    => 'slide-4.svg',
		'caption' => '',
	),
	array(
		'file'    => 'slide-5.svg',
		'caption' => '',
	),
	array(
		'file'    => 'slide-6.svg',
		'caption' => '',
	),
);
?>
<section id="maps" class="section maps-slider">
	<div class="container">
		<h2 class="section__title">Історія війни на мапах</h2>
		<div class="maps-slider__frame">
			<div class="swiper maps-swiper">
				<div class="swiper-wrapper">
					<?php foreach ( $slides as $index => $slide ) : ?>
						<div class="swiper-slide maps-slider__slide">
							<img
								src="<?php echo esc_url( chornahora_asset_uri( 'images/maps/' . $slide['file'] ) ); ?>"
								alt="<?php echo esc_attr( $slide['caption'] ? $slide['caption'] : 'Map Slide ' . ( $index + 1 ) ); ?>"
								width="1200"
								height="800"
								loading="lazy"
							>
							<?php if ( $slide['caption'] ) : ?>
								<p class="maps-slider__caption"><?php echo esc_html( $slide['caption'] ); ?></p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<button class="maps-slider__nav maps-slider__prev" type="button" aria-label="Previous slide">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.5 5.5 9 12l6.5 6.5"/></svg>
			</button>
			<button class="maps-slider__nav maps-slider__next" type="button" aria-label="Next slide">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.5 5.5 15 12l-6.5 6.5"/></svg>
			</button>
			<div class="swiper-pagination maps-slider__dots"></div>
		</div>
	</div>
</section>
