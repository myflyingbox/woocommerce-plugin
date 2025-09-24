<?php
/*
 * Plugin Name: My Flying Box
 * Version: 0.18.1
 * Plugin URI: http://www.myflyingbox.com
 * Description: Integrated Shipping services through My Flying Box API.
 * Author: Thomas Belliard (My Flying Box)
 * Author URI: http://github.com/myflyingbox
 * Requires at least: 6.0
 * Tested up to: 6.8
 *
 * Text Domain: my-flying-box
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author My Flying Box
 * @since 0.1
 */

if (! defined('ABSPATH')) exit;

require_once('includes/class-mfb-install.php');

// Systematic trigger of install/update. The process starts with a very simple version
// compare, so the performance cost is minimal. 
add_action('admin_init', array('MFB_Install', 'install'));

/**
 * Check if WooCommerce is active
 **/
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) :

  if (!function_exists("log_trace")) {

    function log_trace($message = '', $file = '')
    {
      if (!$file) {
        $file = plugin_dir_path(__FILE__) . 'logi.txt';
      }
      $trace = debug_backtrace();
      if ($message) {
        error_log($message, 3, $file);
      }
      $caller = array_shift($trace);
      $function_name = $caller['function'];
      error_log(sprintf('%s: Called from %s:%s', $function_name, $caller['file'], $caller['line']) . "\n", 3, $file);
      foreach ($trace as $entry_id => $entry) {
        $entry['file'] = $entry['file'] ?: '-';
        $entry['line'] = $entry['line'] ?: '-';
        if (empty($entry['class'])) {
          error_log(sprintf('%s %3s. %s() %s:%s', $function_name, $entry_id + 1, $entry['function'], $entry['file'], $entry['line']) . "\n", 3, $file);
        } else {
          error_log(sprintf('%s %3s. %s->%s() %s:%s', $function_name, $entry_id + 1, $entry['class'], $entry['function'], $entry['file'], $entry['line']) . "\n", 3, $file);
        }
      }
      error_log("\n\n", 3, $file);
    }
  }

  add_action('woocommerce_thankyou', 'mfb_save_extended_cover_on_thankyou', 10, 1);

  function mfb_save_extended_cover_on_thankyou($order_id)
  {
    if (!$order_id) return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    $extended_cover = WC()->session->get('myflyingbox_extended_cover');

    if ($extended_cover === 'yes') {
      $order->update_meta_data('_myflyingbox_extended_cover', 'yes');
    } else {
      $order->update_meta_data('_myflyingbox_extended_cover', 'no');
    }

    $order->save();

    WC()->session->__unset('myflyingbox_extended_cover');
  }

  add_filter('woocommerce_cart_shipping_method_full_label', 'add_logo_method_expedition', 10, 2);
  add_filter('woocommerce_review_order_shipping_method', 'add_logo_method_expedition', 10, 2);

  function add_logo_method_expedition($label, $method) {
      $method_id = $method->id;
      if (trim($method_id) && str_ends_with($method_id, '_no_cover')) {
        $method_id = substr($method_id, 0, -strlen('_no_cover'));
      } elseif (trim($method_id) && str_ends_with($method_id, '_cover')) {
        $method_id = substr($method_id, 0, -strlen('_cover'));
      }

      $data_store = WC_Data_Store::load('shipping-zone');
      $raw_zones  = $data_store->get_zones();
      $zones      = array();

      foreach ($raw_zones as $raw_zone) {
        $zones[] = new WC_Shipping_Zone($raw_zone);
      }

      $zones[] = new WC_Shipping_Zone(0);
      foreach ( WC()->shipping()->load_shipping_methods() as $method ) {		

        foreach ( $zones as $zone ) {

          $shipping_method_instances = $zone->get_shipping_methods();

          foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

            if ( $shipping_method_instance->id !== $method->id ) {
              continue;
            }
            if($method_id == $method->id) {
              $settings = get_option("woocommerce_{$method_id}_{$shipping_method_instance_id}_settings", []);
              $logo_url = !empty($settings['carrier_logo']) ? $settings['carrier_logo'] : wc_placeholder_img_src();
              break;
            }
          }
        }
      }

      if (isset($logo_url)) {
          $img = '<img src="' . esc_url($logo_url) . '" alt="" style="height: 45px; vertical-align: middle; margin-right: 8px;">';
          return $img . $label;
      }

      return $label;
  }

  add_action('wp_ajax_get_shipping_method_logo', 'mfb_get_shipping_method_logo');
  add_action('wp_ajax_nopriv_get_shipping_method_logo', 'mfb_get_shipping_method_logo');

  function mfb_get_shipping_method_logo()
  {
    if (empty($_POST['method_id'])) {
      wp_send_json_error('Missing method ID');
    }

    $method_id = sanitize_text_field($_POST['method_id']);
    if (trim($method_id) && str_ends_with($method_id, '_no_cover')) {
      $method_id = substr($method_id, 0, -strlen('_no_cover'));
    } elseif (trim($method_id) && str_ends_with($method_id, '_cover')) {
      $method_id = substr($method_id, 0, -strlen('_cover'));
    }

    $data_store = WC_Data_Store::load('shipping-zone');
    $raw_zones  = $data_store->get_zones();
    $zones      = array();

    foreach ($raw_zones as $raw_zone) {
      $zones[] = new WC_Shipping_Zone($raw_zone);
    }

    $zones[] = new WC_Shipping_Zone(0);
    foreach ( WC()->shipping()->load_shipping_methods() as $method ) {		

			foreach ( $zones as $zone ) {

				$shipping_method_instances = $zone->get_shipping_methods();

				foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

					if ( $shipping_method_instance->id !== $method->id ) {
						continue;
					}
          if($method_id == $method->id) {
            $settings = get_option("woocommerce_{$method_id}_{$shipping_method_instance_id}_settings", []);
            $logo_url = !empty($settings['carrier_logo']) ? $settings['carrier_logo'] : wc_placeholder_img_src();
            break;
          }
        }
      }
    }

    wp_send_json_success([
      'logo' => esc_url($logo_url),
    ]);
  }

  add_action('wp_ajax_get_delivery_location', 'mfb_get_delivery_location');
  add_action('wp_ajax_nopriv_get_delivery_location', 'mfb_get_delivery_location');

  function mfb_get_delivery_location()
  {
    if (empty($_POST['method_id'])) {
      wp_send_json_error('Missing method ID');
    }
    $location = "";
    $method_id = sanitize_text_field($_POST['method_id']);
    if (trim($method_id) && str_ends_with($method_id, '_no_cover')) {
      $method_id = substr($method_id, 0, -strlen('_no_cover'));
    } elseif (trim($method_id) && str_ends_with($method_id, '_cover')) {
      $method_id = substr($method_id, 0, -strlen('_cover'));
    }

    $data_store = WC_Data_Store::load('shipping-zone');
    $raw_zones  = $data_store->get_zones();
    $zones      = array();

    foreach ($raw_zones as $raw_zone) {
      $zones[] = new WC_Shipping_Zone($raw_zone);
    }

    $zones[] = new WC_Shipping_Zone(0);
    foreach ( WC()->shipping()->load_shipping_methods() as $method ) {		

			foreach ( $zones as $zone ) {

				$shipping_method_instances = $zone->get_shipping_methods();

				foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

					if ( $shipping_method_instance->id !== $method->id ) {
						continue;
					}
          if($method_id == $method->id) {
            ob_start();
            $carrier = MFB_Carrier::get_by_code( $method_id );
            $instance_id = $shipping_method_instance_id;
            $full_label = "";
            if ($carrier->shop_delivery) {
                $quote = MFB_Quote::get( WC()->session->get('myflyingbox_shipment_quote_id') );
                $full_label .= '<div data-mfb-action="select-location" data-mfb-method-id="'.$method->id.'" data-mfb-instance-id="'.$instance_id.'" data-mfb-offer-uuid="'.$quote->offers[$method->id]->api_offer_uuid.'" id="locationselector">'.__( 'Choose a location', 'my-flying-box' ).'</div>';
                $full_label .=  '<div style="display:none;" id="ilbl_'.$method->id.'">'.__( 'Selected ', 'my-flying-box' ).' : <span id="mfb-location-client_'.$method->id.'"></span></div>';
                $full_label .=  '<div id="input_'.$method->id.'"></div>';
			      }
            $full_label .=  '<span data-mfb-method-input-id="'.$method->id.'" ></span>';
            echo $full_label;
            $location = ob_get_clean();
            break;
          }
        }
      }
    }

    wp_send_json_success([
      'location' => $location,
    ]);
  }

add_filter('script_loader_tag', 'add_async_defer_to_google_maps', 10, 3);
function add_async_defer_to_google_maps($tag, $handle, $src) {
    if ($handle === 'google-api-grouped') {
        return '<script src="' . esc_url($src) . '" id="'.$handle.'" async defer></script>';
    }
    return $tag;
}
  
  /**
   * Returns the main instance of My_Flying_Box to prevent the need to use globals.
   *
   * @since  1.0.0
   * @return object My_Flying_Box
   */
  function MFB()
  {

    // Load plugin class files
    require_once('includes/class-my-flying-box.php');
    require_once('includes/class-my-flying-box-settings.php');

    // Load plugin libraries
    require_once('includes/class-my-flying-box-admin-api.php');
    require_once('includes/class-my-flying-box-post-type.php');
    require_once('includes/class-my-flying-box-taxonomy.php');

    if (!class_exists('WP_Background_Process')) {
      require_once('includes/lib/wp-async-request.php');
      require_once('includes/lib/wp-background-process.php');
    }
    require_once('includes/class-mfb-bulk-order-background-process.php');
    require_once('includes/class-mfb-bulk-order-return-background-process.php');
    require_once('includes/class-my-flying-box-multiple-shipment.php');

    // Load view elements
    require_once('includes/meta-boxes/class-mfb-meta-box-order-shipping.php');
    require_once('includes/meta-boxes/class-mfb-meta-box-bulk-order.php');

    $instance = My_Flying_Box::instance(__FILE__, '0.18.1');

    return $instance;
  }

  add_action('woocommerce_init', 'MFB');

endif;
