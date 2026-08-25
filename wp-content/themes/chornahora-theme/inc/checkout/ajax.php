<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function chornahora_ajax_verify() {
	if ( ! check_ajax_referer( 'chornahora_checkout', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Сесія застаріла. Оновіть сторінку.' ), 403 );
	}
}

function ch_search_cities() {
	chornahora_ajax_verify();

	$query  = isset( $_POST['query'] ) ? wp_unslash( $_POST['query'] ) : '';
	$cities = Chornahora_Nova_Poshta::search_cities( $query );

	wp_send_json_success( array( 'cities' => $cities ) );
}

function ch_get_warehouses() {
	chornahora_ajax_verify();

	$city_ref       = isset( $_POST['city_ref'] ) ? wp_unslash( $_POST['city_ref'] ) : '';
	$settlement_ref = isset( $_POST['settlement_ref'] ) ? wp_unslash( $_POST['settlement_ref'] ) : '';
	$warehouses     = Chornahora_Nova_Poshta::get_warehouses( $city_ref, $settlement_ref );

	wp_send_json_success( array( 'warehouses' => $warehouses ) );
}

function ch_process_order() {
	chornahora_ajax_verify();

	$input = array(
		'full_name'        => isset( $_POST['full_name'] ) ? wp_unslash( $_POST['full_name'] ) : '',
		'phone'            => isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '',
		'email'            => isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '',
		'city'             => isset( $_POST['city'] ) ? wp_unslash( $_POST['city'] ) : '',
		'city_ref'         => isset( $_POST['city_ref'] ) ? wp_unslash( $_POST['city_ref'] ) : '',
		'settlement_ref'   => isset( $_POST['settlement_ref'] ) ? wp_unslash( $_POST['settlement_ref'] ) : '',
		'warehouse_ref'    => isset( $_POST['warehouse_ref'] ) ? wp_unslash( $_POST['warehouse_ref'] ) : '',
		'warehouse_label'  => isset( $_POST['warehouse_label'] ) ? wp_unslash( $_POST['warehouse_label'] ) : '',
		'payment'          => isset( $_POST['payment'] ) ? wp_unslash( $_POST['payment'] ) : '',
		'notes'            => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
	);

	$result = Chornahora_Order_Processor::process( $input );

	if ( is_wp_error( $result ) ) {
		$data = $result->get_error_data();
		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
				'fields'  => isset( $data['fields'] ) ? $data['fields'] : array(),
			),
			400
		);
	}

	wp_send_json_success( $result );
}
