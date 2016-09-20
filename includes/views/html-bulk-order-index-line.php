<?php
/**
 * Shows a shipment line
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<tr class="bulk-order" data-bulk_order_id="<?php echo $bulk_order->id; ?>">

  <td>
    <?php
      printf( ' ' . _x( '%s @ %s', 'on date at time', 'my-flying-box' ), date_i18n( get_option( 'date_format' ), strtotime( $bulk_order->post_date ) ), date_i18n( get_option( 'time_format' ), strtotime( $bulk_order->post_date ) ) );
    ?>
  </td>

  <td><?php echo $bulk_order->status ?></td>

  <td>
  <?php
    $refs = array();
    foreach( $bulk_order->orders as $order ):
      $refs[] = $order->get_order_number();
    endforeach;
    echo implode(', ', $refs);
  ?>
  </td>

  <td>
    <?php if ( $bulk_order->status == 'mfb-draft' ) { ?>
    <a class="delete-bulk_order" href="#"></a>
    <?php } ?>
  </td>

</tr>
