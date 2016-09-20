<?php
/**
 * MetaBox to display MyFlyingBox tools on Admin order page
 *
 */

class MFB_Meta_Box_Bulk_Order {


	public static function output( ) {
		global $thebulkorder, $post;

		if ( ! is_object( $thebulkorder ) ) {
			$thebulkorder = MFB_Bulk_Order::get( $post->ID );
		}

		include( 'views/html-bulk-order-main.php' );

	}

}
