<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chornahora_Wayforpay {
	const PURCHASE_URL = 'https://secure.wayforpay.com/pay';

	public static function checkout_payload( $order ) {
		$amount   = (string) (int) ( isset( $order['amount'] ) ? $order['amount'] : CHORNAHORA_BOOK_PRICE );
		$domain   = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$order_id = isset( $order['order_id'] ) ? (string) $order['order_id'] : '';
		$date     = (string) time();
		$name     = isset( $order['product_name'] ) ? (string) $order['product_name'] : 'Кривава агонія Югославії';
		$count    = '1';
		$price    = $amount;

		$sign_parts = array(
			CHORNAHORA_WFP_MERCHANT,
			$domain,
			$order_id,
			$date,
			$amount,
			'UAH',
			$name,
			$count,
			$price,
		);

		$signature = hash_hmac( 'md5', implode( ';', $sign_parts ), CHORNAHORA_WFP_SECRET );

		$fields = array(
			'merchantAccount'    => CHORNAHORA_WFP_MERCHANT,
			'merchantAuthType'   => 'SimpleSignature',
			'merchantDomainName' => $domain,
			'merchantSignature'  => $signature,
			'orderReference'     => $order_id,
			'orderDate'          => $date,
			'amount'             => $amount,
			'currency'           => 'UAH',
			'productName'        => array( $name ),
			'productCount'       => array( $count ),
			'productPrice'       => array( $price ),
			'clientFirstName'    => isset( $order['first_name'] ) ? $order['first_name'] : '',
			'clientLastName'     => isset( $order['last_name'] ) ? $order['last_name'] : '',
			'clientEmail'        => isset( $order['email'] ) ? $order['email'] : '',
			'clientPhone'        => preg_replace( '/\D+/', '', isset( $order['phone'] ) ? $order['phone'] : '' ),
			'language'           => 'UA',
			'serviceUrl'         => home_url( '/?ch_wfp=notify' ),
			'returnUrl'          => chornahora_thankyou_url( $order_id ),
		);

		return array(
			'url'    => self::PURCHASE_URL,
			'fields' => $fields,
		);
	}

	public static function handle_notify() {
		$raw  = file_get_contents( 'php://input' );
		$data = json_decode( (string) $raw, true );

		if ( ! is_array( $data ) || empty( $data['orderReference'] ) ) {
			status_header( 400 );
			echo wp_json_encode( array( 'error' => 'invalid_payload' ) );
			exit;
		}

		$order_ref = sanitize_text_field( (string) $data['orderReference'] );
		$received  = isset( $data['merchantSignature'] ) ? (string) $data['merchantSignature'] : '';
		$expected  = self::notify_signature( $data );

		if ( '' === $expected || ! hash_equals( $expected, $received ) ) {
			status_header( 403 );
			echo wp_json_encode( array( 'error' => 'invalid_signature' ) );
			exit;
		}

		$txn_status = isset( $data['transactionStatus'] ) ? (string) $data['transactionStatus'] : '';

		if ( 'Approved' === $txn_status ) {
			Chornahora_Order_Processor::update_status( $order_ref, 'paid' );
		} elseif ( in_array( $txn_status, array( 'Declined', 'Expired', 'Refunded', 'Voided' ), true ) ) {
			Chornahora_Order_Processor::update_status( $order_ref, sanitize_key( strtolower( $txn_status ) ) );
		}

		$time      = time();
		$reply_sig = hash_hmac( 'md5', $order_ref . ';accept;' . $time, CHORNAHORA_WFP_SECRET );

		status_header( 200 );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode(
			array(
				'orderReference' => $order_ref,
				'status'         => 'accept',
				'time'           => $time,
				'signature'      => $reply_sig,
			)
		);
		exit;
	}

	private static function notify_signature( $data ) {
		$parts = array(
			isset( $data['merchantAccount'] ) ? $data['merchantAccount'] : '',
			isset( $data['orderReference'] ) ? $data['orderReference'] : '',
			isset( $data['amount'] ) ? $data['amount'] : '',
			isset( $data['currency'] ) ? $data['currency'] : '',
			isset( $data['authCode'] ) ? $data['authCode'] : '',
			isset( $data['cardPan'] ) ? $data['cardPan'] : '',
			isset( $data['transactionStatus'] ) ? $data['transactionStatus'] : '',
			isset( $data['reasonCode'] ) ? $data['reasonCode'] : '',
		);

		return hash_hmac( 'md5', implode( ';', $parts ), CHORNAHORA_WFP_SECRET );
	}
}
