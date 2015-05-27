<?php
require_once('../../../../wp-config.php');
require_once(WP_PLUGIN_DIR.'/woocommerce/woocommerce.php');

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
?>
