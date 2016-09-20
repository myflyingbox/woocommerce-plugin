<?php

if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('MFB_Download_Labels')) {

class MFB_Download_Labels {
  static function on_load() {
    add_action('admin_post_mfb_download_labels',array(__CLASS__,'download_labels'));
  }

  static function download_labels() {
    global $pagenow;

    if ($pagenow=='admin-post.php' &&
        isset($_GET['action']) &&
        $_GET['action'] == 'mfb_download_labels' &&
        isset($_GET['shipment_id'])) {


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
      exit();
    }
  }
}
MFB_Download_Labels::on_load();
}
