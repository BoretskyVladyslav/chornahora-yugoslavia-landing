<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="main" class="site-main error-404">
	<section class="section error-404__section">
		<div class="container error-404__inner">
			<h1 class="error-404__title">404 - Сторінку не знайдено</h1>
			<p class="error-404__text">На жаль, сторінка, яку ви шукаєте, не існує або була переміщена.</p>
			<a class="btn btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">Повернутися на головну</a>
		</div>
	</section>
</main>
<?php
get_footer();
