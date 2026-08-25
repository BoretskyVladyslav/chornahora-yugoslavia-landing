<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chornahora_Wayforpay {
	const PURCHASE_URL = 'https://secure.wayforpay.com/pay';

	public static function checkout_payload( $order ) {
		$amount   = (string) (int) $order['amount'];
		$domain   = wp_parse_url( home_url(), PHP_URL_HOST );
		$order_id = (string) $order['order_id'];
		$date     = (string) time();
		$name     = (string) $order['product_name'];
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
			'clientFirstName'    => $order['first_name'],
			'clientLastName'     => $order['last_name'],
			'clientEmail'        => $order['email'],
			'clientPhone'        => preg_replace( '/\D+/', '', $order['phone'] ),
			'language'           => 'UA',
			'serviceUrl'         => home_url( '/?ch_wfp=notify' ),
			'returnUrl'          => home_url( '/#order' ),
		);

		return array(
			'url'    => self::PURCHASE_URL,
			'fields' => $fields,
		);
	}
}
