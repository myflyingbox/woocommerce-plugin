<?php
/*
 * Plugin Name: My Flying Box
 * Version: 0.16
 * Plugin URI: http://www.myflyingbox.com
 * Description: Integrated Shipping services through My Flying Box API.
 * Author: Thomas Belliard (My Flying Box)
 * Author URI: http://github.com/myflyingbox
 * Requires at least: 4.0
 * Tested up to: 6.5
 *
 * Text Domain: my-flying-box
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author My Flying Box
 * @since 0.1
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once( 'includes/class-mfb-install.php' );

// Systematic trigger of install/update. The process starts with a very simple version
// compare, so the performance cost is minimal. 
add_action('admin_init', array( 'MFB_Install', 'install') );

/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) :

  /**
   * Returns the main instance of My_Flying_Box to prevent the need to use globals.
   *
   * @since  1.0.0
   * @return object My_Flying_Box
   */
  function MFB() {

    // Load plugin class files
    require_once( 'includes/class-my-flying-box.php' );
    require_once( 'includes/class-my-flying-box-settings.php' );

    // Load plugin libraries
    require_once( 'includes/class-my-flying-box-admin-api.php' );
    require_once( 'includes/class-my-flying-box-post-type.php' );
    require_once( 'includes/class-my-flying-box-taxonomy.php' );

    if ( !class_exists( 'WP_Background_Process' ) ) {
      require_once( 'includes/lib/wp-async-request.php' );
      require_once( 'includes/lib/wp-background-process.php' );
    }
    require_once( 'includes/class-mfb-bulk-order-background-process.php' );
    require_once( 'includes/class-mfb-bulk-order-return-background-process.php' );
    require_once( 'includes/class-my-flying-box-multiple-shipment.php' );

    // Load view elements
    require_once( 'includes/meta-boxes/class-mfb-meta-box-order-shipping.php' );
    require_once( 'includes/meta-boxes/class-mfb-meta-box-bulk-order.php' );

    $instance = My_Flying_Box::instance( __FILE__, '0.16' );

    return $instance;
  }

  add_action( 'woocommerce_init', 'MFB' );

endif;
