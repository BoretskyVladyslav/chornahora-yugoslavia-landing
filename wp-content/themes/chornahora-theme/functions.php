<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CHORNAHORA_CHECKOUT_URL', 'https://rusova.chornahora.com.ua/' );
define( 'CHORNAHORA_BOOK_PRICE', 550 );
define( 'CHORNAHORA_NP_API_KEY', 'b1c8fee45753bde5092988529e9f305b' );
define( 'CHORNAHORA_ORDER_EMAIL', 'chornagorabook@gmail.com' );
define( 'CHORNAHORA_SHEETS_ID', '1qVMbKvY5Bs6EGUGi-Y4mdbB5mCEviQn9bspCrNjsYM4' );
define( 'CHORNAHORA_SHEETS_WEBHOOK', '' );
define( 'CHORNAHORA_WFP_MERCHANT', 'test_merch_n1' );
define( 'CHORNAHORA_WFP_SECRET', 'flk3409refn54t54t*FNJRET' );

require get_template_directory() . '/inc/checkout/class-nova-poshta.php';
require get_template_directory() . '/inc/checkout/class-wayforpay.php';
require get_template_directory() . '/inc/checkout/class-order-processor.php';
require get_template_directory() . '/inc/checkout/ajax.php';

function chornahora_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
}
add_action( 'after_setup_theme', 'chornahora_theme_setup' );

function chornahora_asset_uri( $path ) {
	return get_template_directory_uri() . '/assets/' . ltrim( $path, '/' );
}

function chornahora_theme_scripts() {
	$version = wp_get_theme()->get( 'Version' );

	wp_enqueue_style(
		'chornahora-fonts',
		'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Roboto:ital,wght@0,400;0,700;1,400&family=Teko:wght@600;700&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'chornahora-theme',
		get_stylesheet_uri(),
		array( 'chornahora-fonts' ),
		$version
	);

	wp_enqueue_style(
		'chornahora-main',
		chornahora_asset_uri( 'css/main.css' ),
		array( 'chornahora-theme' ),
		$version
	);

	if ( is_front_page() ) {
		wp_enqueue_style(
			'swiper',
			'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
			array(),
			'11'
		);

		wp_enqueue_script(
			'swiper',
			'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
			array(),
			'11',
			true
		);

		wp_enqueue_script(
			'chornahora-main',
			chornahora_asset_uri( 'js/main.js' ),
			array( 'swiper' ),
			$version,
			true
		);

		wp_enqueue_script(
			'chornahora-checkout',
			chornahora_asset_uri( 'js/checkout.js' ),
			array(),
			$version,
			true
		);

		wp_localize_script(
			'chornahora-checkout',
			'chCheckout',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'chornahora_checkout' ),
				'amount'  => CHORNAHORA_BOOK_PRICE,
			)
		);
	} else {
		wp_enqueue_script(
			'chornahora-main',
			chornahora_asset_uri( 'js/main.js' ),
			array(),
			$version,
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'chornahora_theme_scripts' );

function chornahora_register_order_cpt() {
	register_post_type(
		'ch_order',
		array(
			'labels'      => array(
				'name'          => 'Замовлення',
				'singular_name' => 'Замовлення',
			),
			'public'      => false,
			'show_ui'     => false,
			'supports'    => array( 'title' ),
		)
	);
}
add_action( 'init', 'chornahora_register_order_cpt' );

add_action( 'wp_ajax_ch_search_cities', 'ch_search_cities' );
add_action( 'wp_ajax_nopriv_ch_search_cities', 'ch_search_cities' );
add_action( 'wp_ajax_ch_get_warehouses', 'ch_get_warehouses' );
add_action( 'wp_ajax_nopriv_ch_get_warehouses', 'ch_get_warehouses' );
add_action( 'wp_ajax_ch_process_order', 'ch_process_order' );
add_action( 'wp_ajax_nopriv_ch_process_order', 'ch_process_order' );

function chornahora_bootstrap_pages() {
	$pages = array(
		array(
			'title' => 'Головна',
			'slug'  => 'home',
		),
		array(
			'title' => 'Оплата та доставка',
			'slug'  => 'oplata-ta-dostavka',
		),
		array(
			'title' => 'Повернення',
			'slug'  => 'povernennya',
		),
		array(
			'title' => 'Політика конфіденційності',
			'slug'  => 'politika-konfidenciynosti',
		),
		array(
			'title' => 'Контакти',
			'slug'  => 'kontakty',
		),
	);

	$home_id = 0;

	foreach ( $pages as $page ) {
		$existing = get_page_by_path( $page['slug'] );

		if ( $existing instanceof WP_Post ) {
			$page_id = (int) $existing->ID;
		} else {
			$page_id = wp_insert_post(
				array(
					'post_title'   => $page['title'],
					'post_name'    => $page['slug'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_content' => '',
				),
				true
			);

			if ( is_wp_error( $page_id ) ) {
				continue;
			}
		}

		if ( 'home' === $page['slug'] ) {
			$home_id = (int) $page_id;
		}
	}

	if ( $home_id > 0 ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home_id );
	}

	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'chornahora_bootstrap_pages' );
