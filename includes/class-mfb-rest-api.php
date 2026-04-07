<?php
/**
 * MFB REST API for Dashboard Synchronization
 *
 * Provides REST API endpoints for synchronizing orders and shipments
 * with the MyFlyingBox dashboard. Uses JWT authentication with a shared secret configured in plugin settings.
 *
 * Endpoints:
 *   GET  /wp-json/myflyingbox/v1/shop
 *   GET  /wp-json/myflyingbox/v1/orders?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
 *   GET  /wp-json/myflyingbox/v1/order/<id>
 *   GET  /wp-json/myflyingbox/v1/shipment?api_order_id=<uuid>
 *   POST /wp-json/myflyingbox/v1/shipment
 *   GET  /wp-json/myflyingbox/v1/settings/custom_api_server_url
 *   POST /wp-json/myflyingbox/v1/settings/custom_api_server_url
 *   GET  /wp-json/myflyingbox/v1/settings/custom_dashboard_server_url
 *   POST /wp-json/myflyingbox/v1/settings/custom_dashboard_server_url
 *   GET  /wp-json/myflyingbox/v1/settings/webhooks_signature_key
 *   POST /wp-json/myflyingbox/v1/settings/webhooks_signature_key
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MFB_Rest_Api {

	const NAMESPACE = 'myflyingbox/v1';

	/**
	 * Register all REST routes.
	 */
	public static function register_routes() {

		// GET /shop
		register_rest_route( self::NAMESPACE, '/shop', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'get_shop' ],
			'permission_callback' => [ __CLASS__, 'authenticate_jwt' ],
		]);

		// GET /orders
		register_rest_route( self::NAMESPACE, '/orders', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'get_orders' ],
			'permission_callback' => [ __CLASS__, 'authenticate_jwt' ],
			'args'                => [
				'start_date' => [
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
				'end_date' => [
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		]);

		// GET /order/<id>
		register_rest_route( self::NAMESPACE, '/order/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'get_order' ],
			'permission_callback' => [ __CLASS__, 'authenticate_jwt' ],
			'args'                => [
				'id' => [
					'required'          => true,
					'validate_callback' => function( $param ) {
						return is_numeric( $param );
					},
				],
			],
		]);

		// GET & POST /shipment
		register_rest_route( self::NAMESPACE, '/shipment', [
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_shipment' ],
				'permission_callback' => [ __CLASS__, 'authenticate_jwt' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'create_or_update_shipment' ],
				'permission_callback' => [ __CLASS__, 'authenticate_jwt' ],
			],
		]);

		// GET & POST /settings/custom_api_server_url
		register_rest_route( self::NAMESPACE, '/settings/custom_api_server_url', [
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_custom_api_server_url' ],
				'permission_callback' => [ __CLASS__, 'authenticate_jwt' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'set_custom_api_server_url' ],
				'permission_callback' => [ __CLASS__, 'authenticate_jwt' ],
			],
		]);

		// GET & POST /settings/custom_dashboard_server_url
		register_rest_route( self::NAMESPACE, '/settings/custom_dashboard_server_url', [
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_custom_dashboard_server_url' ],
				'permission_callback' => [ __CLASS__, 'authenticate_jwt' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'set_custom_dashboard_server_url' ],
				'permission_callback' => [ __CLASS__, 'authenticate_jwt' ],
			],
		]);

		// GET & POST /settings/webhooks_signature_key
		register_rest_route( self::NAMESPACE, '/settings/webhooks_signature_key', [
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_webhooks_signature_key' ],
				'permission_callback' => [ __CLASS__, 'authenticate_jwt' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'set_webhooks_signature_key' ],
				'permission_callback' => [ __CLASS__, 'authenticate_jwt' ],
			],
		]);
	}

	// ─── Authentication ──────────────────────────────────────────────

	/**
	 * JWT authentication callback.
	 *
	 * Validates Bearer JWT token using the shared secret configured in plugin settings.
	 * Checks: sync enabled, secret configured, token signature, expiration, audience.
	 *
	 * @param  WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public static function authenticate_jwt( WP_REST_Request $request ) {
		$sync_behavior = get_option( 'mfb_dashboard_sync_behavior', 'never' );
		if ( $sync_behavior === 'never' ) {
			return new WP_Error( 'api_disabled', __( 'Dashboard sync API is disabled. Enable it in plugin settings.', 'my-flying-box' ), [ 'status' => 403 ] );
		}

		$jwt_secret = get_option( 'mfb_api_jwt_shared_secret', '' );
		if ( empty( $jwt_secret ) ) {
			return new WP_Error( 'api_not_configured', __( 'API authentication key (JWT) is not configured.', 'my-flying-box' ), [ 'status' => 503 ] );
		}

		$auth_header = $request->get_header( 'authorization' );
		if ( empty( $auth_header ) ) {
			// Fallback: some server configs strip Authorization
			if ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
				$auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
			} elseif ( function_exists( 'getallheaders' ) ) {
				$headers = getallheaders();
				$auth_header = isset( $headers['Authorization'] ) ? $headers['Authorization'] : ( isset( $headers['authorization'] ) ? $headers['authorization'] : '' );
			}
		}

		if ( empty( $auth_header ) ) {
			return new WP_Error( 'missing_authorization', __( 'Authorization header is required.', 'my-flying-box' ), [ 'status' => 401 ] );
		}

		if ( ! preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
			return new WP_Error( 'invalid_authorization_format', __( 'Authorization header must use Bearer token format.', 'my-flying-box' ), [ 'status' => 401 ] );
		}

		$jwt = $matches[1];
		$payload = self::decode_jwt( $jwt, $jwt_secret );

		if ( $payload === false ) {
			return new WP_Error( 'invalid_token', __( 'Invalid or expired JWT token.', 'my-flying-box' ), [ 'status' => 401 ] );
		}

		// Verify audience matches shop UUID
		$shop_uuid = get_option( 'mfb_shop_uuid', '' );
		if ( ! isset( $payload->aud ) || $payload->aud !== $shop_uuid ) {
			return new WP_Error( 'invalid_audience', __( 'Token audience does not match shop identifier.', 'my-flying-box' ), [ 'status' => 403 ] );
		}

		return true;
	}

	// ─── JWT Helpers ─────────────────────────────────────────────────

	/**
	 * Decode and verify a JWT token (HS256).
	 *
	 * @param  string $jwt
	 * @param  string $secret
	 * @return object|false  Decoded payload or false on failure.
	 */
	private static function decode_jwt( $jwt, $secret ) {
		if ( empty( $jwt ) || empty( $secret ) ) {
			return false;
		}

		$parts = explode( '.', $jwt );
		if ( count( $parts ) !== 3 ) {
			return false;
		}

		list( $header_enc, $payload_enc, $sig_enc ) = $parts;

		$header  = json_decode( self::base64url_decode( $header_enc ) );
		$payload = json_decode( self::base64url_decode( $payload_enc ) );

		if ( ! $header || ! $payload ) {
			return false;
		}

		if ( ! isset( $header->alg ) || $header->alg !== 'HS256' ) {
			return false;
		}

		$signature          = self::base64url_decode( $sig_enc );
		$expected_signature = hash_hmac( 'sha256', $header_enc . '.' . $payload_enc, $secret, true );

		if ( ! hash_equals( $expected_signature, $signature ) ) {
			return false;
		}

		if ( isset( $payload->exp ) && $payload->exp < time() ) {
			return false;
		}

		if ( isset( $payload->nbf ) && $payload->nbf > time() ) {
			return false;
		}

		return $payload;
	}

	/**
	 * Base64 URL-safe decode.
	 */
	private static function base64url_decode( $input ) {
		$remainder = strlen( $input ) % 4;
		if ( $remainder ) {
			$input .= str_repeat( '=', 4 - $remainder );
		}
		return base64_decode( strtr( $input, '-_', '+/' ) );
	}

	// ─── GET /shop ───────────────────────────────────────────────────

	/**
	 * Return shop metadata for the dashboard.
	 */
	public static function get_shop( WP_REST_Request $request ) {
		$default_shipping_address = [
			'name'        => get_option( 'mfb_shipper_name', '' ),
			'company'     => get_option( 'mfb_shipper_company', '' ),
			'street'      => get_option( 'mfb_shipper_street', '' ),
			'city'        => get_option( 'mfb_shipper_city', '' ),
			'state'       => get_option( 'mfb_shipper_state', '' ),
			'postcode'    => get_option( 'mfb_shipper_postal_code', '' ),
			'country_iso' => get_option( 'mfb_shipper_country_code', '' ),
			'phone'       => get_option( 'mfb_shipper_phone', '' ),
			'email'       => get_option( 'mfb_shipper_email', '' ),
		];

		$response = [
			'uuid'                       => get_option( 'mfb_shop_uuid', '' ),
			'name'                       => get_bloginfo( 'name' ),
			'url'                        => get_site_url(),
			'wordpress_version'          => get_bloginfo( 'version' ),
			'woocommerce_version'        => defined( 'WC_VERSION' ) ? WC_VERSION : '',
			'plugin_version'             => MFB()->_version,
			'sync_behavior'              => get_option( 'mfb_dashboard_sync_behavior', 'never' ),
			'sync_history_max_past_days' => (int) get_option( 'mfb_sync_history_max_past_days', 30 ),
			'sync_order_max_duration'    => (int) get_option( 'mfb_sync_order_max_duration', 90 ),
			'default_shipping_address'   => $default_shipping_address,
			'timestamp'                  => date( 'c' ),
		];

		return new WP_REST_Response( $response, 200 );
	}

	// ─── GET /orders ─────────────────────────────────────────────────

	/**
	 * Return a list of WooCommerce orders within a date range.
	 */
	public static function get_orders( WP_REST_Request $request ) {
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		// Validate date formats
		if ( ! self::is_valid_date( $start_date ) || ! self::is_valid_date( $end_date ) ) {
			return new WP_REST_Response( [
				'error'   => 'INVALID_DATE_FORMAT',
				'message' => 'Dates must be in YYYY-MM-DD format.',
			], 400 );
		}

		if ( strtotime( $start_date ) > strtotime( $end_date ) ) {
			return new WP_REST_Response( [
				'error'   => 'INVALID_DATE_RANGE',
				'message' => 'start_date must be before or equal to end_date.',
			], 400 );
		}

		// Clamp start date to max accessible history
		$max_past_days = (int) get_option( 'mfb_sync_history_max_past_days', 30 );
		$cutoff_date   = date( 'Y-m-d', strtotime( '-' . $max_past_days . ' days' ) );
		if ( $start_date < $cutoff_date ) {
			$start_date = $cutoff_date;
		}

		$args = [
			'date_created' => $start_date . '...' . $end_date,
			'limit'        => -1,
			'orderby'      => 'date',
			'order'        => 'DESC',
			'return'       => 'objects',
		];

		$orders       = wc_get_orders( $args );
		$orders_data  = [];

		foreach ( $orders as $order ) {
			$orders_data[] = self::serialize_order( $order );
		}

		return new WP_REST_Response( [
			'start_date' => $start_date,
			'end_date'   => $end_date,
			'count'      => count( $orders_data ),
			'orders'     => $orders_data,
		], 200 );
	}

	// ─── GET /order/<id> ─────────────────────────────────────────────

	/**
	 * Return a single WooCommerce order.
	 */
	public static function get_order( WP_REST_Request $request ) {
		$order_id = (int) $request->get_param( 'id' );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_REST_Response( [
				'error'   => 'ORDER_NOT_FOUND',
				'message' => 'Order not found with ID: ' . $order_id,
			], 404 );
		}

		// Check if order is within sync duration limit
		$max_duration = (int) get_option( 'mfb_sync_order_max_duration', 90 );
		$order_date   = strtotime( $order->get_date_created()->date( 'Y-m-d' ) );
		$cutoff_date  = strtotime( '-' . $max_duration . ' days' );

		if ( $order_date < $cutoff_date ) {
			return new WP_REST_Response( [
				'error'   => 'ORDER_TOO_OLD',
				'message' => 'Order is older than the maximum sync duration (' . $max_duration . ' days).',
			], 403 );
		}

		return new WP_REST_Response( self::serialize_order( $order ), 200 );
	}

	// ─── GET /shipment ───────────────────────────────────────────────

	/**
	 * Retrieve shipment data by api_order_id.
	 */
	public static function get_shipment( WP_REST_Request $request ) {
		$api_order_id = sanitize_text_field( $request->get_param( 'api_order_id' ) );
		if ( empty( $api_order_id ) ) {
			$api_order_id = sanitize_text_field( $request->get_param( 'api_id' ) );
		}

		if ( empty( $api_order_id ) ) {
			return new WP_REST_Response( [
				'error'   => 'MISSING_PARAMETER',
				'message' => 'api_order_id parameter is required.',
			], 400 );
		}

		$shipment_id = MFB_Shipment::get_shipment_by_api_uuid( $api_order_id );
		if ( ! $shipment_id ) {
			return new WP_REST_Response( [
				'error'   => 'SHIPMENT_NOT_FOUND',
				'message' => 'No shipment found with api_order_id: ' . $api_order_id,
			], 404 );
		}

		$shipment = MFB_Shipment::get( $shipment_id );

		$data = [
			'id'               => $shipment->id,
			'api_order_uuid'   => $shipment->api_order_uuid,
			'wc_order_id'      => $shipment->wc_order_id,
			'status'           => $shipment->status,
			'date_booking'     => $shipment->date_booking,
			'is_return'        => (bool) $shipment->is_return,
			'insured'          => (bool) $shipment->insured,
			'extended_covered' => (bool) $shipment->extended_covered,
			'delivered'        => (bool) $shipment->delivered,
			'shipper'          => self::format_shipment_address( $shipment->shipper ),
			'recipient'        => self::format_shipment_address( $shipment->recipient ),
			'parcels'          => self::format_shipment_parcels( $shipment->parcels ),
		];

		return new WP_REST_Response( $data, 200 );
	}

	// ─── POST /shipment ──────────────────────────────────────────────

	/**
	 * Create or update a shipment from dashboard data.
	 */
	public static function create_or_update_shipment( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			return new WP_REST_Response( [
				'error'   => 'INVALID_JSON',
				'message' => 'Request body must be valid JSON.',
			], 422 );
		}

		// Support payload wrapped in {"shipment": {...}}
		if ( isset( $data['shipment'] ) && is_array( $data['shipment'] ) ) {
			$data = $data['shipment'];
		}

		$api_order_id = self::extract_field( $data, [ 'api_order_id', 'api_order_uuid', 'api_id' ] );
		$order_id     = isset( $data['order_id'] ) ? (int) $data['order_id'] : 0;

		if ( empty( $api_order_id ) ) {
			return new WP_REST_Response( [
				'error'   => 'MISSING_FIELD',
				'message' => 'Required field missing: api_order_id',
			], 422 );
		}

		if ( $order_id <= 0 ) {
			return new WP_REST_Response( [
				'error'   => 'MISSING_FIELD',
				'message' => 'Required field missing or invalid: order_id',
			], 422 );
		}

		if ( empty( $data['parcels'] ) || ! is_array( $data['parcels'] ) ) {
			return new WP_REST_Response( [
				'error'   => 'INVALID_PAYLOAD',
				'message' => 'Parcels must be provided as a non-empty array.',
			], 422 );
		}

		$parcels_check = self::validate_parcels( $data['parcels'] );
		if ( $parcels_check !== true ) {
			return new WP_REST_Response( [
				'error'   => 'INVALID_PARCEL',
				'message' => $parcels_check,
			], 422 );
		}

		$wc_order = wc_get_order( $order_id );
		if ( ! $wc_order ) {
			return new WP_REST_Response( [
				'error'   => 'ORDER_NOT_FOUND',
				'message' => 'Order not found with ID: ' . $order_id,
			], 404 );
		}

		// Check for existing shipment with this api_order_id
		$existing_id = MFB_Shipment::get_shipment_by_api_uuid( $api_order_id );
		$is_new      = ! $existing_id;

		if ( $existing_id ) {
			$shipment = MFB_Shipment::get( $existing_id );
			if ( (int) $shipment->wc_order_id !== $order_id ) {
				return new WP_REST_Response( [
					'error'   => 'ORDER_MISMATCH',
					'message' => 'Existing shipment already linked to order ' . (int) $shipment->wc_order_id,
				], 409 );
			}
		} else {
			$shipment = new MFB_Shipment();
			$shipment->wc_order_id = $order_id;
			$shipment->status      = 'mfb-booked';
		}

		// Hydrate shipment fields
		$shipment->api_order_uuid = $api_order_id;
		$shipment->is_return      = ! empty( $data['is_return'] );
		$shipment->insured        = ! empty( $data['chosen_cover'] ) && $data['chosen_cover'] == 'insurance';
		$shipment->extended_covered = ! empty( $data['chosen_cover'] ) && $data['chosen_cover'] == 'extended_cover';

		if ( ! empty( $data['created_at'] ) ) {
			$shipment->date_booking = date( 'Y-m-d H:i:s', strtotime( $data['created_at'] ) );
		}

		if ( ! empty( $data['collection_date'] ) ) {
			$shipment->collection_date = sanitize_text_field( $data['collection_date'] );
		}

		if ( ! empty( $data['delivery_location_code'] ) ) {
			$shipment->delivery_location_code = sanitize_text_field( $data['delivery_location_code'] );
		}

		// Hydrate shipper address
		$shipper_data = isset( $data['shipper'] ) && is_array( $data['shipper'] ) ? $data['shipper'] : [];
		self::apply_address_to_shipment( $shipment, 'shipper', $shipper_data );

		// Hydrate recipient address
		$recipient_data = isset( $data['recipient'] ) && is_array( $data['recipient'] ) ? $data['recipient'] : [];
		self::apply_address_to_shipment( $shipment, 'recipient', $recipient_data );

		// Set parcels
		$parcels = [];
		foreach ( $data['parcels'] as $p ) {
			$parcel = new stdClass();
			$parcel->length            = (int) round( $p['length'] );
			$parcel->width             = (int) round( $p['width'] );
			$parcel->height            = (int) round( $p['height'] );
			$parcel->weight            = self::normalize_weight( $p['weight'], isset( $p['mass_unit'] ) ? $p['mass_unit'] : 'kg' );
			$parcel->description       = isset( $p['description'] ) ? sanitize_text_field( $p['description'] ) : get_option( 'mfb_default_parcel_description', '' );
			$parcel->country_of_origin = isset( $p['country_of_origin'] ) ? sanitize_text_field( $p['country_of_origin'] ) : get_option( 'mfb_default_origin_country', '' );
			$parcel->value             = isset( $p['value'] ) ? (int) round( $p['value'] ) : 0;
			$parcel->insurable_value   = 0;
			$parcel->shipper_reference   = isset( $p['shipper_reference'] ) ? sanitize_text_field( $p['shipper_reference'] ) : '';
			$parcel->recipient_reference = isset( $p['recipient_reference'] ) ? sanitize_text_field( $p['recipient_reference'] ) : '';
			$parcel->customer_reference  = isset( $p['customer_reference'] ) ? sanitize_text_field( $p['customer_reference'] ) : '';
			$parcel->tracking_number     = isset( $p['carrier_reference'] ) ? sanitize_text_field( $p['carrier_reference'] ) : '';

			if ( $shipment->insured || $shipment->extended_covered ) {
				$parcel->insurable_value = isset( $p['value_to_insure'] ) ? (float) $p['value_to_insure'] : (float) $parcel->value;
			}

			$parcels[] = $parcel;
		}
		$shipment->parcels = $parcels;

		// Create or update the selected offer object
		$offer_data = isset( $data['selected_offer'] ) && is_array( $data['selected_offer'] ) ? $data['selected_offer'] : [];

		// Fallback to flat fields for backward compatibility
		if ( empty( $offer_data ) ) {
			$offer_data = [
				'api_offer_id'  => self::extract_field( $data, [ 'api_offer_id', 'offer_id', 'offer_uuid' ] ),
				'product_code'  => self::extract_field( $data, [ 'product_code', 'service_code' ] ),
				'carrier_code'  => isset( $data['carrier_code'] ) ? $data['carrier_code'] : '',
				'product_name'  => isset( $data['product_name'] ) ? $data['product_name'] : '',
				'pickup'        => isset( $data['pickup'] ) ? $data['pickup'] : null,
				'dropoff'       => isset( $data['dropoff'] ) ? $data['dropoff'] : null,
				'relay'         => isset( $data['relay'] ) ? $data['relay'] : null,
			];
		}

		$api_offer_id = self::extract_field( $offer_data, [ 'api_offer_id', 'offer_id', 'offer_uuid' ] );
		$product_code = self::extract_field( $offer_data, [ 'product_code', 'service_code' ] );

		if ( ! empty( $product_code ) || ! empty( $api_offer_id ) ) {
			// Reuse existing offer if the shipment already has one, otherwise create new
			$offer = ( $shipment->offer && $shipment->offer->id > 0 ) ? $shipment->offer : new MFB_Offer();

			if ( ! empty( $api_offer_id ) && $offer->id == 0 ) {
				// api_offer_uuid is immutable — only set on new offers
				$offer->api_offer_uuid = sanitize_text_field( $api_offer_id );
			}
			if ( ! empty( $product_code ) ) {
				$offer->product_code = sanitize_text_field( $product_code );
			}
			if ( ! empty( $offer_data['carrier_code'] ) ) {
				$offer->carrier_code = sanitize_text_field( $offer_data['carrier_code'] );
			}
			if ( ! empty( $offer_data['product_name'] ) ) {
				$offer->product_name = sanitize_text_field( $offer_data['product_name'] );
			}
			if ( isset( $offer_data['pickup'] ) ) {
				$offer->pickup = ! empty( $offer_data['pickup'] );
			}
			if ( isset( $offer_data['dropoff'] ) ) {
				$offer->dropoff = ! empty( $offer_data['dropoff'] );
			}
			if ( isset( $offer_data['relay'] ) ) {
				$offer->relay = ! empty( $offer_data['relay'] );
			}
			if ( isset( $offer_data['base_price_in_cents'] ) ) {
				$offer->base_price_in_cents = (int) $offer_data['base_price_in_cents'];
			}
			if ( isset( $offer_data['total_price_in_cents'] ) ) {
				$offer->total_price_in_cents = (int) $offer_data['total_price_in_cents'];
			}
			if ( isset( $offer_data['insurance_price_in_cents'] ) ) {
				$offer->insurance_price_in_cents = (int) $offer_data['insurance_price_in_cents'];
			}
			if ( ! empty( $offer_data['currency'] ) ) {
				$offer->currency = sanitize_text_field( $offer_data['currency'] );
			}
			if ( isset( $offer_data['collection_dates'] ) ) {
				$offer->collection_dates = $offer_data['collection_dates'];
			}
			if ( isset( $offer_data['extended_cover_available'] ) ) {
				$offer->extended_cover_available = ! empty( $offer_data['extended_cover_available'] );
			}
			if ( isset( $offer_data['price_with_extended_cover'] ) ) {
				$offer->price_with_extended_cover = (int) $offer_data['price_with_extended_cover'];
			}
			if ( isset( $offer_data['price_vat_with_extended_cover'] ) ) {
				$offer->price_vat_with_extended_cover = (int) $offer_data['price_vat_with_extended_cover'];
			}
			if ( isset( $offer_data['total_price_with_extended_cover'] ) ) {
				$offer->total_price_with_extended_cover = (int) $offer_data['total_price_with_extended_cover'];
			}
			if ( isset( $offer_data['extended_cover_max_liability'] ) ) {
				$offer->extended_cover_max_liability = (int) $offer_data['extended_cover_max_liability'];
			}

			// electronic customs
			if ( isset( $offer_data['support_electronic_customs'] ) ) {
				$offer->support_electronic_customs = ! empty( $offer_data['support_electronic_customs'] );
			}
			if ( isset( $offer_data['mandatory_electronic_customs'] ) ) {
				$offer->mandatory_electronic_customs = ! empty( $offer_data['mandatory_electronic_customs'] );
			}

			$offer->save();
			$shipment->offer = $offer;
		}

		$shipment->save();

		// If configured and new shipment, update WC order status
		if ( get_option( 'mfb_update_order_status_on_shipment', 'no' ) === 'yes' && $is_new ) {
			$tracking_number = isset( $data['parcels'][0]['carrier_reference'] ) ? sanitize_text_field( $data['parcels'][0]['carrier_reference'] ) : '';

			if ( $wc_order->get_status() !== 'completed' ) {
				$wc_order->set_status( 'completed' );
				if ( $tracking_number ) {
					$wc_order->add_order_note(
						sprintf( __( 'Shipment created from MyFlyingBox dashboard. Tracking: %s', 'my-flying-box' ), $tracking_number )
					);
				} else {
					$wc_order->add_order_note( __( 'Shipment created from MyFlyingBox dashboard.', 'my-flying-box' ) );
				}
				$wc_order->save();
			}
		}

		return new WP_REST_Response( [
			'success'      => true,
			'message'      => $is_new ? 'Shipment created successfully' : 'Shipment updated successfully',
			'api_order_id' => $api_order_id,
			'id_shipment'  => (int) $shipment->id,
			'order_id'     => $order_id,
		], $is_new ? 201 : 200 );
	}

	// ─── Order Serialization ─────────────────────────────────────────

	/**
	 * Serialize a WC_Order into the format expected by the dashboard connector.
	 *
	 * @param  WC_Order $order
	 * @return array
	 */
	private static function serialize_order( WC_Order $order ) {
		$products     = [];
		$total_weight = 0.0;

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$qty     = $item->get_quantity();

			$product_weight = 0.0;
			if ( $product && is_numeric( $product->get_weight() ) ) {
				$product_weight = (float) wc_get_weight( $product->get_weight(), 'kg' );
			}

			$total_weight += $product_weight * $qty;

			$products[] = [
				'id'                  => $product ? $product->get_id() : 0,
				'reference'           => $product ? $product->get_sku() : '',
				'name'                => $item->get_name(),
				'quantity'            => $qty,
				'unit_price_tax_excl' => (float) ( $item->get_total() / max( $qty, 1 ) ),
				'unit_price_tax_incl' => (float) ( ( $item->get_total() + $item->get_total_tax() ) / max( $qty, 1 ) ),
				'total_price_tax_excl' => (float) $item->get_total(),
				'total_price_tax_incl' => (float) ( $item->get_total() + $item->get_total_tax() ),
				'weight'              => $product_weight,
			];
		}

		// Determine carrier / shipping method info
		$shipping_methods = $order->get_shipping_methods();
		$carrier_name     = '';
		$mfb_service_code = '';
		$carrier_id       = 0;

		if ( ! empty( $shipping_methods ) ) {
			$first_method    = reset( $shipping_methods );
			$carrier_name    = $first_method->get_method_title();
			$method_id_parts = explode( ':', $first_method->get_method_id() );
			$mfb_service_code = $method_id_parts[0];
			$carrier_id       = isset( $method_id_parts[1] ) ? (int) $method_id_parts[1] : 0;
		}

		// Selected relay delivery location (if any)
		$selected_relay = get_post_meta( $order->get_id(), '_mfb_delivery_location', true );

		// Map WC order status to standard slugs
		$wc_status = 'wc-' . $order->get_status();

		return [
			'id'               => $order->get_id(),
			'reference'        => $order->get_order_number(),
			'date_add'         => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
			'date_upd'         => $order->get_date_modified() ? $order->get_date_modified()->date( 'Y-m-d H:i:s' ) : '',
			'current_state'    => [
				'id'    => $wc_status,
				'name'  => wc_get_order_status_name( $order->get_status() ),
				'slug'  => $wc_status,
			],
			'customer'         => [
				'email'     => $order->get_billing_email(),
				'firstname' => $order->get_billing_first_name(),
				'lastname'  => $order->get_billing_last_name(),
				'phone'     => $order->get_billing_phone(),
				'company'   => $order->get_billing_company(),
				'address1'  => $order->get_billing_address_1(),
				'address2'  => $order->get_billing_address_2(),
				'postcode'  => $order->get_billing_postcode(),
				'city'      => $order->get_billing_city(),
				'state'     => $order->get_billing_state(),
				'country_iso' => $order->get_billing_country(),
			],
			'delivery_address' => [
				'firstname'    => $order->get_shipping_first_name(),
				'lastname'     => $order->get_shipping_last_name(),
				'company'      => $order->get_shipping_company(),
				'address1'     => $order->get_shipping_address_1(),
				'address2'     => $order->get_shipping_address_2(),
				'postcode'     => $order->get_shipping_postcode(),
				'city'         => $order->get_shipping_city(),
				'state'        => $order->get_shipping_state(),
				'country_iso'  => $order->get_shipping_country(),
				'phone'        => $order->get_shipping_phone(),
			],
			'carrier'          => [
				'id'               => $carrier_id,
				'name'             => $carrier_name,
				'mfb_service_code' => $mfb_service_code,
				'selected_relay'   => $selected_relay ? $selected_relay : null,
			],
			'products'         => $products,
			'totals'           => [
				'products'          => (float) $order->get_subtotal(),
				'products_wt'       => (float) ( $order->get_subtotal() + $order->get_total_tax() - $order->get_shipping_tax() ),
				'shipping'          => (float) $order->get_shipping_total(),
				'shipping_tax_incl' => (float) ( $order->get_shipping_total() + $order->get_shipping_tax() ),
				'total_paid'        => (float) $order->get_total(),
				'total_paid_tax_incl' => (float) $order->get_total(),
			],
			'currency'         => [
				'iso_code' => $order->get_currency(),
			],
			'weight'           => $total_weight,
		];
	}

	// ─── GET /settings/custom_api_server_url ─────────────────────────

	/**
	 * Return the current custom API server URL setting.
	 */
	public static function get_custom_api_server_url( WP_REST_Request $request ) {
		return new WP_REST_Response( [
			'mfb_custom_api_server_url' => get_option( 'mfb_custom_api_server_url', '' ),
		], 200 );
	}

	// ─── POST /settings/custom_api_server_url ────────────────────────

	/**
	 * Set the custom API server URL.
	 * Send an empty string or null to clear.
	 */
	public static function set_custom_api_server_url( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		$url  = isset( $data['mfb_custom_api_server_url'] ) ? $data['mfb_custom_api_server_url'] : '';

		if ( ! empty( $url ) ) {
			$url = esc_url_raw( $url );
			if ( empty( $url ) ) {
				return new WP_REST_Response( [
					'error'   => 'INVALID_URL',
					'message' => 'The provided value is not a valid URL.',
				], 422 );
			}
		}

		update_option( 'mfb_custom_api_server_url', $url );

		return new WP_REST_Response( [
			'success'                   => true,
			'mfb_custom_api_server_url' => $url,
		], 200 );
	}

	// ─── GET /settings/custom_dashboard_server_url ──────────────────

	/**
	 * Return the current custom dashboard server URL setting.
	 */
	public static function get_custom_dashboard_server_url( WP_REST_Request $request ) {
		return new WP_REST_Response( [
			'mfb_custom_dashboard_server_url' => get_option( 'mfb_custom_dashboard_server_url', '' ),
		], 200 );
	}

	// ─── POST /settings/custom_dashboard_server_url ─────────────────

	/**
	 * Set the custom dashboard server URL.
	 * Send an empty string or null to clear.
	 */
	public static function set_custom_dashboard_server_url( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		$url  = isset( $data['mfb_custom_dashboard_server_url'] ) ? $data['mfb_custom_dashboard_server_url'] : '';

		if ( ! empty( $url ) ) {
			$url = esc_url_raw( $url );
			if ( empty( $url ) ) {
				return new WP_REST_Response( [
					'error'   => 'INVALID_URL',
					'message' => 'The provided value is not a valid URL.',
				], 422 );
			}
		}

		update_option( 'mfb_custom_dashboard_server_url', $url );

		return new WP_REST_Response( [
			'success'                         => true,
			'mfb_custom_dashboard_server_url' => $url,
		], 200 );
	}

	// ─── GET /settings/webhooks_signature_key ───────────────────────

	/**
	 * Return the current webhooks signature key.
	 */
	public static function get_webhooks_signature_key( WP_REST_Request $request ) {
		return new WP_REST_Response( [
			'mfb_webhooks_signature_key' => get_option( 'mfb_webhooks_signature_key', '' ),
		], 200 );
	}

	// ─── POST /settings/webhooks_signature_key ──────────────────────

	/**
	 * Set the webhooks signature key.
	 * Send an empty string or null to clear.
	 */
	public static function set_webhooks_signature_key( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		$key  = isset( $data['mfb_webhooks_signature_key'] ) ? sanitize_text_field( $data['mfb_webhooks_signature_key'] ) : '';

		update_option( 'mfb_webhooks_signature_key', $key );

		return new WP_REST_Response( [
			'success'                    => true,
			'mfb_webhooks_signature_key' => $key,
		], 200 );
	}

	// ─── Helpers ─────────────────────────────────────────────────────

	/**
	 * Validate date format YYYY-MM-DD.
	 */
	private static function is_valid_date( $date ) {
		$d = DateTime::createFromFormat( 'Y-m-d', $date );
		return $d && $d->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Extract the first non-empty field from data by a list of candidate keys.
	 */
	private static function extract_field( array $data, array $keys, $default = '' ) {
		foreach ( $keys as $key ) {
			if ( ! empty( $data[ $key ] ) ) {
				return $data[ $key ];
			}
		}
		return $default;
	}

	/**
	 * Validate parcel array entries.
	 */
	private static function validate_parcels( array $parcels ) {
		if ( empty( $parcels ) ) {
			return 'At least one parcel is required.';
		}
		foreach ( $parcels as $index => $parcel ) {
			foreach ( [ 'length', 'width', 'height', 'weight' ] as $key ) {
				if ( ! isset( $parcel[ $key ] ) || ! is_numeric( $parcel[ $key ] ) ) {
					return 'Parcel #' . ( $index + 1 ) . ': missing or invalid ' . $key;
				}
			}
		}
		return true;
	}

	/**
	 * Get default shipper address from plugin settings.
	 */
	private static function get_default_shipper_address() {
		return [
			'name'         => get_option( 'mfb_shipper_name', '' ),
			'company'      => get_option( 'mfb_shipper_company', '' ),
			'street'       => get_option( 'mfb_shipper_street', '' ),
			'city'         => get_option( 'mfb_shipper_city', '' ),
			'state'        => get_option( 'mfb_shipper_state', '' ),
			'postal_code'  => get_option( 'mfb_shipper_postal_code', '' ),
			'country_code' => get_option( 'mfb_shipper_country_code', '' ),
			'phone'        => get_option( 'mfb_shipper_phone', '' ),
			'email'        => get_option( 'mfb_shipper_email', '' ),
		];
	}

	/**
	 * Apply address data to a shipment object.
	 */
	private static function apply_address_to_shipment( MFB_Shipment $shipment, $type, array $addr ) {
		$shipment->$type->name         = isset( $addr['name'] ) ? sanitize_text_field( $addr['name'] ) : '';
		$shipment->$type->company      = isset( $addr['company'] ) ? sanitize_text_field( $addr['company'] ) : '';
		$shipment->$type->street       = isset( $addr['street'] ) ? sanitize_textarea_field( $addr['street'] ) : '';
		$shipment->$type->city         = isset( $addr['city'] ) ? sanitize_text_field( $addr['city'] ) : '';
		$shipment->$type->state        = isset( $addr['state'] ) ? sanitize_text_field( $addr['state'] ) : '';
		$shipment->$type->postal_code  = isset( $addr['postal_code'] ) ? sanitize_text_field( $addr['postal_code'] ) : '';
		$shipment->$type->country_code = isset( $addr['country'] ) ? sanitize_text_field( $addr['country'] ) : '';
		$shipment->$type->phone        = isset( $addr['phone'] ) ? sanitize_text_field( $addr['phone'] ) : '';
		$shipment->$type->email        = isset( $addr['email'] ) ? sanitize_text_field( $addr['email'] ) : '';
	}

	/**
	 * Format a shipment address object for API response.
	 */
	private static function format_shipment_address( $address ) {
		if ( ! $address ) return [];
		$result = [];
		foreach ( MFB_Shipment::$address_fields as $field ) {
			$result[ $field ] = isset( $address->$field ) ? $address->$field : '';
		}
		return $result;
	}

	/**
	 * Format shipment parcels for API response.
	 */
	private static function format_shipment_parcels( array $parcels ) {
		$result = [];
		foreach ( $parcels as $parcel ) {
			$p = [];
			foreach ( MFB_Shipment::$parcel_fields as $field ) {
				$p[ $field ] = isset( $parcel->$field ) ? $parcel->$field : '';
			}
			$result[] = $p;
		}
		return $result;
	}

	/**
	 * Normalize weight to kg.
	 */
	private static function normalize_weight( $weight, $mass_unit = 'kg' ) {
		$normalized = (float) $weight;
		if ( strtolower( (string) $mass_unit ) === 'lbs' ) {
			$normalized = round( $normalized * 0.45359237, 3 );
		}
		return $normalized;
	}
}
