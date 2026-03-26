<div class="mfb_shipments_wrapper">
	<?php
	if ( $relay ) {
		echo '<p>';
		_e( 'Relay selected by the customer:', 'my-flying-box' );
		echo '<br/>';
		echo $relay->name.' ('.$relay->code.')';
		echo '<br/>';
		echo $relay->street;
		echo '<br/>';
		echo $relay->postal_code.' '.$relay->city;
		echo '</p>';
	}

	?>
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
			<button type="button" class="button button-primary add-shipment" data-order_id="<?php echo $theorder->get_id() ?>"><?php _e( 'Create new shipment', 'my-flying-box' ); ?></button>
		</p>
		<?php
		$sync_behavior = get_option( 'mfb_dashboard_sync_behavior', 'never' );
		if ( in_array( $sync_behavior, array( 'on_demand', 'always' ), true ) ) : ?>
		<p class="sync-to-dashboard">
			<button type="button" class="button mfb-sync-to-dashboard" data-order_id="<?php echo $theorder->get_id() ?>"><?php _e( 'Sync to dashboard', 'my-flying-box' ); ?></button>
			<span class="mfb-sync-status"></span>
		</p>
		<?php endif; ?>
	</div>
</div>
