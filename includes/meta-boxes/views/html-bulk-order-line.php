<?php
/**
 * Shows a shipment line
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$shipment = $thebulkorder->get_shipment_for_order( $wc_order->get_id() );

if ( $shipment ) {

?>
<tr class="shipment" data-shipment_id="<?php echo $shipment->id; ?>">

  <td><?php
    echo '<a href="' . admin_url( 'post.php?post=' . absint( $wc_order->get_id() ) . '&action=edit' ) . '" class="row-title">';
    echo $wc_order->get_order_number();
    echo '</a>';
  ?></td>

  <td><?php echo $shipment->status ?></td>

  <td>
    <?php
      $address_type = 'shipper';
      include( 'html-shipment-address-cell.php');
    ?>
  </td>

  <td>
    <?php
      $address_type = 'recipient';
      include( 'html-shipment-address-cell.php');
    ?>
  </td>

  <td>
    <?php
      $parcels = $shipment->parcels;
      foreach( $parcels as $key => $parcel ) {
        ?>
        <div class="parcel" data-parcel_index="<?php echo $key; ?>">
          <div class="parcel-data">
            <p>
              <?php echo MFB_Shipment::formatted_parcel_line( $parcel ); ?>
            </p>
          </div>
        </div>
      <?php
      }
      ?>
  </td>

  <td>
    <?php if ( $shipment->status == 'mfb-processing' ) {

      _e('This shipment is being processed. Please wait and reload the page in a few seconds.', 'my-flying-box');

    } elseif ( $shipment->status == 'mfb-draft' ) { ?>

    <div class="mfb-available-offers">
      <?php $offers = $shipment->quote->offers ?>
        <select name="_mfb_selected_offer" class="offer-selector" style="width: 250px; font-size: 0.9em;">
        <?php foreach ($offers as $offer) {
          echo "<option data-offer_id='".$offer->id."' value='".$offer->product_code."'";
          if ( $shipment->offer && $shipment->offer->product_code == $offer->product_code ) echo " selected";
          echo ">".$offer->product_name." - ".$offer->formatted_price()."</option>";
        }
        ?>
      </select>

      <?php
        if ($shipment->offer && true == $shipment->offer->pickup) {
          // A pickup is required, we must select the date
          echo '<p>';
          _e( 'Select pickup date:', 'my-flying-box' );
          echo '<br/>';
        ?>
        <select name="_mfb_pickup_date" class="pickup-date-selector" style="width: 250px; font-size: 0.9em;">
        <?php
        foreach ( $shipment->offer->collection_dates as $date ) {
          echo "<option value='".$date->date."'>".$date->date."</option>";
        }
        ?>
      </select>
      <?php
        echo '</p>';
        }
      ?>

      <?php
        if ($shipment->offer && true == $shipment->offer->relay) {
          // A pickup is required, we must select the date
          echo '<p>';
          _e( 'Select delivery location:', 'my-flying-box' );
          echo '<br/>';
        ?>
        <select name="_mfb_relay_code" class="delivery-location-selector" style="width: 250px; font-size: 0.9em;">
        <?php
        $params = array(
          'street' => $shipment->recipient->street,
          'city' => $shipment->recipient->city
        );
        $locations = $shipment->offer->get_delivery_locations( $params );

        foreach ( $locations as $location ) {
          echo "<option value='".$location->code."'";
          $preferred_location = get_post_meta( $theorder->get_id(), '_mfb_delivery_location');
          if ( $location->code == $preferred_location[0] ) {
            echo " selected";
          }
          echo ">";
          echo $location->code . ' ' . $location->company . ' / ' . $location->street . ' / ' . $location->postal_code.' '.$location->city;
          echo "</option>";
        }
        ?>
      </select>
      <?php
        echo '</p>';
        }
      ?>

      <br/>
      <button type="button" class="button button-primary book-offer"><?php _e( 'Book this service', 'my-flying-box' ); ?></button>
      <br/>
      <br/>
      <?php
        if ( $shipment->last_booking_error ) {
          echo $shipment->last_booking_error;
        }
      ?>
    </div>
        <?php

    } elseif ( $shipment->status == 'mfb-booked' ) {
    ?>
      <div class="mfb-booked-offer">
        <div class="offer-details" data-offer_id="<?php echo $shipment->offer->id ?>">
          <p><?php echo $shipment->offer->product_name ?> (<?php echo $shipment->offer->formatted_price(); ?>)</p>
          <button type="button" class="button button-primary download-labels"><?php _e( 'Download labels', 'my-flying-box' ); ?></button>
        </div>
        <?php
          $tracking_links = $shipment->tracking_links();
          if ( count ( $tracking_links ) > 0 ) {
            echo '<div class="mfb-order-tracking">';
            echo '<p>'.__( 'Tracking:', 'my-flying-box' );
            foreach( $tracking_links as $link ) {
            ?>
              <br/><a href='<?php echo $link['link']; ?>' target='_blank'><?php echo $link['code']; ?></a>
            <?php
            }
            echo '</p></div>';
          }
        ?>

        <?php
        if ( $shipment->delivery_location ) {
          echo '<p>';
          _e('Delivery location:', 'my-flying-box');
          echo '<br/>';
          echo $shipment->delivery_location->company.' ('.$shipment->delivery_location->code.')';
          echo '<br/>';
          echo $shipment->delivery_location->street;
          echo '<br/>';
          echo $shipment->delivery_location->postal_code.' '.$shipment->delivery_location->city;
          echo '</p>';
        }

        ?>

      </div>
    <?php
    }

    ?>
  </td>

  <td>
    <?php if ( $shipment->status == 'mfb-draft' ) { ?>
    <a class="delete-shipment" href="#"></a>
    <?php } ?>
  </td>

</tr>
<?php

} else {

  ?>
<tr class="shipment" data-order_id="<?php echo $wc_order->get_id(); ?>">

  <td><?php echo $wc_order->get_order_number(); ?></td>

  <td colspan="6"><?php
		if ( $thebulkorder->return_shipments ) {
			if (MFB_Shipment::get_last_booked_for_order( $wc_order->get_id(), true ) != null) {
	      _e('This order already has a booked return shipment. You cannot create a new return shipment through bulk processing.', 'my-flying-box');
	    } else {
	      _e('No return shipment has been booked yet for this order.', 'my-flying-box');
	    }
		} else {
			if (MFB_Shipment::get_last_booked_for_order( $wc_order->get_id() ) != null) {
	      _e('This order already has a booked shipment. You cannot create a new shipment through bulk processing.', 'my-flying-box');
	    } else {
	      _e('No shipment has been generated yet for this order.', 'my-flying-box');
	    }
		}
    ?>
    </td>
</tr>
  <?php
}

?>
