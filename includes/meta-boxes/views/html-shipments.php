<div class="mfb_shipments_wrapper">
	<table cellpadding="0" cellspacing="0" class="mfb_order_shipments">
		<thead>
			<tr>
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
			foreach( $shipments as $shipment ):
				include( 'html-shipment-line.php');
			endforeach;
		?>
		</tbody>
	</table>
	<div class="clear"></div>

	<div class="mfb-actions">
		<p class="add-shipment">
			<button type="button" class="button button-primary add-shipment"><?php _e( 'Create new shipment', 'my-flying-box' ); ?></button>
		</p>
	</div>
</div>
