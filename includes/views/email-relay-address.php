<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( count($addresses) > 0 ) {
	echo '<h2>'.__('Relay delivery address','my-flying-box').'</h2>';
	if ( count($addresses) == 1 ) {
		echo '<p>'.__('Your shipment will be delivered at the following address:','my-flying-box').'</p>';
	} else {
		echo '<p>'.__('Your order has been split and shipped to several addresses, listed below. Please check tracking information.','my-flying-box').'</p>';
	}
	foreach( $addresses as $loc ) {
		echo '<p>';
		echo $loc->company;
		echo '<br/>';
		echo $loc->street;
		echo '<br/>';
		echo $loc->postal_code.' '.$loc->city;
		echo '</p>';
	}
}

?>
