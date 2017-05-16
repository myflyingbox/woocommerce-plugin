<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class MFB_Install {

  # Definition of required data updates on new versions
  private static $db_updates = array(
    '0.5' => array(
      'mfb_update_05_api_v2_services'
    )
  );

  public static function install () {

    # Loading corresponding functions
    include_once( 'mfb-update-functions.php' );

    # Initializing version comparison data
    $current_mfb_version = MFB()->_version;

    # Version 0.5 was the first to introduce the persistence of DB version.
    # So we force it at 0.4. This will force the execution of the update
    # script even for new install of 0.5, but this is not a problem in this case.

    if ( $current_mfb_version == '0.5' ) {
      $current_db_version = '0.4';
    } else {
      $current_db_version  = get_option( 'myflyingbox_db_version', null );
    }

    if ( !is_null( $current_db_version ) && version_compare( $current_db_version, max( array_keys( self::$db_updates ) ), '<' )) {
      # We have an outdated version, and we have some available updates
      foreach ( self::$db_updates as $version => $update_functions ) {
        if ( version_compare( $current_db_version, $version, '<' ) ) {
          foreach ( $update_functions as $update_function ) {
            call_user_func( $update_function );
          }
        }
      }
    }

    # Registering the new version number
    self::_log_version_number();
  }

  private static function _log_version_number () {
    delete_option( 'myflyingbox_db_version' );
    add_option( 'myflyingbox_db_version', MFB()->_version );
  }

}