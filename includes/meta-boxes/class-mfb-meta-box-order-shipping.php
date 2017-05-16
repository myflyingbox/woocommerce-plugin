<?php
/**
 * MetaBox to display MyFlyingBox tools on Admin order page
 *
 */

class MFB_Meta_Box_Order_Shipping {


	public static function output( ) {
		global $thepostid, $theorder;

		if ( ! is_object( $theorder ) ) {
			$theorder = wc_get_order( $thepostid );
		}
		$delivery_location_code = get_post_meta( $theorder->get_id(), '_mfb_delivery_location', true);
		if ( $delivery_location_code && !empty( $delivery_location_code ) ) {
			// We have a relay delivery. Extracting the details of the customer selection
			$relay = new stdClass();
			$relay->code = $delivery_location_code;
			$relay->name = get_post_meta( $theorder->get_id(), '_mfb_delivery_location_name', true);
			$relay->street = get_post_meta( $theorder->get_id(), '_mfb_delivery_location_street', true);
			$relay->postal_code = get_post_meta( $theorder->get_id(), '_mfb_delivery_location_postal_code', true);
			$relay->city = get_post_meta( $theorder->get_id(), '_mfb_delivery_location_city', true);
		} else {
			$relay = false;
		}

		$shipments = MFB_Shipment::get_all_for_order( $theorder->get_id() );

		include( 'views/html-shipments.php' );

	}

}
