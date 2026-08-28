<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CHORNAHORA_BOOK_PRICE', 550 );
define( 'CHORNAHORA_PAGES_VERSION', 2 );
define( 'CHORNAHORA_NP_API_KEY', 'b1c8fee45753bde5092988529e9f305b' );
define( 'CHORNAHORA_ORDER_EMAIL', 'chornagorabook@gmail.com' );
define( 'CHORNAHORA_SHEETS_ID', '1qVMbKvY5Bs6EGUGi-Y4mdbB5mCEviQn9bspCrNjsYM4' );

if ( ! defined( 'CHORNAHORA_MAIL_FROM' ) ) {
	define( 'CHORNAHORA_MAIL_FROM', 'order@yugoslavia.chornahora.com.ua' );
}

if ( ! defined( 'CHORNAHORA_MAIL_FROM_FALLBACK' ) ) {
	define( 'CHORNAHORA_MAIL_FROM_FALLBACK', 'wordpress@yugoslavia.chornahora.com.ua' );
}

if ( ! defined( 'CHORNAHORA_MAIL_FROM_NAME' ) ) {
	define( 'CHORNAHORA_MAIL_FROM_NAME', 'Видавництво Чорна Гора' );
}

if ( ! defined( 'CHORNAHORA_SHEETS_WEBHOOK_URL' ) ) {
	$legacy_sheets = defined( 'CHORNAHORA_SHEETS_WEBHOOK' ) ? CHORNAHORA_SHEETS_WEBHOOK : '';
	if ( '' === (string) $legacy_sheets ) {
		$legacy_sheets = 'https://script.google.com/macros/s/AKfycbyHOZDuBG9jXdb054JqUbVZHRzkIUR6u38LnhMmFgKrqnwvVe6f8Obc2yPCrh6_WvoM1g/exec';
	}
	define( 'CHORNAHORA_SHEETS_WEBHOOK_URL', $legacy_sheets );
}

if ( ! defined( 'CHORNAHORA_SHEETS_WEBHOOK' ) ) {
	define( 'CHORNAHORA_SHEETS_WEBHOOK', CHORNAHORA_SHEETS_WEBHOOK_URL );
}

if ( ! defined( 'CHORNAHORA_WFP_MERCHANT' ) ) {
	define( 'CHORNAHORA_WFP_MERCHANT', 'test_merch_n1' );
}

if ( ! defined( 'CHORNAHORA_WFP_SECRET' ) ) {
	define( 'CHORNAHORA_WFP_SECRET', 'flk3409refn54t54t*FNJRET' );
}

$ch_requires = array(
	'/inc/checkout/class-nova-poshta.php',
	'/inc/checkout/class-wayforpay.php',
	'/inc/checkout/class-order-processor.php',
	'/inc/checkout/ajax.php',
);

foreach ( $ch_requires as $ch_file ) {
	$ch_path = get_template_directory() . $ch_file;
	if ( is_readable( $ch_path ) ) {
		require $ch_path;
	}
}

function send_to_google_sheets( $payload, $blocking = false ) {
	return Chornahora_Order_Processor::send_to_google_sheets( $payload, $blocking );
}

function chornahora_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
}
add_action( 'after_setup_theme', 'chornahora_theme_setup' );

function chornahora_https_url( $url ) {
	$url = (string) $url;

	if ( '' === $url ) {
		return '';
	}

	return set_url_scheme( $url, 'https' );
}

function chornahora_theme_file_uri( $relative ) {
	$relative = ltrim( (string) $relative, '/' );
	$path     = get_theme_file_path( $relative );

	if ( ! $path || ! file_exists( $path ) ) {
		return '';
	}

	return chornahora_https_url( get_template_directory_uri() . '/' . $relative );
}

function chornahora_asset_uri( $path ) {
	return chornahora_theme_file_uri( 'assets/' . ltrim( (string) $path, '/' ) );
}

function chornahora_asset_version( $relative_path ) {
	$path = get_theme_file_path( $relative_path );

	if ( $path && file_exists( $path ) ) {
		return (string) filemtime( $path );
	}

	return (string) wp_get_theme()->get( 'Version' );
}

function chornahora_enqueue_style_file( $handle, $relative, $deps = array() ) {
	$uri = chornahora_theme_file_uri( $relative );

	if ( '' === $uri ) {
		return false;
	}

	wp_enqueue_style( $handle, $uri, $deps, chornahora_asset_version( $relative ) );

	return true;
}

function chornahora_enqueue_script_file( $handle, $relative, $deps = array(), $in_footer = true ) {
	$uri = chornahora_theme_file_uri( $relative );

	if ( '' === $uri ) {
		return false;
	}

	wp_enqueue_script( $handle, $uri, $deps, chornahora_asset_version( $relative ), $in_footer );

	return true;
}

function chornahora_checkout_url() {
	return home_url( '/checkout/' );
}

function chornahora_thankyou_url( $order_id = '' ) {
	$url = home_url( '/thank-you/' );

	if ( '' !== $order_id ) {
		$url = add_query_arg( 'order', (string) $order_id, $url );
	}

	return $url;
}

function chornahora_handle_wfp_notify() {
	if ( ! isset( $_GET['ch_wfp'] ) || 'notify' !== $_GET['ch_wfp'] ) {
		return;
	}

	if ( ! class_exists( 'Chornahora_Wayforpay' ) ) {
		status_header( 500 );
		exit;
	}

	Chornahora_Wayforpay::handle_notify();
}
add_action( 'init', 'chornahora_handle_wfp_notify', 0 );

function chornahora_theme_scripts() {
	wp_enqueue_style(
		'chornahora-fonts',
		chornahora_https_url( 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Roboto:ital,wght@0,400;0,700;1,400&family=Teko:wght@600;700&display=swap' ),
		array(),
		null
	);

	chornahora_enqueue_style_file( 'chornahora-theme', 'style.css', array( 'chornahora-fonts' ) );
	chornahora_enqueue_style_file( 'chornahora-main', 'assets/css/main.css', array( 'chornahora-theme' ) );

	$main_deps = array();

	if ( is_front_page() ) {
		wp_enqueue_style(
			'swiper',
			chornahora_https_url( 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css' ),
			array(),
			'11'
		);

		wp_enqueue_script(
			'swiper',
			chornahora_https_url( 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js' ),
			array(),
			'11',
			true
		);

		wp_enqueue_style(
			'fancybox',
			chornahora_https_url( 'https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0.36/dist/fancybox/fancybox.css' ),
			array(),
			'5.0.36'
		);

		wp_enqueue_script(
			'fancybox',
			chornahora_https_url( 'https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0.36/dist/fancybox/fancybox.umd.js' ),
			array(),
			'5.0.36',
			true
		);

		$main_deps[] = 'swiper';
		$main_deps[] = 'fancybox';
	}

	chornahora_enqueue_script_file( 'chornahora-main', 'assets/js/main.js', $main_deps, true );

	if ( is_page( 'checkout' ) && chornahora_enqueue_script_file( 'chornahora-checkout', 'assets/js/checkout.js', array(), true ) ) {
		wp_localize_script(
			'chornahora-checkout',
			'chCheckout',
			array(
				'ajaxUrl'     => chornahora_https_url( admin_url( 'admin-ajax.php' ) ),
				'nonce'       => wp_create_nonce( 'chornahora_checkout' ),
				'amount'      => CHORNAHORA_BOOK_PRICE,
				'thankYouUrl' => chornahora_thankyou_url(),
			)
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
		array(
			'title' => 'Оформлення замовлення',
			'slug'  => 'checkout',
		),
		array(
			'title' => 'Замовлення отримано',
			'slug'  => 'thank-you',
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
	update_option( 'chornahora_pages_version', CHORNAHORA_PAGES_VERSION );
}
add_action( 'after_switch_theme', 'chornahora_bootstrap_pages' );

function chornahora_maybe_bootstrap_pages() {
	if ( (int) get_option( 'chornahora_pages_version', 0 ) >= CHORNAHORA_PAGES_VERSION ) {
		return;
	}

	chornahora_bootstrap_pages();
}
add_action( 'init', 'chornahora_maybe_bootstrap_pages' );
