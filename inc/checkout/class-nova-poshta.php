<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chornahora_Nova_Poshta {
	const API_URL     = 'https://api.novaposhta.ua/v2.0/json/';
	const PAGE_LIMIT  = 500;
	const MAX_PAGES   = 10;
	const ALLOWED_CAT = array( 'branch', 'postomat' );

	public static function search_cities( $query ) {
		$query = trim( wp_strip_all_tags( (string) $query ) );

		if ( mb_strlen( $query ) < 2 ) {
			return array();
		}

		$cache_key = 'ch_np_c_' . md5( mb_strtolower( $query ) );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = self::request(
			'Address',
			'searchSettlements',
			array(
				'CityName' => $query,
				'Limit'    => '20',
			)
		);

		if ( null === $response ) {
			return array();
		}

		$cities = self::normalize_settlements( $response );
		set_transient( $cache_key, $cities, 15 * MINUTE_IN_SECONDS );

		return $cities;
	}

	public static function get_warehouses( $city_ref, $settlement_ref = '' ) {
		$city_ref       = sanitize_text_field( (string) $city_ref );
		$settlement_ref = sanitize_text_field( (string) $settlement_ref );

		if ( '' === $city_ref && '' === $settlement_ref ) {
			return array();
		}

		$cache_key = 'ch_np_w2_' . md5( $city_ref . '|' . $settlement_ref );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
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
		set_transient( $cache_key, $warehouses, HOUR_IN_SECONDS );

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
		$payload = wp_json_encode(
			array(
				'apiKey'           => CHORNAHORA_NP_API_KEY,
				'modelName'        => $model,
				'calledMethod'     => $method,
				'methodProperties' => $properties,
			)
		);

		if ( false === $payload ) {
			return null;
		}

		$remote = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => 12,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => $payload,
			)
		);

		if ( is_wp_error( $remote ) ) {
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $remote );

		if ( $code < 200 || $code >= 300 ) {
			return null;
		}

		$body = trim( (string) wp_remote_retrieve_body( $remote ) );

		if ( '' === $body ) {
			return null;
		}

		$decoded = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return null;
		}

		if ( empty( $decoded['success'] ) ) {
			return array();
		}

		return isset( $decoded['data'] ) && is_array( $decoded['data'] ) ? $decoded['data'] : array();
	}

	private static function normalize_settlements( $data ) {
		$addresses = array();

		if ( ! is_array( $data ) ) {
			return array();
		}

		if ( isset( $data[0] ) && is_array( $data[0] ) && isset( $data[0]['Addresses'] ) && is_array( $data[0]['Addresses'] ) ) {
			$addresses = $data[0]['Addresses'];
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

		if ( in_array( $category, self::ALLOWED_CAT, true ) ) {
			return true;
		}

		$description = isset( $row['Description'] ) ? (string) $row['Description'] : '';

		return (bool) preg_match( '/відділення|поштомат/iu', $description );
	}
}
