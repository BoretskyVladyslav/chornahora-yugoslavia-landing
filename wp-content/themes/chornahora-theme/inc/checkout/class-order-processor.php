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

		$validated['amount']        = CHORNAHORA_BOOK_PRICE;
		$validated['currency']      = 'UAH';
		$validated['product_name']  = 'Кривава агонія Югославії';
		$validated['order_id']      = self::create_order_id();
		$validated['created_at']    = gmdate( 'c' );

		$name_parts               = preg_split( '/\s+/', $validated['full_name'], 2 );
		$validated['first_name']  = isset( $name_parts[0] ) ? $name_parts[0] : $validated['full_name'];
		$validated['last_name']   = isset( $name_parts[1] ) ? $name_parts[1] : '';

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
		self::sync_google_sheets( $payload );
		do_action( 'chornahora_order_created', $payload, $post_id );

		$result = array(
			'success'  => true,
			'order_id' => $validated['order_id'],
			'amount'   => $validated['amount'],
			'payment'  => $validated['payment'],
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

		$full_name = sanitize_text_field( isset( $input['full_name'] ) ? $input['full_name'] : '' );
		$email     = sanitize_email( isset( $input['email'] ) ? $input['email'] : '' );
		$phone     = self::normalize_phone( isset( $input['phone'] ) ? $input['phone'] : '' );
		$city      = sanitize_text_field( isset( $input['city'] ) ? $input['city'] : '' );
		$city_ref  = sanitize_text_field( isset( $input['city_ref'] ) ? $input['city_ref'] : '' );
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

	public static function sheets_payload( $order, $post_id ) {
		return array(
			'sheet_id'     => CHORNAHORA_SHEETS_ID,
			'timestamp'    => $order['created_at'],
			'order_id'     => $order['order_id'],
			'wp_post_id'   => (int) $post_id,
			'full_name'    => $order['full_name'],
			'phone'        => $order['phone'],
			'email'        => $order['email'],
			'city'         => $order['city'],
			'warehouse'    => $order['warehouse_label'],
			'notes'        => isset( $order['notes'] ) ? $order['notes'] : '',
			'payment'      => $order['payment'],
			'delivery'     => 'Нова пошта',
			'product'      => $order['product_name'],
			'amount'       => (int) $order['amount'],
			'currency'     => $order['currency'],
			'status'       => 'wayforpay' === $order['payment'] ? 'pending_payment' : 'cod_pending',
		);
	}

	private static function send_notification_email( $order ) {
		$payment_label = 'wayforpay' === $order['payment']
			? 'Оплата на сайті (WayForPay)'
			: 'Оплата під час отримання (НП післяплата)';

		$lines = array(
			'Нове замовлення: ' . $order['order_id'],
			'Книга: ' . $order['product_name'],
			'Сума: ' . $order['amount'] . ' грн',
			'ПІБ: ' . $order['full_name'],
			'Телефон: ' . $order['phone'],
			'Email: ' . $order['email'],
			'Місто: ' . $order['city'],
			'Відділення/поштомат: ' . $order['warehouse_label'],
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

	private static function sync_google_sheets( $payload ) {
		$url = apply_filters( 'chornahora_sheets_webhook', CHORNAHORA_SHEETS_WEBHOOK );

		if ( '' === $url ) {
			return;
		}

		wp_remote_post(
			$url,
			array(
				'timeout' => 8,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);
	}

	private static function create_order_id() {
		return 'CH-' . gmdate( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 6, false, false ) );
	}
}
