<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chornahora_Leadcrm {
	const API_URL = 'https://app.leadcrm.top/api/v1/orders';

	public static function send_order( $order ) {
		try {
			self::dispatch( $order );
		} catch ( Throwable $e ) {
			error_log( 'Chornahora LeadCRM exception: ' . $e->getMessage() );
		}
	}

	private static function dispatch( $order ) {
		if ( ! is_array( $order ) ) {
			error_log( 'Chornahora LeadCRM: invalid order payload.' );
			return;
		}

		$post_id = isset( $order['post_id'] ) ? (int) $order['post_id'] : 0;

		if ( $post_id > 0 && '1' === (string) get_post_meta( $post_id, '_ch_leadcrm_sent', true ) ) {
			return;
		}

		$token = self::token();

		if ( '' === $token ) {
			error_log( 'Chornahora LeadCRM: missing API token.' );
			return;
		}

		$payload = self::build_payload( $order );
		$body    = json_encode( $payload, JSON_UNESCAPED_UNICODE );

		if ( false === $body ) {
			error_log( 'Chornahora LeadCRM: failed to encode JSON for order ' . self::order_number( $order ) );
			return;
		}

		$response = wp_remote_post(
			self::endpoint(),
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $token,
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log(
				'Chornahora LeadCRM: ' . $response->get_error_code() . ' ' . $response->get_error_message()
				. ' order ' . self::order_number( $order )
			);
			return;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code && 201 !== $code ) {
			error_log(
				'Chornahora LeadCRM HTTP ' . $code
				. ' order ' . self::order_number( $order )
				. ': ' . wp_remote_retrieve_body( $response )
			);
			return;
		}

		if ( $post_id > 0 ) {
			update_post_meta( $post_id, '_ch_leadcrm_sent', '1' );
		}
	}

	private static function build_payload( $order ) {
		$order_number = self::order_number( $order );
		$amount       = (float) ( isset( $order['amount'] ) ? $order['amount'] : CHORNAHORA_BOOK_PRICE );
		$quantity     = isset( $order['quantity'] ) ? max( 1, (int) $order['quantity'] ) : 1;
		$notes        = isset( $order['notes'] ) ? (string) $order['notes'] : '';
		$city_ref     = isset( $order['city_ref'] ) ? (string) $order['city_ref'] : '';
		$payment      = isset( $order['payment'] ) ? (string) $order['payment'] : '';
		$price        = (float) CHORNAHORA_BOOK_PRICE;

		if ( '' === $city_ref && isset( $order['settlement_ref'] ) ) {
			$city_ref = (string) $order['settlement_ref'];
		}

		return array(
			'number'                => $order_number,
			'number_client'         => $order_number,
			'customer_fullname'     => isset( $order['full_name'] ) ? (string) $order['full_name'] : '',
			'customer_phonenumber1' => isset( $order['phone'] ) ? (string) $order['phone'] : '',
			'recipient_type'        => 'PrivatePerson',
			'delivery_company'      => 'np',
			'service_type_np'       => 'WarehouseWarehouse',
			'city'                  => $city_ref,
			'city_description'      => isset( $order['city'] ) ? (string) $order['city'] : '',
			'warehouse'             => isset( $order['warehouse_ref'] ) ? (string) $order['warehouse_ref'] : '',
			'warehouse_description' => isset( $order['warehouse_label'] ) ? (string) $order['warehouse_label'] : '',
			'weight_total'          => 1.0,
			'seats_total'           => 1,
			'cost_total'            => $amount,
			'backward_summ'         => 'wayforpay' === $payment ? 0.0 : $amount,
			'status'                => 1,
			'about'                 => $notes,
			'products'              => array(
				array(
					'name'     => 'Книга Югославія',
					'price'    => $price,
					'quantity' => $quantity,
				),
			),
		);
	}

	private static function order_number( $order ) {
		if ( isset( $order['order_id'] ) && '' !== (string) $order['order_id'] ) {
			return (string) $order['order_id'];
		}

		return (string) time();
	}

	private static function endpoint() {
		if ( defined( 'CHORNAHORA_LEADCRM_URL' ) && '' !== CHORNAHORA_LEADCRM_URL ) {
			return CHORNAHORA_LEADCRM_URL;
		}

		return self::API_URL;
	}

	private static function token() {
		if ( defined( 'CHORNAHORA_LEADCRM_TOKEN' ) ) {
			return trim( (string) CHORNAHORA_LEADCRM_TOKEN );
		}

		return '';
	}
}
