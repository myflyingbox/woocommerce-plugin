<div class='display_<?php echo $address_type; ?>_address'>
  <?php if ( $shipment->status == 'mfb-draft' ) { ?>
    <a class="edit_<?php echo $address_type; ?>_address" href="#"><img src="<?php echo WC()->plugin_url(); ?>/assets/images/icons/edit.png" alt="<?php _e( "Edit $address_type address", 'my-flying-box' ); ?>" width="14" /></a>
  <?php } ?>
  <?php $method_name = 'formatted_'.$address_type.'_address'; echo $shipment->$method_name(); ?>
  <br/>
  <?php echo $shipment->$address_type->email; ?> 
  <br/>
  <?php echo $shipment->$address_type->phone; ?>
</div>

<div class='<?php echo $address_type; ?>_form_container' style='display: none;'>
    <p class='form_field'>
      <input name="_shipment_<?php echo $address_type; ?>_company" placeholder="<?php _e("Company", 'my-flying-box') ?>" type="text" value="<?php echo $shipment->$address_type->company; ?>"/>
    </p>
    <p class='form_field'>
      <input name="_shipment_<?php echo $address_type; ?>_name" placeholder="<?php _e("Name", 'my-flying-box') ?>" type="text" value="<?php echo $shipment->$address_type->name; ?>"/>
    </p>
    <p class='form_field'>
      <textarea name="_shipment_<?php echo $address_type; ?>_street" placeholder="<?php _e("Address", 'my-flying-box') ?>" rows="2" cols="10"><?php echo $shipment->$address_type->street; ?></textarea>
    </p>
    <p class='form_field'>
      <input name="_shipment_<?php echo $address_type; ?>_postal_code" placeholder="<?php _e("Postal code", 'my-flying-box') ?>" type="text" value="<?php echo $shipment->$address_type->postal_code; ?>"/>
    </p>
    <p class='form_field'>
      <input name="_shipment_<?php echo $address_type; ?>_city" placeholder="<?php _e("City", 'my-flying-box') ?>" type="text" value="<?php echo $shipment->$address_type->city; ?>"/>
    </p>
    <p class='form_field'>
      <input name="_shipment_<?php echo $address_type; ?>_state" placeholder="<?php _e("State/Region", 'my-flying-box') ?>" type="text" value="<?php echo $shipment->$address_type->state; ?>"/>
    </p>
    <p class='form_field'>
      <?php $countries = WC()->countries->__get( 'countries' ) ?>
      <select name="_shipment_<?php echo $address_type; ?>_country_code">
        <option value='<?php echo $shipment->$address_type->country_code ?>' selected><?php echo $countries[$shipment->$address_type->country_code] ?></option>
        <option value='' disabled></option>
        <?php foreach ($countries as $code => $name) {
          echo "<option value='$code'>$name</option>";
        }
        ?>
      </select>
    </p>
    <p class='form_field'>
      <input name="_shipment_<?php echo $address_type; ?>_phone" placeholder="<?php _e("Phone number", 'my-flying-box') ?>" type="text" value="<?php echo $shipment->$address_type->phone; ?>"/>
    </p>
    <p class='form_field'>
      <input name="_shipment_<?php echo $address_type; ?>_email" placeholder="<?php _e("Email", 'my-flying-box') ?>" type="text" value="<?php echo $shipment->$address_type->email; ?>"/>
    </p>
    
    <button class="button cancel_<?php echo $address_type; ?>_form"><?php _e( 'Cancel', 'my-flying-box' ) ?></button>
    <button class="button submit_<?php echo $address_type; ?>_form"><?php _e( 'Submit', 'my-flying-box' ) ?></button>
</div>
