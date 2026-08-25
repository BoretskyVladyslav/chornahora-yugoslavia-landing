<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chornahora_Nova_Poshta {
	const API_URL = 'https://api.novaposhta.ua/v2.0/json/';

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

		$cache_key = 'ch_np_w_' . md5( $city_ref . '|' . $settlement_ref );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$props = array(
			'Limit' => '500',
			'Page'  => '1',
		);

		if ( '' !== $city_ref ) {
			$props['CityRef'] = $city_ref;
		}

		if ( '' !== $settlement_ref ) {
			$props['SettlementRef'] = $settlement_ref;
		}

		$response   = self::request( 'AddressGeneral', 'getWarehouses', $props );
		$warehouses = self::normalize_warehouses( $response );
		set_transient( $cache_key, $warehouses, HOUR_IN_SECONDS );

		return $warehouses;
	}

	private static function request( $model, $method, $properties ) {
		$body = array(
			'apiKey'           => CHORNAHORA_NP_API_KEY,
			'modelName'        => $model,
			'calledMethod'     => $method,
			'methodProperties' => $properties,
		);

		$remote = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => 12,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $remote ) ) {
			return array();
		}

		$decoded = json_decode( wp_remote_retrieve_body( $remote ), true );

		if ( ! is_array( $decoded ) || empty( $decoded['success'] ) ) {
			return array();
		}

		return isset( $decoded['data'] ) && is_array( $decoded['data'] ) ? $decoded['data'] : array();
	}

	private static function normalize_settlements( $data ) {
		$addresses = array();

		if ( isset( $data[0]['Addresses'] ) && is_array( $data[0]['Addresses'] ) ) {
			$addresses = $data[0]['Addresses'];
		} elseif ( is_array( $data ) ) {
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

		foreach ( $data as $row ) {
			if ( ! is_array( $row ) || empty( $row['Ref'] ) ) {
				continue;
			}

			$description = isset( $row['Description'] ) ? (string) $row['Description'] : '';
			$category    = isset( $row['CategoryOfWarehouse'] ) ? (string) $row['CategoryOfWarehouse'] : '';

			if ( '' === $description ) {
				continue;
			}

			$out[] = array(
				'ref'      => (string) $row['Ref'],
				'label'    => $description,
				'category' => $category,
			);
		}

		return $out;
	}
}
