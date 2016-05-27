<?php
/*
 * Plugin Name: My Flying Box
 * Version: 0.2
 * Plugin URI: http://www.myflyingbox.com
 * Description: Integrated Shipping services through My Flying Box API.
 * Author: Thomas Belliard (My Flying Box)
 * Author URI: http://github.com/myflyingbox
 * Requires at least: 4.0
 * Tested up to: 4.4
 *
 * Text Domain: my-flying-box
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author My Flying Box
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) :

  require_once (dirname(__FILE__).'/../woocommerce/woocommerce.php');


  // Load plugin class files
  require_once( 'includes/class-my-flying-box.php' );
  require_once( 'includes/class-my-flying-box-settings.php' );

  // Load plugin libraries
  require_once( 'includes/class-my-flying-box-admin-api.php' );
  require_once( 'includes/class-my-flying-box-post-type.php' );
  require_once( 'includes/class-my-flying-box-taxonomy.php' );
  require_once( 'includes/class-my-flying-box-multiple-shipment.php' );

  // Load view elements
  require_once( 'includes/meta-boxes/class-mfb-meta-box-order-shipping.php' );

/**
 * Returns the main instance of My_Flying_Box to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object My_Flying_Box
 */
function MFB() {
  
	$instance = My_Flying_Box::instance( __FILE__, '1.0.0' );

	return $instance;
}

MFB();

endif;
