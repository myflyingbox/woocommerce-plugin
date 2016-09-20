<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'MFB_Admin_Menus' ) ) :

class MFB_Admin_Menus {

	/**
	 * Hook in tabs.
	 */
	public function __construct() {
		// Add menus
		add_action( 'admin_menu', array( $this, 'mfb_menu' ), 30 );
	}

	/**
	 * Add menu item
	 */
	public function mfb_menu() {

		add_menu_page( __( 'My Flying Box', 'my-flying-box' ),  __( 'My Flying Box', 'my-flying-box' ) , 'manage_options', 'my-flying-box', null, null, '56' );

    add_submenu_page( 'my-flying-box', __( 'My Flying Box Settings', 'my-flying-box' ),  __( 'Settings', 'my-flying-box' ) , 'manage_options', 'my-flying-box-settings', array( $this, 'settings_page' ) );

    add_submenu_page( 'my-flying-box', __( 'My Flying Box Bulk Shipments', 'my-flying-box' ),  __( 'Bulk Shipments', 'my-flying-box' ) , 'manage_options', 'my-flying-box-bulk-shipments', array( $this, 'bulk_shipments_page' ) );
	}

	/**
	 * Init the settings page
	 */
	public function settings_page() {

		if ( ! class_exists( 'My_Flying_Box_Settings' ) ) {
			include dirname(__FILE__) .'/class-my-flying-box-settings.php';
		}
		My_Flying_Box_Settings::output();
	}

  public function bulk_shipments_page() {

    if ( ! class_exists( 'My_Flying_Box_Multiple_Shipment' ) ) {
      include dirname(__FILE__) .'/class-my-flying-box-multiple-shipment.php';
    }
    My_Flying_Box_Multiple_Shipment::output();
  }

}

endif;

return new MFB_Admin_Menus();
