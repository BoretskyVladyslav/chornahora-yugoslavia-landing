<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chornahora_Order_Processor {
	public static function process( $input ) {
		$validated = self::validate( $input );

		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$validated['amount']       = CHORNAHORA_BOOK_PRICE;
		$validated['currency']     = 'UAH';
		$validated['product_name'] = 'Кривава агонія Югославії';
		$validated['order_id']     = self::create_order_id();
		$validated['created_at']   = wp_date( 'Y-m-d H:i:s' );
		$validated['status']       = 'wayforpay' === $validated['payment'] ? 'pending_payment' : 'complete';

		$name_parts              = preg_split( '/\s+/', $validated['full_name'], 2 );
		$validated['first_name'] = isset( $name_parts[0] ) ? $name_parts[0] : $validated['full_name'];
		$validated['last_name']  = isset( $name_parts[1] ) ? $name_parts[1] : '';

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'ch_order',
				'post_status' => 'private',
				'post_title'  => $validated['order_id'] . ' — ' . $validated['full_name'],
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error( 'save_failed', 'Не вдалося зберегти замовлення. Спробуйте ще раз.' );
		}

		foreach ( $validated as $key => $value ) {
			update_post_meta( $post_id, '_ch_' . $key, $value );
		}

		$payload = self::sheets_payload( $validated, $post_id );
		self::send_notification_email( $validated );
		self::send_to_google_sheets( $payload );
		do_action( 'chornahora_order_created', $payload, $post_id );

		$result = array(
			'success'       => true,
			'order_id'      => $validated['order_id'],
			'amount'        => $validated['amount'],
			'payment'       => $validated['payment'],
			'status'        => $validated['status'],
			'thank_you_url' => chornahora_thankyou_url( $validated['order_id'] ),
		);

		if ( 'wayforpay' === $validated['payment'] ) {
			$result['wayforpay'] = Chornahora_Wayforpay::checkout_payload( $validated );
		} else {
			$result['message'] = 'Замовлення прийнято. Ми зв’яжемося з вами для підтвердження післяплати.';
		}

		return $result;
	}

	public static function validate( $input ) {
		$errors = array();

		$full_name      = sanitize_text_field( isset( $input['full_name'] ) ? $input['full_name'] : '' );
		$email          = sanitize_email( isset( $input['email'] ) ? $input['email'] : '' );
		$phone          = self::normalize_phone( isset( $input['phone'] ) ? $input['phone'] : '' );
		$city           = sanitize_text_field( isset( $input['city'] ) ? $input['city'] : '' );
		$city_ref       = sanitize_text_field( isset( $input['city_ref'] ) ? $input['city_ref'] : '' );
		$settlement_ref = sanitize_text_field( isset( $input['settlement_ref'] ) ? $input['settlement_ref'] : '' );
		$warehouse_ref  = sanitize_text_field( isset( $input['warehouse_ref'] ) ? $input['warehouse_ref'] : '' );
		$warehouse      = sanitize_text_field( isset( $input['warehouse_label'] ) ? $input['warehouse_label'] : '' );
		$payment        = sanitize_key( isset( $input['payment'] ) ? $input['payment'] : '' );
		$notes          = sanitize_textarea_field( isset( $input['notes'] ) ? $input['notes'] : '' );

		if ( mb_strlen( $full_name ) < 2 ) {
			$errors['full_name'] = 'Вкажіть прізвище та ім’я.';
		}

		if ( '' === $phone ) {
			$errors['phone'] = 'Вкажіть телефон у форматі +380 (XX) XXX-XX-XX.';
		}

		if ( ! is_email( $email ) ) {
			$errors['email'] = 'Вкажіть коректний email.';
		}

		if ( '' === $city || ( '' === $city_ref && '' === $settlement_ref ) ) {
			$errors['city'] = 'Оберіть місто зі списку Нової пошти.';
		}

		if ( '' === $warehouse_ref ) {
			$errors['warehouse'] = 'Оберіть відділення або поштомат.';
		}

		if ( ! in_array( $payment, array( 'wayforpay', 'cod' ), true ) ) {
			$errors['payment'] = 'Оберіть спосіб оплати.';
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation', 'Перевірте поля форми.', array( 'fields' => $errors ) );
		}

		return array(
			'full_name'       => $full_name,
			'email'           => $email,
			'phone'           => $phone,
			'city'            => $city,
			'city_ref'        => $city_ref,
			'settlement_ref'  => $settlement_ref,
			'warehouse_ref'   => $warehouse_ref,
			'warehouse_label' => $warehouse,
			'payment'         => $payment,
			'notes'           => $notes,
			'delivery'        => 'nova_poshta',
		);
	}

	public static function normalize_phone( $raw ) {
		$digits = preg_replace( '/\D+/', '', (string) $raw );

		if ( strlen( $digits ) === 12 && 0 === strpos( $digits, '380' ) ) {
			return '+' . $digits;
		}

		if ( strlen( $digits ) === 10 && 0 === strpos( $digits, '0' ) ) {
			return '+380' . substr( $digits, 1 );
		}

		if ( strlen( $digits ) === 9 ) {
			return '+380' . $digits;
		}

		return '';
	}

	public static function payment_label( $payment ) {
		return 'wayforpay' === $payment ? 'Оплатити на сайті' : 'Готівка при отриманні';
	}

	public static function sheets_payment_method( $payment ) {
		return 'wayforpay' === $payment ? 'WayForPay' : 'COD';
	}

	public static function sheets_status_label( $status, $payment = '' ) {
		if ( 'paid' === $status ) {
			return 'оплачено';
		}

		if ( 'pending_payment' === $status || 'wayforpay' === $payment ) {
			return 'очікує оплати';
		}

		return 'нове';
	}

	public static function sheets_payload( $order, $post_id = 0 ) {
		$created = isset( $order['created_at'] ) ? (string) $order['created_at'] : '';

		if ( '' === $created ) {
			$created = wp_date( 'Y-m-d H:i:s' );
		}

		$comment = '';
		if ( isset( $order['notes'] ) ) {
			$comment = (string) $order['notes'];
		} elseif ( isset( $order['comment'] ) ) {
			$comment = (string) $order['comment'];
		}

		return array(
			'datetime'       => $created,
			'order_id'       => (string) $order['order_id'],
			'client_name'    => (string) $order['full_name'],
			'phone'          => (string) $order['phone'],
			'email'          => (string) $order['email'],
			'city'           => (string) $order['city'],
			'warehouse'      => isset( $order['warehouse_label'] ) ? (string) $order['warehouse_label'] : '',
			'payment_method' => self::sheets_payment_method( $order['payment'] ),
			'amount'         => (int) ( isset( $order['amount'] ) ? $order['amount'] : CHORNAHORA_BOOK_PRICE ),
			'status'         => self::sheets_status_label(
				isset( $order['status'] ) ? $order['status'] : '',
				isset( $order['payment'] ) ? $order['payment'] : ''
			),
			'comment'        => $comment,
		);
	}

	public static function find_by_order_id( $order_id ) {
		$order_id = sanitize_text_field( (string) $order_id );

		if ( '' === $order_id ) {
			return null;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'ch_order',
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'meta_key'       => '_ch_order_id',
				'meta_value'     => $order_id,
			)
		);

		if ( empty( $posts ) ) {
			return null;
		}

		$post_id = (int) $posts[0]->ID;

		return array(
			'post_id'         => $post_id,
			'order_id'        => (string) get_post_meta( $post_id, '_ch_order_id', true ),
			'full_name'       => (string) get_post_meta( $post_id, '_ch_full_name', true ),
			'phone'           => (string) get_post_meta( $post_id, '_ch_phone', true ),
			'email'           => (string) get_post_meta( $post_id, '_ch_email', true ),
			'city'            => (string) get_post_meta( $post_id, '_ch_city', true ),
			'city_ref'        => (string) get_post_meta( $post_id, '_ch_city_ref', true ),
			'warehouse_label' => (string) get_post_meta( $post_id, '_ch_warehouse_label', true ),
			'warehouse_ref'   => (string) get_post_meta( $post_id, '_ch_warehouse_ref', true ),
			'payment'         => (string) get_post_meta( $post_id, '_ch_payment', true ),
			'amount'          => (int) get_post_meta( $post_id, '_ch_amount', true ),
			'status'          => (string) get_post_meta( $post_id, '_ch_status', true ),
			'notes'           => (string) get_post_meta( $post_id, '_ch_notes', true ),
			'created_at'      => (string) get_post_meta( $post_id, '_ch_created_at', true ),
		);
	}

	public static function update_status( $order_id, $status ) {
		$order = self::find_by_order_id( $order_id );

		if ( ! $order ) {
			return false;
		}

		$order['status'] = sanitize_key( $status );
		update_post_meta( $order['post_id'], '_ch_status', $order['status'] );

		if ( 'paid' === $order['status'] ) {
			self::send_to_google_sheets( self::sheets_payload( $order, $order['post_id'] ) );
		}

		return true;
	}

	private static function send_notification_email( $order ) {
		$payment_label = self::payment_label( $order['payment'] );
		$lines         = array(
			'Нове замовлення: ' . $order['order_id'],
			'Статус: ' . $order['status'],
			'Книга: ' . $order['product_name'],
			'Сума: ' . $order['amount'] . ' UAH',
			'ПІБ: ' . $order['full_name'],
			'Телефон: ' . $order['phone'],
			'Email: ' . $order['email'],
			'Місто: ' . $order['city'] . ' (ref: ' . $order['city_ref'] . ')',
			'Відділення/поштомат: ' . $order['warehouse_label'] . ' (ref: ' . $order['warehouse_ref'] . ')',
			'Оплата: ' . $payment_label,
			'Коментар: ' . ( isset( $order['notes'] ) && '' !== $order['notes'] ? $order['notes'] : '—' ),
		);

		wp_mail(
			CHORNAHORA_ORDER_EMAIL,
			'Нове замовлення ' . $order['order_id'],
			implode( "\n", $lines ),
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}

	public static function send_to_google_sheets( $payload, $blocking = false ) {
		$url = apply_filters( 'CHORNAHORA_SHEETS_WEBHOOK_URL', CHORNAHORA_SHEETS_WEBHOOK_URL );
		$url = apply_filters( 'chornahora_sheets_webhook', $url );
		$url = apply_filters( 'chornahora_sheets_webhook_url', $url );
		$url = esc_url_raw( (string) $url );

		if ( '' === $url ) {
			$error = new WP_Error( 'sheets_url_missing', 'CHORNAHORA_SHEETS_WEBHOOK_URL is not defined.' );
			error_log( 'Chornahora Sheets webhook failed: ' . $error->get_error_message() );
			return $error;
		}

		$body = wp_json_encode( $payload );

		if ( false === $body ) {
			$error = new WP_Error( 'sheets_encode_failed', 'Failed to encode Google Sheets payload.' );
			error_log( 'Chornahora Sheets: failed to encode payload for order ' . ( isset( $payload['order_id'] ) ? $payload['order_id'] : '' ) );
			return $error;
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout'     => $blocking ? 20 : 2,
				'blocking'    => (bool) $blocking,
				'redirection' => 0,
				'headers'     => array(
					'Content-Type' => 'text/plain; charset=utf-8',
				),
				'body'        => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Chornahora Sheets webhook failed: ' . $response->get_error_message() );
		}

		return $response;
	}

	private static function create_order_id() {
		return 'CH-' . gmdate( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 6, false, false ) );
	}
}
