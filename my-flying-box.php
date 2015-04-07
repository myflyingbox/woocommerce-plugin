<?php
/*
 * Plugin Name: My Flying Box
 * Version: 0.1
 * Plugin URI: http://www.myflyingbox.com
 * Description: Integrated Shipping services through My Flying Box API.
 * Author: Thomas Belliard (My Flying Box)
 * Author URI: http://github.com/tbelliard
 * Requires at least: 4.0
 * Tested up to: 4.0
 *
 * Text Domain: my-flying-box
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author My Flying Box
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Load plugin class files
require_once( 'includes/class-my-flying-box.php' );
require_once( 'includes/class-my-flying-box-settings.php' );

// Load plugin libraries
require_once( 'includes/lib/class-my-flying-box-admin-api.php' );
require_once( 'includes/lib/class-my-flying-box-post-type.php' );
require_once( 'includes/lib/class-my-flying-box-taxonomy.php' );

/**
 * Returns the main instance of My_Flying_Box to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object My_Flying_Box
 */
function My_Flying_Box () {
	$instance = My_Flying_Box::instance( __FILE__, '1.0.0' );

	if ( is_null( $instance->settings ) ) {
		$instance->settings = My_Flying_Box_Settings::instance( $instance );
	}

	return $instance;
}

My_Flying_Box();

My_Flying_Box()->register_post_type( 'mfb_shipment', __( 'Shipments', 'my-flying-box' ), __( 'Shipment', 'my-flying-box' ) );
