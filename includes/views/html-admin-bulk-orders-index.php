<?php
/**
 * Admin View: Carriers
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

?>
<h1><?php _e( 'MY FLYING BOX Bulk Orders', 'my-flying-box' ); ?></h1>


<table cellpadding="0" cellspacing="0" class="mfb_bulk_orders">
  <thead>
    <tr>
      <th class="bulk-date"><?php _e( 'Created at', 'my-flying-box' ); ?></th>
      <th class="bulk-status"><?php _e( 'Status', 'my-flying-box' ); ?></th>
      <th class="bulk-order-numbers"><?php _e( 'Included orders', 'my-flying-box' ); ?></th>
      <th class="bulk-actions"><?php _e( 'Actions', 'my-flying-box' ); ?></th>

    </tr>
  </thead>
  <tbody>
  <?php
    foreach( $bulk_orders as $bulk_order ):
      include( 'html-bulk-order-index-line.php');
    endforeach;
  ?>
  </tbody>
</table>
