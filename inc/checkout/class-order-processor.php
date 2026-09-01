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
		$validated['created_at']   = chornahora_kyiv_datetime();
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

		$validated['post_id'] = (int) $post_id;
		$payload              = self::sheets_payload( $validated, $post_id );

		if ( 'wayforpay' !== $validated['payment'] ) {
			self::send_notification_email( $validated );
		}

		self::send_to_google_sheets( $payload );
		do_action( 'chornahora_order_created', $payload, $post_id );

		try {
			if ( class_exists( 'Chornahora_Leadcrm' ) ) {
				Chornahora_Leadcrm::send_order( $validated );
			}
		} catch ( Throwable $e ) {
			error_log( 'Chornahora LeadCRM: ' . $e->getMessage() );
		}

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
			$created = chornahora_kyiv_datetime();
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

		$product_name = (string) get_post_meta( $post_id, '_ch_product_name', true );

		if ( '' === $product_name ) {
			$product_name = 'Кривава агонія Югославії';
		}

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
			'product_name'    => $product_name,
		);
	}

	public static function update_status( $order_id, $status, $extra = array() ) {
		$order = self::find_by_order_id( $order_id );

		if ( ! $order ) {
			return false;
		}

		$previous        = isset( $order['status'] ) ? (string) $order['status'] : '';
		$order['status'] = sanitize_key( $status );
		update_post_meta( $order['post_id'], '_ch_status', $order['status'] );

		if ( is_array( $extra ) ) {
			foreach ( $extra as $key => $value ) {
				update_post_meta( $order['post_id'], '_ch_' . sanitize_key( $key ), sanitize_text_field( (string) $value ) );
			}
		}

		if ( 'paid' === $order['status'] && 'paid' !== $previous ) {
			self::send_notification_email( $order );
			self::send_to_google_sheets( self::sheets_payload( $order, $order['post_id'] ) );
		}

		return true;
	}

	private static function send_notification_email( $order ) {
		$post_id = isset( $order['post_id'] ) ? (int) $order['post_id'] : 0;

		if ( $post_id > 0 && (string) get_post_meta( $post_id, '_ch_email_sent', true ) === '1' ) {
			return;
		}

		if ( $post_id > 0 ) {
			update_post_meta( $post_id, '_ch_email_sent', '1' );
		}

		$html     = self::notification_email_html( $order );
		$order_id = isset( $order['order_id'] ) ? (string) $order['order_id'] : '';

		self::send_html_mail(
			CHORNAHORA_ORDER_EMAIL,
			'Нове замовлення ' . $order_id,
			$html
		);

		$client_email = isset( $order['email'] ) ? sanitize_email( (string) $order['email'] ) : '';

		if ( is_email( $client_email ) ) {
			self::send_html_mail(
				$client_email,
				'Ваше замовлення ' . $order_id,
				$html
			);
		}
	}

	private static function mail_from_address() {
		$from = defined( 'CHORNAHORA_MAIL_FROM' ) ? CHORNAHORA_MAIL_FROM : '';

		if ( is_email( $from ) ) {
			return $from;
		}

		$fallback = defined( 'CHORNAHORA_MAIL_FROM_FALLBACK' ) ? CHORNAHORA_MAIL_FROM_FALLBACK : 'wordpress@yugoslavia.chornahora.com.ua';

		return is_email( $fallback ) ? $fallback : 'wordpress@yugoslavia.chornahora.com.ua';
	}

	private static function send_html_mail( $to, $subject, $html ) {
		$from_email = self::mail_from_address();
		$from_name  = defined( 'CHORNAHORA_MAIL_FROM_NAME' ) ? CHORNAHORA_MAIL_FROM_NAME : 'Видавництво Чорна Гора';

		$configure = function ( $phpmailer ) use ( $from_email, $from_name ) {
			$phpmailer->CharSet  = 'UTF-8';
			$phpmailer->Encoding = 'base64';
			$phpmailer->isHTML( true );
			$phpmailer->setFrom( $from_email, $from_name, false );
		};

		add_action( 'phpmailer_init', $configure );
		wp_mail(
			$to,
			$subject,
			$html,
			array(
				'Content-Type: text/html; charset=UTF-8',
				sprintf( 'From: %s <%s>', $from_name, $from_email ),
			)
		);
		remove_action( 'phpmailer_init', $configure );
	}

	private static function notification_email_html( $order ) {
		$product = isset( $order['product_name'] ) && '' !== (string) $order['product_name']
			? (string) $order['product_name']
			: 'Кривава агонія Югославії';
		$notes   = isset( $order['notes'] ) && '' !== (string) $order['notes'] ? (string) $order['notes'] : '—';
		$rows    = array(
			'Номер замовлення'     => isset( $order['order_id'] ) ? (string) $order['order_id'] : '',
			'ПІБ'                  => isset( $order['full_name'] ) ? (string) $order['full_name'] : '',
			'Телефон'              => isset( $order['phone'] ) ? (string) $order['phone'] : '',
			'Місто'                => isset( $order['city'] ) ? (string) $order['city'] : '',
			'Відділення/поштомат' => isset( $order['warehouse_label'] ) ? (string) $order['warehouse_label'] : '',
			'Оплата'               => self::payment_label( isset( $order['payment'] ) ? $order['payment'] : '' ),
			'Сума'                 => ( isset( $order['amount'] ) ? (string) (int) $order['amount'] : (string) CHORNAHORA_BOOK_PRICE ) . ' UAH',
			'Товар'                => $product,
			'Коментар'             => $notes,
		);

		$cells = '';

		foreach ( $rows as $label => $value ) {
			$cells .= '<tr>'
				. '<td style="padding:8px 12px;border:1px solid #ddd;font-weight:600;">' . esc_html( $label ) . '</td>'
				. '<td style="padding:8px 12px;border:1px solid #ddd;">' . esc_html( $value ) . '</td>'
				. '</tr>';
		}

		return '<!DOCTYPE html><html lang="uk"><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;color:#222;">'
			. '<h1 style="font-size:18px;">Замовлення ' . esc_html( isset( $order['order_id'] ) ? (string) $order['order_id'] : '' ) . '</h1>'
			. '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;max-width:560px;">' . $cells . '</table>'
			. '</body></html>';
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

		if ( $blocking && is_wp_error( $response ) ) {
			error_log( 'Chornahora Sheets webhook failed: ' . $response->get_error_message() );
		}

		return $response;
	}

	private static function create_order_id() {
		global $wpdb;

		$option = 'ch_next_order_number';
		$start  = 100;

		$current = (int) get_option( $option, 0 );
		if ( $current < $start ) {
			update_option( $option, $start );
		}

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID(option_value + 1) WHERE option_name = %s",
				$option
			)
		);

		if ( ! $updated ) {
			add_option( $option, $start, '', 'no' );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID(option_value + 1) WHERE option_name = %s",
					$option
				)
			);
		}

		$next = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
		wp_cache_delete( $option, 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		$assigned = $next - 1;
		if ( $assigned < $start ) {
			$assigned = $start;
			update_option( $option, $start + 1 );
		}

		return (string) $assigned;
	}
}
