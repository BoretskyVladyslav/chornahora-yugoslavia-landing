<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$slides = array(
	array(
		'file'    => 'map-1.png',
		'caption' => 'Республіки, що входили до складу Соціалістичної Федеративної Республіки Югославія в 1991 році',
	),
	array(
		'file'    => 'map-2.png',
		'caption' => 'Етнічна мапа Югославії у 1991 році',
	),
	array(
		'file'    => 'map-3.png',
		'caption' => 'Самопроголошені Сербські автономні області на території Хорватії, 1991 рік',
	),
	array(
		'file'    => 'map-4.png',
		'caption' => '',
	),
	array(
		'file'    => 'map-5.png',
		'caption' => '',
	),
	array(
		'file'    => 'map-6.png',
		'caption' => '',
	),
	array(
		'file'    => 'map-7.png',
		'caption' => '',
	),
	array(
		'file'    => 'map-8.png',
		'caption' => '',
	),
);
?>
<section id="maps" class="section maps-slider">
	<div class="container">
		<h2 class="section__title">Історія війни на мапах</h2>
		<div class="maps-slider__frame">
			<div class="maps-slider__stage">
				<div class="swiper maps-swiper">
					<div class="swiper-wrapper">
						<?php foreach ( $slides as $index => $slide ) : ?>
							<?php
							$map_src = chornahora_asset_uri( 'images/maps/' . $slide['file'] );
							$map_alt = $slide['caption'] ? $slide['caption'] : 'Map Slide ' . ( $index + 1 );
							?>
							<div class="swiper-slide maps-slider__slide">
								<a
									class="glightbox maps-slider__zoom"
									href="<?php echo esc_url( $map_src ); ?>"
									data-gallery="maps"
									data-fancybox="maps"
									data-caption="<?php echo esc_attr( $map_alt ); ?>"
								>
									<img
										src="<?php echo esc_url( $map_src ); ?>"
										alt="<?php echo esc_attr( $map_alt ); ?>"
										width="1334"
										height="1000"
										loading="lazy"
									>
								</a>
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
			</div>
			<div class="swiper-pagination maps-slider__dots"></div>
		</div>
	</div>
</section>
