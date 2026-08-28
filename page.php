<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="main" class="site-main legal-page">
	<article class="section legal-page__section">
		<div class="container legal-page__inner">
			<?php
			if ( have_posts() ) {
				while ( have_posts() ) {
					the_post();
					the_title( '<h1>', '</h1>' );
					the_content();
				}
			}
			?>
		</div>
	</article>
</main>
<?php
get_footer();
