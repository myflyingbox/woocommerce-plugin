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
	
		$shipments = MFB_Shipment::get_all_for_order( $theorder->id );
		
		include( 'views/html-shipments.php' );
		
	}

}
