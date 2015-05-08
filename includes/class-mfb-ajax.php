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
			'delete_shipment'                                 => false
			
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
		$error = false;
		
		if ( $shipment->offer->id == $offer_id ) {
			$shipment->place_booking();
		} else {
			$error = true;
		}
		
		if ( ! $error ) {
			$response['data'] = 'success';
		} else {
			$response['data'] = 'error';
		}
		
		// Whatever the outcome, send the Response back
		wp_send_json( $response );
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
	
}

MFB_AJAX::init();
