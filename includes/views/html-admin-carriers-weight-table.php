<?php
/**
 * Admin View: Weight options
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

		?>
		<h3><?php printf( __( 'Weight/dimensions', 'my-flying-box' ) ); ?></h3>
		<p><?php printf( __('When requesting a quote for a shipment, most carriers need parcel dimensions. There are two ways to determine the dimensions used for automatic calculation: if dimensions of the products have been defined in the product catalog, these dimensions will be used (one parcel per item in the order, with the dimensions of the item) ; if no dimensions have been defined for the items in the order, the module will determine dimensions based on the total weight of the cart and the table below, associating weights to dimensions.','my-flying-box' )); ?></p>

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
