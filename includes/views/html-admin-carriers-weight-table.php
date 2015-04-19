<?php
/**
 * Admin View: Weight options
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

		?>
		<h3><?php printf( __( 'Weight/dimensions', 'my-flying-box' ) ); ?></h3>
		<p><?php printf( __( 'When requesting a quote for a shipment, most carriers need parcel dimensions. The MyFlyingBox woocommerce shipping module uses this table to compute dimensions based on the total weight of the cart. There is no way for the module to automatically guess your final packaging based on a customer cart, so it is very important that you customize this table based on your product, average cart, and usual packaging strategy.', 'my-flying-box' ) ); ?></p>
		
		<table class="<?php echo $current_section; ?> wc_input_table widefat">
			<thead>
				<tr>

					<th>#</th>

					<th><?php _e( 'Weight (up to)', 'my-flying-box' ); ?></th>

					<th><?php _e( 'length', 'my-flying-box' ); ?></th>

					<th><?php _e( 'width', 'my-flying-box' ); ?></th>
					
					<th><?php _e( 'height', 'my-flying-box' ); ?></th>

				</tr>
				
			</thead>
			<tbody>
				<?php	foreach ( $dimensions as $key => $dimension ) {	?>
					<tr>
						<td class=""><?php echo $dimension->index; ?></td>

						<td class="dimension">
							<input type="text" name="weight_<?php echo $dimension->index; ?>" id="weight_<?php echo $dimension->index; ?>" value="<?php echo $dimension->weight_to; ?>" class="" /> <span>kg</span>
						</td>

						<td class="dimension">
							<input type="text" name="length_<?php echo $dimension->index; ?>" id="length_<?php echo $dimension->index; ?>" value="<?php echo $dimension->length; ?>" class="" /> <span>cm</span>
						</td>

						<td class="dimension">
							<input type="text" name="width_<?php echo $dimension->index; ?>" id="width_<?php echo $dimension->index; ?>" value="<?php echo $dimension->width; ?>" class="" /> <span>cm</span>
						</td>
						
						<td class="dimension">
							<input type="text" name="height_<?php echo $dimension->index; ?>" id="height_<?php echo $dimension->index; ?>" value="<?php echo $dimension->height; ?>" class="" /> <span>cm</span>
							<input type="hidden" name="id_<?php echo $dimension->index; ?>" id="id_<?php echo $dimension->index; ?>" value="<?php echo $dimension->id; ?>" />
						</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
