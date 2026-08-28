<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chornahora_Wayforpay {
	const PURCHASE_URL = 'https://secure.wayforpay.com/pay';
	const WIDGET_URL   = 'https://secure.wayforpay.com/server/pay-widget.js';

	public static function merchant_domain() {
		if ( defined( 'CHORNAHORA_WFP_DOMAIN' ) && '' !== CHORNAHORA_WFP_DOMAIN ) {
			return CHORNAHORA_WFP_DOMAIN;
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		return $host ? (string) $host : 'yugoslavia.chornahora.com.ua';
	}

	public static function currency() {
		return defined( 'CHORNAHORA_WFP_CURRENCY' ) ? CHORNAHORA_WFP_CURRENCY : 'UAH';
	}

	public static function service_url() {
		$rest = rest_url( 'chornahora/v1/wayforpay/notify' );
		$path = wp_parse_url( $rest, PHP_URL_PATH );
		$query = wp_parse_url( $rest, PHP_URL_QUERY );

		if ( ! is_string( $path ) || '' === $path ) {
			$path = '/wp-json/chornahora/v1/wayforpay/notify';
		}

		$url = 'https://' . self::merchant_domain() . $path;

		if ( is_string( $query ) && '' !== $query ) {
			$url .= '?' . $query;
		}

		return $url;
	}

	public static function return_url( $order_id ) {
		$thank_you = chornahora_thankyou_url( $order_id );
		$path      = wp_parse_url( $thank_you, PHP_URL_PATH );
		$query     = wp_parse_url( $thank_you, PHP_URL_QUERY );

		if ( ! is_string( $path ) || '' === $path ) {
			$path = '/thank-you/';
		}

		$url = 'https://' . self::merchant_domain() . $path;

		if ( is_string( $query ) && '' !== $query ) {
			$url .= '?' . $query;
		}

		return $url;
	}

	public static function format_amount( $amount ) {
		return number_format( (float) $amount, 2, '.', '' );
	}

	public static function checkout_payload( $order ) {
		$amount   = self::format_amount( isset( $order['amount'] ) ? $order['amount'] : CHORNAHORA_BOOK_PRICE );
		$domain   = self::merchant_domain();
		$order_id = isset( $order['order_id'] ) ? (string) $order['order_id'] : '';
		$date     = (string) time();
		$name     = isset( $order['product_name'] ) ? (string) $order['product_name'] : 'Кривава агонія Югославії';
		$count    = '1';
		$price    = $amount;
		$currency = self::currency();
		$phone    = preg_replace( '/\D+/', '', isset( $order['phone'] ) ? (string) $order['phone'] : '' );

		$sign_parts = array(
			CHORNAHORA_WFP_MERCHANT,
			$domain,
			$order_id,
			$date,
			$amount,
			$currency,
			$name,
			$count,
			$price,
		);

		$signature = hash_hmac( 'md5', implode( ';', $sign_parts ), CHORNAHORA_WFP_SECRET );

		$client = array(
			'clientFirstName' => isset( $order['first_name'] ) ? (string) $order['first_name'] : '',
			'clientLastName'  => isset( $order['last_name'] ) ? (string) $order['last_name'] : '',
			'clientEmail'     => isset( $order['email'] ) ? (string) $order['email'] : '',
			'clientPhone'     => $phone,
		);

		$fields = array_merge(
			array(
				'merchantAccount'               => CHORNAHORA_WFP_MERCHANT,
				'merchantAuthType'              => 'SimpleSignature',
				'merchantDomainName'            => $domain,
				'merchantTransactionSecureType' => 'AUTO',
				'merchantSignature'             => $signature,
				'apiVersion'                    => '1',
				'orderReference'                => $order_id,
				'orderDate'                     => $date,
				'amount'                        => $amount,
				'currency'                      => $currency,
				'productName'                   => array( $name ),
				'productCount'                  => array( $count ),
				'productPrice'                  => array( $price ),
				'language'                      => 'UA',
				'orderTimeout'                  => '49000',
				'defaultPaymentSystem'          => 'card',
				'serviceUrl'                    => self::service_url(),
				'returnUrl'                     => self::return_url( $order_id ),
			),
			$client
		);

		$widget = array_merge(
			array(
				'merchantAccount'     => CHORNAHORA_WFP_MERCHANT,
				'merchantDomainName'  => $domain,
				'authorizationType'   => 'SimpleSignature',
				'merchantSignature'   => $signature,
				'orderReference'      => $order_id,
				'orderDate'           => $date,
				'amount'              => $amount,
				'currency'            => $currency,
				'productName'         => $name,
				'productCount'        => $count,
				'productPrice'        => $price,
				'language'            => 'UA',
				'serviceUrl'          => self::service_url(),
				'returnUrl'           => self::return_url( $order_id ),
			),
			$client
		);

		return array(
			'url'    => self::PURCHASE_URL,
			'fields' => $fields,
			'widget' => $widget,
		);
	}

	public static function handle_notify() {
		nocache_headers();

		$data = self::parse_notify_payload();

		if ( ! is_array( $data ) || empty( $data['orderReference'] ) ) {
			status_header( 400 );
			header( 'Content-Type: application/json; charset=utf-8' );
			echo wp_json_encode( array( 'error' => 'invalid_payload' ) );
			exit;
		}

		$order_ref = sanitize_text_field( (string) $data['orderReference'] );
		$received  = isset( $data['merchantSignature'] ) ? (string) $data['merchantSignature'] : '';

		if ( '' === $received || ! self::notify_signature_matches( $data, $received ) ) {
			status_header( 403 );
			header( 'Content-Type: application/json; charset=utf-8' );
			echo wp_json_encode( array( 'error' => 'invalid_signature' ) );
			exit;
		}

		$txn_status = isset( $data['transactionStatus'] ) ? (string) $data['transactionStatus'] : '';
		$extra      = array(
			'wfp_transaction_status' => $txn_status,
			'wfp_reason_code'        => isset( $data['reasonCode'] ) ? (string) $data['reasonCode'] : '',
			'wfp_auth_code'          => isset( $data['authCode'] ) ? (string) $data['authCode'] : '',
			'wfp_payment_system'     => isset( $data['paymentSystem'] ) ? (string) $data['paymentSystem'] : '',
		);

		if ( 'Approved' === $txn_status ) {
			Chornahora_Order_Processor::update_status( $order_ref, 'paid', $extra );
		} elseif ( in_array( $txn_status, array( 'Declined', 'Expired', 'Refunded', 'Voided' ), true ) ) {
			Chornahora_Order_Processor::update_status( $order_ref, sanitize_key( strtolower( $txn_status ) ), $extra );
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

	private static function parse_notify_payload() {
		$raw  = file_get_contents( 'php://input' );
		$data = json_decode( (string) $raw, true );

		if ( is_array( $data ) && ! empty( $data['orderReference'] ) ) {
			return $data;
		}

		if ( isset( $_POST['orderReference'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		return null;
	}

	private static function notify_signature_matches( $data, $received ) {
		$amounts = array();
		$reasons = array();

		if ( isset( $data['amount'] ) ) {
			$amounts[] = (string) $data['amount'];
			if ( is_numeric( $data['amount'] ) ) {
				$amounts[] = self::format_amount( $data['amount'] );
				$amounts[] = (string) (int) $data['amount'];
			}
		} else {
			$amounts[] = '';
		}

		if ( isset( $data['reasonCode'] ) ) {
			$reasons[] = (string) $data['reasonCode'];
		} else {
			$reasons[] = '';
		}

		foreach ( array_unique( $amounts ) as $amount ) {
			foreach ( array_unique( $reasons ) as $reason_code ) {
				$expected = self::notify_signature( $data, $amount, $reason_code );
				if ( '' !== $expected && hash_equals( $expected, $received ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function notify_signature( $data, $amount, $reason_code ) {
		$parts = array(
			isset( $data['merchantAccount'] ) ? (string) $data['merchantAccount'] : '',
			isset( $data['orderReference'] ) ? (string) $data['orderReference'] : '',
			(string) $amount,
			isset( $data['currency'] ) ? (string) $data['currency'] : '',
			isset( $data['authCode'] ) ? (string) $data['authCode'] : '',
			isset( $data['cardPan'] ) ? (string) $data['cardPan'] : '',
			isset( $data['transactionStatus'] ) ? (string) $data['transactionStatus'] : '',
			(string) $reason_code,
		);

		return hash_hmac( 'md5', implode( ';', $parts ), CHORNAHORA_WFP_SECRET );
	}
}
