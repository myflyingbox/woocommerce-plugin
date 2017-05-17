<?php
/**
 * MyFlyingBox updates
 *
 * Functions for updating data when installing a new version
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

function mfb_update_05_api_v2_services() {
  global $wpdb;

  $new_services = array(
    'lce_blue_economy' => array('sda_standard','sda'),
    'lce_blue_priority' => array('usps_priority_domestic','usps'),
    'lce_blue_priority_express' => array('usps_priority_express','usps'),
    'lce_brown_economy' => array('ups_standard','ups'),
    'lce_brown_express' => array('ups_express_saver','ups'),
    'lce_brown_express_09' => array('ups_express_plus','ups'),
    'lce_brown_express_12' => array('ups_express','ups'),
    'lce_gray_economy' => array('parcelforce_euro_priority','parcelforce'),
    'lce_gray_economy_dropoff' => array('bpost_bpack_world_business','bpost'),
    'lce_gray_express' => array('bpost_bpack_24_pro','bpost'),
    'lce_green_economy' => array('zeleris_internacional_carretera','zeleris'),
    'lce_green_express' => array('zeleris_internacional_aereo','zeleris'),
    'lce_green_express_10' => array('zeleris_zeleris_10 ','zeleris'),
    'lce_green_express_14' => array('zeleris_zeleris_14 ','zeleris'),
    'lce_purple_economy' => array('fedex_economy','fedex'),
    'lce_purple_express' => array('fedex_international_priority','fedex'),
    'lce_red_economy' => array('dhl_economy_select','dhl'),
    'lce_red_express' => array('dhl_domestic_express_18','dhl'),
    'lce_red_express_09' => array('dhl_express_09','dhl'),
    'lce_red_express_12' => array('dhl_express_12','dhl'),
    'lce_yellow_economy' => array('chronopost_classic_international','chronopost'),
    'lce_yellow_economy_pickup' => array('chronopost_classic_international_pickup','chronopost'),
    'lce_yellow_express' => array('chronopost_chrono_express_international','chronopost'),
    'lce_yellow_express_13' => array('chronopost_chrono_13','chronopost'),
    'lce_yellow_express_13_pickup' => array('chronopost_chrono_13_pickup','chronopost'),
    'lce_yellow_express_pickup' => array('chronopost_chrono_express_international_pickup','chronopost'),
    'lce_yellow_express_shop' => array('chronopost_chrono_relais','chronopost'),
    'lce_yellow_express_shop_pickup' => array('chronopost_chrono_relais_pickup','chronopost')
  );

  $carriers = MFB_Carrier::get_all();

  foreach($carriers as $carrier) {
    if ( array_key_exists( $carrier->code, $new_services ) ) {
      $new_code = trim( $new_services[$carrier->code][0] );
      $old_code = $carrier->code;

      // First, we get all instance_ids of the shipping method
      $shipping_method_instances_rows = $wpdb->get_results( "
        SELECT instance_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods
        WHERE method_id = '{$old_code}'
      " );

      // Now we can update the settings of the shipping method instance
      foreach ( $shipping_method_instances_rows as $shipping_method_instance_row ) {
        $instance_id = $shipping_method_instance_row->instance_id;
        $old_setting_string = 'woocommerce_'.$old_code.'_'.$instance_id.'_settings';
        $new_setting_string = 'woocommerce_'.$new_code.'_'.$instance_id.'_settings';
        $wpdb->query( "UPDATE {$wpdb->prefix}options
                       SET option_name = '{$new_setting_string}'
                       WHERE option_name = '{$old_setting_string}';
                    " );
      }

      update_post_meta( $carrier->id, '_code', $new_code );
      $wpdb->query( "UPDATE {$wpdb->prefix}woocommerce_shipping_zone_methods
                     SET method_id = '{$new_code}'
                     WHERE method_id = '{$old_code}';
                  " );
    }
  }

  // We try to refresh the carriers from API. But we do not necessarily have a working access,
  // so we do not raise any exceptions in case this fails.
  try {
    MFB_Carrier::refresh_from_api();
  } catch (Exception $e) {
    // Do nothing...
  }


  return true;
}