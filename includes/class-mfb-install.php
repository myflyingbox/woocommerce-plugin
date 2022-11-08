<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class MFB_Install {

  # Definition of required data updates on new versions
  private static $db_updates = array(
    '0.5' => array(
      'mfb_update_05_api_v2_services'
    ),
    '0.14' => array(
      'mfb_update_014_set_parcels_insurable_value' // Insurable value and content value are now separate.
    )
  );

  public static function install () {
    
    # Initializing version comparison data
    $current_mfb_version = MFB()->_version;
    $current_db_version = get_option( 'myflyingbox_db_version', null );
    
    // Already up to date? Stop right now.
    if ( $current_db_version == $current_mfb_version ) return;

    # Loading corresponding functions
    include_once( 'mfb-update-functions.php' );

    // We don't seem to be up to date. We check whether we need to execute some update
    // functions as declared in the $db_updates array.
    foreach ( self::$db_updates as $version => $update_functions ) {
      if ( is_null( $current_db_version ) || version_compare( $current_db_version, $version, '<' ) ) {
        foreach ( $update_functions as $update_function ) {
          call_user_func( $update_function );
        }
      }
    }

    # Registering the new version number
    self::_log_version_number();
  }

  private static function _log_version_number () {
    update_option( 'myflyingbox_db_version', MFB()->_version );
  }
}