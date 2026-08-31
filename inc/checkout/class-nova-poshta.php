<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chornahora_Nova_Poshta {
	const API_URL     = 'https://api.novaposhta.ua/v2.0/json/';
	const PAGE_LIMIT  = 500;
	const MAX_PAGES   = 10;
	const ALLOWED_CAT = array( 'branch', 'postomat', 'store', 'postmachine' );

	public static function api_key() {
		$key = defined( 'CHORNAHORA_NP_API_KEY' ) ? CHORNAHORA_NP_API_KEY : '';

		return trim( (string) $key );
	}

	public static function search_cities( $query ) {
		$query = trim( wp_strip_all_tags( (string) $query ) );

		if ( function_exists( 'mb_strlen' ) ) {
			$too_short = mb_strlen( $query ) < 2;
		} else {
			$too_short = strlen( $query ) < 2;
		}

		if ( $too_short ) {
			return array();
		}

		$cache_key = 'ch_np_c3_' . md5( function_exists( 'mb_strtolower' ) ? mb_strtolower( $query ) : strtolower( $query ) );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		$props = array(
			'CityName' => $query,
			'Limit'    => '20',
			'Page'     => '1',
		);

		$response = self::request( 'Address', 'searchSettlements', $props );

		if ( null === $response || array() === $response ) {
			$response = self::request( 'AddressGeneral', 'searchSettlements', $props );
		}

		$cities = self::normalize_settlements( is_array( $response ) ? $response : array() );

		if ( array() === $cities ) {
			$cities = self::normalize_cities_catalog(
				self::request(
					'Address',
					'getCities',
					array(
						'FindByString' => $query,
						'Limit'        => '20',
					)
				)
			);
		}

		if ( ! empty( $cities ) ) {
			set_transient( $cache_key, $cities, 15 * MINUTE_IN_SECONDS );
		}

		return $cities;
	}

	public static function get_warehouses( $city_ref, $settlement_ref = '' ) {
		$city_ref       = sanitize_text_field( (string) $city_ref );
		$settlement_ref = sanitize_text_field( (string) $settlement_ref );

		if ( '' === $city_ref && '' === $settlement_ref ) {
			return array();
		}

		$cache_key = 'ch_np_w3_' . md5( $city_ref . '|' . $settlement_ref );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		$rows = self::fetch_warehouse_pages( $city_ref, $settlement_ref );

		if ( ( null === $rows || array() === $rows ) && '' !== $settlement_ref && '' !== $city_ref ) {
			$fallback = self::fetch_warehouse_pages( $city_ref, '' );
			if ( null !== $fallback ) {
				$rows = $fallback;
			}
		}

		if ( null === $rows ) {
			return array();
		}

		$warehouses = self::normalize_warehouses( $rows );

		if ( ! empty( $warehouses ) ) {
			set_transient( $cache_key, $warehouses, HOUR_IN_SECONDS );
		}

		return $warehouses;
	}

	private static function fetch_warehouse_pages( $city_ref, $settlement_ref ) {
		$all = array();

		for ( $page = 1; $page <= self::MAX_PAGES; $page++ ) {
			$props = array(
				'Limit' => (string) self::PAGE_LIMIT,
				'Page'  => (string) $page,
			);

			if ( '' !== $city_ref ) {
				$props['CityRef'] = $city_ref;
			}

			if ( '' !== $settlement_ref ) {
				$props['SettlementRef'] = $settlement_ref;
			}

			$chunk = self::request( 'AddressGeneral', 'getWarehouses', $props );

			if ( null === $chunk ) {
				$chunk = self::request( 'Address', 'getWarehouses', $props );
			}

			if ( null === $chunk ) {
				return empty( $all ) ? null : $all;
			}

			$all = array_merge( $all, $chunk );

			if ( count( $chunk ) < self::PAGE_LIMIT ) {
				break;
			}
		}

		return $all;
	}

	private static function request( $model, $method, $properties ) {
		$api_key = self::api_key();

		if ( '' === $api_key ) {
			self::log( $model, $method, 'missing API key' );
			return null;
		}

		$payload = wp_json_encode(
			array(
				'apiKey'           => $api_key,
				'modelName'        => $model,
				'calledMethod'     => $method,
				'methodProperties' => $properties,
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		if ( false === $payload ) {
			self::log( $model, $method, 'failed to encode JSON payload' );
			return null;
		}

		$remote = wp_remote_post(
			self::API_URL,
			array(
				'timeout'     => 30,
				'redirection' => 5,
				'headers'     => array(
					'Content-Type' => 'application/json; charset=utf-8',
					'Accept'       => 'application/json',
				),
				'body'        => $payload,
			)
		);

		if ( is_wp_error( $remote ) ) {
			self::log( $model, $method, 'wp_remote_post: ' . $remote->get_error_code() . ' ' . $remote->get_error_message() );
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $remote );

		if ( $code < 200 || $code >= 300 ) {
			self::log( $model, $method, 'HTTP ' . $code );
			return null;
		}

		$body = trim( (string) wp_remote_retrieve_body( $remote ) );

		if ( '' === $body ) {
			self::log( $model, $method, 'empty response body' );
			return null;
		}

		$decoded = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			self::log( $model, $method, 'invalid JSON: ' . json_last_error_msg() );
			return null;
		}

		if ( empty( $decoded['success'] ) ) {
			$errors = isset( $decoded['errors'] ) ? $decoded['errors'] : array();
			$codes  = isset( $decoded['errorCodes'] ) ? $decoded['errorCodes'] : array();
			self::log(
				$model,
				$method,
				'API success=false errors=' . wp_json_encode( $errors ) . ' codes=' . wp_json_encode( $codes )
			);
			return null;
		}

		return isset( $decoded['data'] ) && is_array( $decoded['data'] ) ? $decoded['data'] : array();
	}

	private static function log( $model, $method, $message ) {
		error_log( 'Chornahora NP ' . $model . '/' . $method . ': ' . $message );
	}

	private static function normalize_settlements( $data ) {
		$addresses = array();

		if ( ! is_array( $data ) ) {
			return array();
		}

		if ( isset( $data[0] ) && is_array( $data[0] ) && isset( $data[0]['Addresses'] ) && is_array( $data[0]['Addresses'] ) ) {
			$addresses = $data[0]['Addresses'];
		} elseif ( isset( $data['Addresses'] ) && is_array( $data['Addresses'] ) ) {
			$addresses = $data['Addresses'];
		} else {
			$addresses = $data;
		}

		$out = array();

		foreach ( $addresses as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$ref     = isset( $row['Ref'] ) ? (string) $row['Ref'] : '';
			$cityref = isset( $row['DeliveryCity'] ) ? (string) $row['DeliveryCity'] : $ref;
			$label   = isset( $row['Present'] ) ? (string) $row['Present'] : '';

			if ( '' === $label && isset( $row['MainDescription'] ) ) {
				$parts = array_filter(
					array(
						$row['MainDescription'],
						isset( $row['Area'] ) ? $row['Area'] : '',
						isset( $row['Region'] ) ? $row['Region'] : '',
					)
				);
				$label = implode( ', ', $parts );
			}

			if ( '' === $label || ( '' === $ref && '' === $cityref ) ) {
				continue;
			}

			$out[] = array(
				'label'          => $label,
				'settlement_ref' => $ref,
				'city_ref'       => $cityref,
			);
		}

		return $out;
	}

	private static function normalize_cities_catalog( $data ) {
		$out = array();

		if ( ! is_array( $data ) ) {
			return $out;
		}

		foreach ( $data as $row ) {
			if ( ! is_array( $row ) || empty( $row['Ref'] ) ) {
				continue;
			}

			$label = isset( $row['Description'] ) ? (string) $row['Description'] : '';

			if ( '' === $label ) {
				continue;
			}

			if ( ! empty( $row['AreaDescription'] ) ) {
				$label .= ', ' . $row['AreaDescription'];
			}

			$out[] = array(
				'label'          => $label,
				'settlement_ref' => '',
				'city_ref'       => (string) $row['Ref'],
			);
		}

		return $out;
	}

	private static function normalize_warehouses( $data ) {
		$out = array();

		if ( ! is_array( $data ) ) {
			return $out;
		}

		foreach ( $data as $row ) {
			if ( ! is_array( $row ) || empty( $row['Ref'] ) ) {
				continue;
			}

			$description = isset( $row['Description'] ) ? (string) $row['Description'] : '';

			if ( '' === $description || ! self::is_allowed_warehouse( $row ) ) {
				continue;
			}

			$category = isset( $row['CategoryOfWarehouse'] ) ? (string) $row['CategoryOfWarehouse'] : '';

			$out[] = array(
				'ref'      => (string) $row['Ref'],
				'label'    => $description,
				'category' => $category,
			);
		}

		return $out;
	}

	private static function is_allowed_warehouse( $row ) {
		if ( isset( $row['DenyToSelect'] ) && '1' === (string) $row['DenyToSelect'] ) {
			return false;
		}

		$status = isset( $row['WarehouseStatus'] ) ? strtolower( (string) $row['WarehouseStatus'] ) : 'working';

		if ( '' !== $status && 'working' !== $status ) {
			return false;
		}

		$category = isset( $row['CategoryOfWarehouse'] ) ? strtolower( (string) $row['CategoryOfWarehouse'] ) : '';

		if ( 'cargo' === $category ) {
			return false;
		}

		if ( '' === $category || in_array( $category, self::ALLOWED_CAT, true ) ) {
			return true;
		}

		$description = isset( $row['Description'] ) ? (string) $row['Description'] : '';

		return (bool) preg_match( '/відділення|поштомат/iu', $description );
	}
}
