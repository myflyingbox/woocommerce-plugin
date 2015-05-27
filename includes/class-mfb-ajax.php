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
			'book_offer'                                      => false,
			'delete_shipment'                                 => false,
			'update_shipper'                                  => false,
			'update_recipient'                                => false,
			'add_parcel'                                      => false,
			'edit_parcel'                                     => false,
			'update_parcel'                                   => false,
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

		// Getting Offer from transmitted uuid:
		$offer = MFB_Offer::get_by_uuid( $_REQUEST['k'] );

		// Extracting address and city
		$customer = WC()->session->get('customer');

		$street = $customer['shipping_address'];
		if ( ! empty($customer['shipping_address_2']) ) {
			$street .= "\n".$customer['shipping_address_2'];
		}

		$params = array(
			'street' => $street,
			'city' => $customer['shipping_city']
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
	
	public static function book_offer () {
	
		// We create a ready-to-confirm shipment object based on existing order
		$shipment_id = intval( $_POST['shipment_id'] );
		$shipment = MFB_Shipment::get( $shipment_id );
		
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
		
		$offer_id = intval( $_POST['offer_id'] );
		$offer = MFB_Offer::get( $offer_id );
		
		if ( $offer && $shipment->quote->offers[$offer->product_code] ) {
			$shipment->offer = $offer;
			$shipment->save();
		}
	}

	public static function update_recipient () {
	
		$shipment_id = intval( $_POST['shipment_id'] );
		$shipment = MFB_Shipment::get( $shipment_id );
		
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
		
		$parcel_index = intval( $_POST['parcel_index'] );
		foreach( MFB_Shipment::$parcel_fields as $fieldname) {
			if ( isset($_POST['_parcel_'.$parcel_index.'_'.$fieldname]) ) $shipment->parcels[$parcel_index]->$fieldname = wp_kses_post( $_POST['_parcel_'.$parcel_index.'_'.$fieldname] );
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
