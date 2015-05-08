<?php
/**
 * Shows a shipment line
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<tr class="shipment" data-shipment_id="<?php echo $shipment->id; ?>">
  <td><?php echo $shipment->status ?></td>
  
  <td>
    <?php echo $shipment->formatted_shipper_address(); ?>
    <br/>
    <?php echo $shipment->shipper->email; ?> 
    <br/>
    <?php echo $shipment->shipper->phone; ?>
  </td>
  
  <td>
    <?php echo $shipment->formatted_recipient_address(); ?>
    <br/>
    <?php echo $shipment->recipient->email; ?> 
    <br/>
    <?php echo $shipment->recipient->phone; ?>
  </td>
  
  <td>
    <?php
      foreach( $shipment->parcels as $parcel ) {
        echo '<p>'.MFB_Shipment::formatted_parcel_line( $parcel ).'</p>';
      }
    ?>
  </td> 
  
  <td>
    <?php
      if ( $shipment->offer ) {
        echo '<div class="offer-details" data-offer_id="'.$shipment->offer->id.'">';
          echo '<p>'.$shipment->offer->product_code.' : '.$shipment->offer->formatted_price().'</p>';
          ?>
          <button type="button" class="button button-primary book-offer"><?php _e( 'Book this service', 'my-flying-box' ); ?></button>
        </div>
        <?php
      }
    ?>
  </td>
  
  <td>
    <a class="delete-shipment" href="#"></a>
  </td>  
  
</tr>
