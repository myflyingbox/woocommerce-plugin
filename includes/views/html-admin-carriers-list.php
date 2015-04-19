<?php
/**
 * Admin View: Carriers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
		<br/><input type="submit" class="button-primary" name="refresh" value="<?php _e( 'Reload carriers from API', 'my-flying-box' ); ?>">
		<?php _e( 'By clicking on this link, you ensure the carrier list is up to date.', 'my-flying-box' ); ?>
		
		<h3><?php printf( __( 'Carrier services', 'my-flying-box' ) ); ?></h3>
		<p><?php printf( __( 'Some of these services require the use of dimensional weight. You can define a table to associate a set of dimensions based on the total weight of a cart in the tab "Weight options". Indicate dimensions that match your usual parcels. Please note that if the dimensions and weight are lower than reality, you may be invoiced extra charges.', 'my-flying-box' ) ); ?></p>
		
		
		<table class="mfb_carrier_list <?php echo $current_section; ?> wc_input_table widefat">
			<thead>
				<tr>

					<th><?php _e( 'Offers', 'my-flying-box' ); ?></th>

					<th><?php _e( 'Description', 'my-flying-box' ); ?></th>

					<th><?php _e( 'Status', 'my-flying-box' ); ?></th>
					
					<th><?php _e( 'Edition', 'my-flying-box' ); ?></th>

				</tr>
			</thead>
			<tbody>
				<?php foreach ( $carriers as $carrier ) { 
				?>
					<tr id="<?php echo $carrier->code; ?>" class="<?php echo ( true == $carrier->active ? 'active' : 'inactive' ); ?>">
						<td class="products">
							<div class="name"><?php echo $carrier->name ?>
                <br/>
                <small><?php echo $carrier->code; ?></small>
              </div> 				
						</td>

						<td class="description"><?php echo $carrier->description; ?></td>

						<td class="status">
							<span>
								<input type="checkbox" name="services[]" value="<?php echo $carrier->code; ?>" id="" <?php if ( true == $carrier->active ) { echo 'checked="checked"'; } ?> />
							</span>
						</td>
						
						<td class="status" >
							<?php if ( true == $carrier->active ) { ?>
								<a target="_blank" href="<?php echo admin_url('admin.php?page=wc-settings&tab=shipping&section='.strtolower($carrier->code))?>">
									<?php _e( 'Edit', 'my-flying-box' ); ?>
								</a>
							<?php } else {?>
							-
							<?php } ?>
						</td>						
					</tr>
				<?php	}	?>
			</tbody>
		</table>
