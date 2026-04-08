<?php
/**
 * MFB Webhooks — Send order event notifications to the MyFlyingBox dashboard.
 *
 * Mirrors the Prestashop module webhook implementation:
 * - Sends order/create, order/update, order/cancel topics
 * - Uses HMAC-SHA256 signature with the webhooks signature key
 * - Respects the mfb_dashboard_sync_behavior setting
 * - Uses mfb_custom_dashboard_server_url when configured
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MFB_Webhooks {

	/**
	 * Register WooCommerce hooks for order events.
	 */
	public static function init() {
		// Order created (payment complete or manual)
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'on_order_created' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'on_order_created' ), 20, 1 );

		// Order status changed (covers all transitions: processing, completed, cancelled, etc.)
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_order_status_changed' ), 20, 4 );
	}

	// ─── Hook Callbacks ──────────────────────────────────────────────

	/**
	 * Fires when a new order is placed via checkout.
	 *
	 * @param int|WC_Order $order_or_id Order ID (classic checkout) or WC_Order (Store API)
	 */
	public static function on_order_created( $order_or_id ) {
		$order = $order_or_id instanceof WC_Order ? $order_or_id : wc_get_order( $order_or_id );
		if ( ! $order ) return;

		self::send_order_webhook( 'order/create', $order );
	}

	/**
	 * Fires when an order status changes.
	 *
	 * @param int    $order_id
	 * @param string $old_status
	 * @param string $new_status
	 * @param WC_Order $order
	 */
	public static function on_order_status_changed( $order_id, $old_status, $new_status, $order ) {
		$topic = ( $new_status === 'cancelled' ) ? 'order/cancel' : 'order/update';
		self::send_order_webhook( $topic, $order );
	}

	// ─── Webhook Sending ─────────────────────────────────────────────

	/**
	 * Build a minimal order payload and send the webhook.
	 *
	 * @param string   $topic
	 * @param WC_Order $order
	 * @param bool     $force
	 * @return bool
	 */
	public static function send_order_webhook( $topic, WC_Order $order, $force = false ) {
		$payload = [
			'order_id'        => $order->get_id(),
			'order_reference' => $order->get_order_number(),
			'created_at'      => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
			'state'           => wc_get_order_status_name( $order->get_status() ),
		];

		return self::send_webhook( $topic, $payload, $force );
	}

	/**
	 * Check if webhooks should be sent based on sync behavior setting.
	 *
	 * @param bool $force
	 * @return bool
	 */
	public static function should_send_webhooks( $force = false ) {
		$behavior = get_option( 'mfb_dashboard_sync_behavior', 'never' );
		if ( $behavior === 'never' ) {
			return false;
		}
		if ( $behavior === 'always' ) {
			return true;
		}
		// on_demand: only send when forced
		return (bool) $force;
	}

	/**
	 * Send a webhook notification to the MFB dashboard.
	 *
	 * @param string $topic   e.g. order/create, order/update, order/cancel
	 * @param array  $payload
	 * @param bool   $force   Force sending even when sync behavior is on_demand
	 * @return bool
	 */
	public static function send_webhook( $topic, array $payload, $force = false ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[MFB] Sending webhook (' . $topic . '): ' . wp_json_encode( $payload ) );
		}

		if ( ! self::should_send_webhooks( $force ) ) {
			return false;
		}

		$shop_uuid     = get_option( 'mfb_shop_uuid', '' );
		$signature_key = get_option( 'mfb_webhooks_signature_key', '' );

		if ( empty( $shop_uuid ) || empty( $signature_key ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[MFB] Webhook not sent: missing shop UUID or webhooks signature key.' );
			}
			return false;
		}

		$body      = wp_json_encode( $payload );
		$signature = base64_encode( hash_hmac( 'sha256', $body, $signature_key, true ) );

		$headers = [
			'Content-Type'        => 'application/json',
			'x-mfb-shop-id'      => $shop_uuid,
			'x-mfb-topic'        => $topic,
			'x-mfb-hmac-sha256'  => $signature,
			'x-mfb-triggered-at' => (string) time(),
			'x-mfb-module-version' => MFB()->_version,
		];

		$api_login = get_option( 'mfb_api_login', '' );
		if ( ! empty( $api_login ) ) {
			$headers['x-mfb-client-api-id'] = $api_login;
		}

		// Use custom dashboard URL if configured, otherwise default to production
		$custom_dashboard_url = get_option( 'mfb_custom_dashboard_server_url', '' );
		$base_url = ! empty( $custom_dashboard_url ) ? rtrim( $custom_dashboard_url, '/' ) : 'https://dashboard.myflyingbox.com';
		$webhook_url = $base_url . '/webhooks/woocommerce';

		$response = wp_remote_post( $webhook_url, [
			'body'      => $body,
			'headers'   => $headers,
			'timeout'   => 20,
			'sslverify' => true,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( '[MFB] Webhook error (' . $topic . '): ' . $response->get_error_message() );
			return false;
		}

		$http_code = wp_remote_retrieve_response_code( $response );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[MFB] Webhook response (' . $topic . '): HTTP ' . $http_code );
		}

		if ( $http_code >= 400 ) {
			error_log( '[MFB] Webhook error (' . $topic . '): HTTP ' . $http_code . ' ' . wp_remote_retrieve_body( $response ) );
			return false;
		}

		return true;
	}
}
