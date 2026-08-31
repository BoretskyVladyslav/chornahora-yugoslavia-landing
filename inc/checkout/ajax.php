<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function chornahora_ajax_verify() {
	if ( ! check_ajax_referer( 'chornahora_checkout', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Сесія застаріла. Оновіть сторінку.' ), 403 );
	}
}

function chornahora_ajax_post_text( $key ) {
	if ( ! isset( $_POST[ $key ] ) ) {
		return '';
	}

	return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
}

function chornahora_ajax_post_query( $key ) {
	if ( ! isset( $_POST[ $key ] ) ) {
		return '';
	}

	$value = wp_unslash( $_POST[ $key ] );
	$value = wp_check_invalid_utf8( (string) $value );

	return trim( wp_strip_all_tags( $value ) );
}

function ch_search_cities() {
	chornahora_ajax_verify();

	if ( ! class_exists( 'Chornahora_Nova_Poshta' ) ) {
		wp_send_json_error( array( 'message' => 'Nova Poshta недоступна.', 'cities' => array() ), 500 );
	}

	$cities = Chornahora_Nova_Poshta::search_cities( chornahora_ajax_post_query( 'query' ) );

	wp_send_json_success( array( 'cities' => is_array( $cities ) ? $cities : array() ) );
}

function ch_get_warehouses() {
	chornahora_ajax_verify();

	if ( ! class_exists( 'Chornahora_Nova_Poshta' ) ) {
		wp_send_json_error( array( 'message' => 'Nova Poshta недоступна.', 'warehouses' => array() ), 500 );
	}

	$warehouses = Chornahora_Nova_Poshta::get_warehouses(
		chornahora_ajax_post_text( 'city_ref' ),
		chornahora_ajax_post_text( 'settlement_ref' )
	);

	wp_send_json_success( array( 'warehouses' => is_array( $warehouses ) ? $warehouses : array() ) );
}

function ch_process_order() {
	chornahora_ajax_verify();

	if ( ! class_exists( 'Chornahora_Order_Processor' ) ) {
		wp_send_json_error( array( 'message' => 'Сервіс замовлень недоступний. Спробуйте ще раз.' ), 500 );
	}

	$input = array(
		'full_name'       => chornahora_ajax_post_text( 'full_name' ),
		'phone'           => chornahora_ajax_post_text( 'phone' ),
		'email'           => chornahora_ajax_post_text( 'email' ),
		'city'            => chornahora_ajax_post_text( 'city' ),
		'city_ref'        => chornahora_ajax_post_text( 'city_ref' ),
		'settlement_ref'  => chornahora_ajax_post_text( 'settlement_ref' ),
		'warehouse_ref'   => chornahora_ajax_post_text( 'warehouse_ref' ),
		'warehouse_label' => chornahora_ajax_post_text( 'warehouse_label' ),
		'payment'         => chornahora_ajax_post_text( 'payment' ),
		'notes'           => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
	);

	$result = Chornahora_Order_Processor::process( $input );

	if ( is_wp_error( $result ) ) {
		$data = $result->get_error_data();
		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
				'fields'  => is_array( $data ) && isset( $data['fields'] ) ? $data['fields'] : array(),
			),
			400
		);
	}

	wp_send_json_success( $result );
}
