<div class="mfb_bulk_order_wrapper" id="myflyingbox-bulk-shipments">
	<table cellpadding="0" cellspacing="0" class="mfb_bulk_order">
		<thead>
			<tr>
        <th class="order"><?php _e( 'Order', 'my-flying-box' ); ?></th>
				<th class="shipment-status"><?php _e( 'Status', 'my-flying-box' ); ?></th>
				<th class="shipment-shipper"><?php _e( 'From', 'my-flying-box' ); ?></th>
				<th class="shipment-recipient"><?php _e( 'To', 'my-flying-box' ); ?></th>
				<th class="shipment-parcels"><?php _e( 'Parcels', 'my-flying-box' ); ?></th>
				<th class="shipment-offer"><?php _e( 'Selected service', 'my-flying-box' ); ?></th>
				<th class="shipment-actions"></th>
			</tr>
		</thead>
		<tbody>
		<?php
			foreach( $thebulkorder->orders as $wc_order ):
				include( 'html-bulk-order-line.php');
			endforeach;
		?>
		</tbody>
	</table>
</div>
