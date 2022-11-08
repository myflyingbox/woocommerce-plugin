<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * AJAX Event Handler
 */

class MFB_AJAX {

	public static function init() {

		// mfb_EVENT => nopriv
		// nopriv = true => user-facing request
		$ajax_events = array(
			'get_delivery_locations'                          => true,
			'create_shipment'                                 => false,
			'create_return_shipment'                          => false,
			'book_offer'                                      => false,
			'delete_shipment'                                 => false,
			'delete_bulk_order'                               => false,
			'update_shipper'                                  => false,
			'update_recipient'                                => false,
			'add_parcel'                                      => false,
			'edit_parcel'                                     => false,
			'update_parcel'                                   => false,
			'delete_parcel'                                   => false,
			'update_selected_offer'                           => false,
			'download_labels'                                 => false
		);
		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_mfb_' . $ajax_event, array( __CLASS__, $ajax_event ) );

			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_mfb_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}

	public static function get_delivery_locations () {

		if ( empty( $k ) ) {
			//
			// We have no offer uuid. We need to request a quote to get the delivery locations!

			$method_name = $_REQUEST['s'];
			$instance_id = $_REQUEST['i'];

			if ( ! class_exists($method_name)){
				eval("class $method_name extends MFB_Shipping_Method{}");
			}
			$shipping_method = new $method_name( $instance_id );

			// Extracting total weight from the WC CART
			$weight = 0;
			$dimensions_available = true;
			$products = [];
			foreach ( WC()->cart->get_cart() as $item_id => $values ) {
				$product = $values['data'];
				if( $product->needs_shipping() ) {
					$product_weight = $product->get_weight() ? wc_format_decimal( wc_get_weight($product->get_weight(),'kg'), 2 ) : 0;
					$weight += ($product_weight*$values['quantity']);
					if ($product->get_length() > 0 && $product->get_width() > 0 && $product->get_height() > 0) {
						$products[] = ['name' => $product->get_title(), 'price' => wc_format_decimal($product->get_price()), 'quantity' => $values['quantity'], 'weight' => $product->get_weight(), 'length' => $product->get_length(), 'width' => $product->get_width(), 'height' => $product->get_height()];
					} else {
						$dimensions_available = false;
					}
				}
			}

			if ( 0 == $weight)
				$weight = 0.2;


			// We prepare the parcels data depending on whether or not we have product dimensions
			$parcels = [];
			if ( $dimensions_available && !$shipping_method->force_dimensions_table ) {
				foreach($products as $product) {
					for($i = 1; $i <= $product['quantity']; $i++){
						$parcel = [];
						$parcel['length']            = $product['length'];
						$parcel['width']             = $product['width'];
						$parcel['height']            = $product['height'];
						$parcel['weight']            = $product['weight'];
						$parcels[] = $parcel;
					}
				}
			} else {
				$parcel = [];
				$dims = MFB_Dimension::get_for_weight( $weight );

				# We should use weight/dimensions correspondance; if we don't have any, we can't get a tariff...
				if (!$dims) return false;

				$parcel['length']            = $dims->length;
				$parcel['width']             = $dims->width;
				$parcel['height']            = $dims->height;
				$parcel['weight']            = $weight;
				$parcels[] = $parcel;
			}

			// And then we build the quote request params' array
			$recipient_city = (isset($_REQUEST['ship_to_different_address']) && $_REQUEST['ship_to_different_address'] == 1) ? $_REQUEST['shipping_city'] : $_REQUEST['billing_city'];
			$recipient_postal_code = (isset($_REQUEST['ship_to_different_address']) && $_REQUEST['ship_to_different_address'] == 1) ? $_REQUEST['shipping_postcode'] : $_REQUEST['billing_postcode'];
			$recipient_country = (isset($_REQUEST['ship_to_different_address']) && $_REQUEST['ship_to_different_address'] == 1) ? $_REQUEST['shipping_country'] : $_REQUEST['billing_country'];
			$recipient_company_name = (isset($_REQUEST['ship_to_different_address']) && $_REQUEST['ship_to_different_address'] == 1) ? $_REQUEST['shipping_company'] : $_REQUEST['billing_company'];

			$params = array(
				'shipper' => array(
					'city'         => My_Flying_Box_Settings::get_option('mfb_shipper_city'),
					'postal_code'  => My_Flying_Box_Settings::get_option('mfb_shipper_postal_code'),
					'country'      => My_Flying_Box_Settings::get_option('mfb_shipper_country_code')
				),
				'recipient' => array(
					'city'         => $recipient_city,
					'postal_code'  => $recipient_postal_code,
					'country'      => $recipient_country,
					'is_a_company' => !empty( $recipient_company_name )
				),
				'parcels' => $parcels
			);

			if (
				empty( $params['recipient']['city'] ) ||
				empty( $params['recipient']['country'] )
			) {

				$response['data'] = 'error';
				$response['message'] = 'Please fill in all address fields before loading the delivery location selector';

				wp_send_json( $response );
				die();
			}

			// Loading existing quote, if available, so as not to send useless requests to the API
			$saved_quote_id = WC()->session->get('myflyingbox_shipment_quote_id');
			$quote_request_time = WC()->session->get('myflyingbox_shipment_quote_timestamp');

			if (
						is_numeric( $saved_quote_id ) &&
						$quote_request_time &&
						time() - $_SERVER['REQUEST_TIME'] < 600
					) {

				$quote = MFB_Quote::get( $saved_quote_id );

				if (
					$quote->params['recipient']['city']        !=  $params['recipient']['city'] ||
					$quote->params['recipient']['postal_code'] !=  $params['recipient']['postal_code'] ||
					$quote->params['recipient']['country']     !=  $params['recipient']['country']
				) {
					$quote = null;
				}
			}

			if ( ! $quote ) {

				$api_quote = Lce\Resource\Quote::request($params);

				$quote = new MFB_Quote();
				$quote->api_quote_uuid = $api_quote->id;
				$quote->params         = $params;

				if ($quote->save()) {
					// Now we create the offers

					foreach($api_quote->offers as $k => $api_offer) {
						$offer = new MFB_Offer();
						$offer->quote_id = $quote->id;
						$offer->api_offer_uuid = $api_offer->id;
						$offer->product_code = $api_offer->product->code;
						$offer->base_price_in_cents = $api_offer->price->amount_in_cents;
						$offer->total_price_in_cents = $api_offer->total_price->amount_in_cents;
						$offer->currency = $api_offer->total_price->currency;
						$offer->save();
					}
				}
				// Refreshing the quote, to get the offers loaded properly
				$quote->populate();

				WC()->session->set( 'myflyingbox_shipment_quote_id', $quote->id );
				WC()->session->set( 'myflyingbox_shipment_quote_timestamp', $_SERVER['REQUEST_TIME'] );
			}
			$offer = $quote->offers[$_REQUEST['s']];

		} else {
			// Getting Offer from transmitted uuid:
			$offer = MFB_Offer::get_by_uuid( $_REQUEST['k'] );
		}

		$street = isset($_REQUEST['ship_to_different_address']) && $_REQUEST['ship_to_different_address'] == 1 ? $_REQUEST['shipping_address_1'] : $_REQUEST['billing_address_1'];
		$street_line_2 = isset($_REQUEST['ship_to_different_address']) && $_REQUEST['ship_to_different_address'] == 1 ? $_REQUEST['shipping_address_2'] : $_REQUEST['billing_address_2'];
		if ( ! empty( $street_line_2 ) ) {
			$street .= "\n".$street_line_2;
		}

		$params = array(
			'street' => $street,
			'city' => $quote->params['recipient']['city']
		);

		// Building the response
		$response = array();

		$locations = $offer->get_delivery_locations( $params );

		if ( ! empty($locations) ) {

			$response['data'] = 'success';
			$response['locations'] = $locations;

		} else {
			$response['data'] = 'error';
			$response['message'] = 'Failed to load locations';
		}

		// Whatever the outcome, send the Response back
		wp_send_json( $response );
	}

	public static function create_shipment () {

		// We create a ready-to-confirm shipment object based on existing order
		$order_id = intval( $_POST['order_id'] );
		$order = new WC_Order( $order_id );
		$shipment = MFB_Shipment::create_from_order( $order );
		$response['data'] = 'success';
		$response['shipment'] = $shipment;

		// Whatever the outcome, send the Response back
		wp_send_json( $response );
	}

	public static function create_return_shipment () {
		// We create a ready-to-confirm shipment object based on existing order
		$origin_shipment_id = intval( $_POST['shipment_id'] );

		// Second parameter means we want a return shipment
		$shipment = MFB_Shipment::create_from_shipment( $origin_shipment_id, true );
		$response['data'] = 'success';
		$response['shipment'] = $shipment;

		// Whatever the outcome, send the Response back
		wp_send_json( $response );
	}

	public static function book_offer () {

		// We create a ready-to-confirm shipment object based on existing order
		$shipment_id = intval( $_POST['shipment_id'] );
		$shipment = MFB_Shipment::get( $shipment_id );

		if ( $shipment->status != 'mfb-draft' ) die();

		$offer_id = intval( $_POST['offer_id'] );
		$offer = MFB_Offer::get( $offer_id );

		$error = false;

		if ( $offer && $shipment->quote->offers[$offer->product_code] ) {
			$shipment->offer = $offer;
			if ( $offer->pickup ) {
				$shipment->collection_date = $_POST['pickup_date'];
			}
			if ( $offer->relay ) {
				$shipment->delivery_location_code = $_POST['relay_code'];
			}
			if ( $_POST['insurance'] == '1'  ) {
				$shipment->insured = true;
			} else {
				$shipment->insured = false;
			}
			$shipment->save();
			try {
				$shipment->place_booking();
			} catch ( Lce\Exception\LceException $e) {
				$error = true;
				$error_message = $e->getMessage();
			}
		} else {
			$error = true;
			$error_message = 'Selected offer does not match any available offer in the quotation';
		}

		if ( ! $error ) {
			$response['data'] = 'success';
		} else {
			$response['data'] = 'error';
			$response['message'] = $error_message;
		}

		// Whatever the outcome, send the Response back
		if ( $error ) {
			wp_send_json_error( $response );
		} else {
			wp_send_json_success( $response );
		}

	}


	public static function delete_shipment () {

		// We create a ready-to-confirm shipment object based on existing order
		$shipment_id = intval( $_POST['shipment_id'] );
		$shipment = MFB_Shipment::get( $shipment_id );

		if ( $shipment->status != 'mfb-draft' ) die();

		$res = $shipment->destroy();

		if ( $res ) {
			$response['data'] = 'success';
			$response['shipment'] = $shipment;
		} else {
			$response['data'] = 'error';
		}

		// Whatever the outcome, send the Response back
		wp_send_json( $response );
	}

	public static function update_selected_offer () {

		$shipment_id = intval( $_POST['shipment_id'] );
		$shipment = MFB_Shipment::get( $shipment_id );

		if ( $shipment->status != 'mfb-draft' ) die();

		$offer_id = intval( $_POST['offer_id'] );
		$offer = MFB_Offer::get( $offer_id );

		if ( $offer && $shipment->quote->offers[$offer->product_code] ) {
			$shipment->offer = $offer;
			$shipment->save();
		} else {
			$shipment->offer = null;
			$shipment->save();
		}
	}

	public static function update_recipient () {

		$shipment_id = intval( $_POST['shipment_id'] );
		$shipment = MFB_Shipment::get( $shipment_id );

		if ( $shipment->status != 'mfb-draft' ) die();

		foreach( MFB_Shipment::$address_fields as $fieldname) {
			if ( isset($_POST['_shipment_recipient_'.$fieldname]) ) $shipment->recipient->$fieldname = wp_kses_post( $_POST['_shipment_recipient_'.$fieldname] );
		}

		$shipment->get_new_quote();
		$res = $shipment->save();

		if ( $res ) {
			$response['data'] = 'success';
			$response['shipment'] = $shipment;
		} else {
			$response['data'] = 'error';
		}

		// Whatever the outcome, send the Response back
		wp_send_json( $response );
	}

	public static function update_shipper () {

		$shipment_id = intval( $_POST['shipment_id'] );
		$shipment = MFB_Shipment::get( $shipment_id );

		if ( $shipment->status != 'mfb-draft' ) die();

		foreach( MFB_Shipment::$address_fields as $fieldname) {
			if ( isset($_POST['_shipment_shipper_'.$fieldname]) ) $shipment->shipper->$fieldname = wp_kses_post( $_POST['_shipment_shipper_'.$fieldname] );
		}

		$shipment->get_new_quote();
		$res = $shipment->save();

		if ( $res ) {
			$response['data'] = 'success';
			$response['shipment'] = $shipment;
		} else {
			$response['data'] = 'error';
		}

		// Whatever the outcome, send the Response back
		wp_send_json( $response );
	}
	public static function update_parcel () {

		$shipment_id = intval( $_POST['shipment_id'] );
		$shipment = MFB_Shipment::get( $shipment_id );

		if ( $shipment->status != 'mfb-draft' ) die();

		if ( $_POST['parcel_index'] == 'new' ) {
			// we are adding a new parcel
			$parcel = new stdClass();
			foreach( MFB_Shipment::$parcel_fields as $fieldname) {
				if ( isset($_POST['_parcel_new_'.$fieldname]) ) $parcel->$fieldname = wp_kses_post( $_POST['_parcel_new_'.$fieldname] );
			}
			$shipment->parcels[] = $parcel;
		} else {
			// We are updating an existing parcel
			$parcel_index = intval( $_POST['parcel_index'] );
			foreach( MFB_Shipment::$parcel_fields as $fieldname) {
				if ( isset($_POST['_parcel_'.$parcel_index.'_'.$fieldname]) ) $shipment->parcels[$parcel_index]->$fieldname = wp_kses_post( $_POST['_parcel_'.$parcel_index.'_'.$fieldname] );
			}
		}

		$shipment->get_new_quote();
		$res = $shipment->save();

		if ( $res ) {
			$response['data'] = 'success';
			$response['shipment'] = $shipment;
		} else {
			$response['data'] = 'error';
		}

		// Whatever the outcome, send the Response back
		wp_send_json( $response );
	}

	public static function delete_parcel () {

		$shipment_id = intval( $_POST['shipment_id'] );
		$shipment = MFB_Shipment::get( $shipment_id );

		if ( $shipment->status != 'mfb-draft' ) die();

		$parcel_index = intval( $_POST['parcel_index'] );

		unset( $shipment->parcels[$parcel_index] );

		$shipment->save();

		$shipment->get_new_quote();
		$res = $shipment->save();

		if ( $res ) {
			$response['data'] = 'success';
			$response['shipment'] = $shipment;
		} else {
			$response['data'] = 'error';
		}

		// Whatever the outcome, send the Response back
		wp_send_json( $response );
	}

	public static function download_labels () {

		// We create a ready-to-confirm shipment object based on existing order
		$shipment_id = intval( $_GET['shipment_id'] );
		$shipment = MFB_Shipment::get( $shipment_id );

		$booking = Lce\Resource\Order::find($shipment->api_order_uuid);
		$labels_content = $booking->labels();
		$filename = 'labels_'.$booking->id.'.pdf';

		header('Content-type: application/pdf');
		header("Content-Transfer-Encoding: binary");
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		print($labels_content);
		die();
	}

}

MFB_AJAX::init();
