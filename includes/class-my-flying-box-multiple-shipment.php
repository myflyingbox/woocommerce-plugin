<?php

use \Lce\Lce;
use \Lce\Resource\Order;

class My_Flying_Box_Multiple_Shipment  extends WC_Shipping_Method {

	/*
	 * Ajout des différentes actions wordpress
	 */
	public function __construct() {
		add_action( 'admin_footer-edit.php', array($this,'my_flying_box_admin_footer') );
		add_action( 'admin_action_mfb_creer_expedition', array($this,'creation_expedition') );
		add_action( 'admin_action_mfb_creer_impression', array($this,'creation_impression') );
		add_action( 'admin_notices', array( $this, 'bulk_admin_notices' ) );
	}


	/*
	 * Ajout des actions dans le menu déroulant
	 */
	function my_flying_box_admin_footer()
	{
		global $post_type;
		/* Ici on prend que pour les articles et on ajoute l'opion export en haut et en bas du tableau */
		if($post_type == 'shop_order') {
			?>
				<script type="text/javascript">
					jQuery(document).ready(function() {
						jQuery('<option>').val('mfb_creer_expedition').text('Créer et commander les expeditions').appendTo("select[name='action']");
						jQuery('<option>').val('mfb_creer_expedition').text('Créer et commander les expeditions').appendTo("select[name='action2']");
						jQuery('<option>').val('mfb_creer_impression').text("Télécharger les étiquettes d'expedition").appendTo("select[name='action']");
						jQuery('<option>').val('mfb_creer_impression').text("Télécharger les étiquettes d'expedition").appendTo("select[name='action2']");
					});
				</script>
			<?php
		}
	}

	/*
	 * Fonction de création des expéditions
	 */
	function creation_expedition()
	{
		$notices = array();
		$errors = array();
		$already_shipped = array();
		$booked = array();

		foreach( $_REQUEST['post'] as $post_id ) {
			// Only booking new shipments if no existing booked shipment
			$latest_shipment = MFB_Shipment::get_last_booked_for_order( $post_id );
			$order = new WC_Order( $post_id );
			if ( $latest_shipment == null ) {
				try {
					$shipment = MFB_Shipment::create_from_order( $order );
					$shipment->place_booking();
					$booked[] = $order->id;
				} catch (Exception $e) {
					$errors[] = sprintf( __('Error while booking shipment for Order %s: %s', 'my-flying-box'), $order->id, $e->getMessage());
				}
			} else {
				$already_shipped[] = $order->id;
			}
		}
		if ( count($already_shipped) > 0) {
			$notices[] = sprintf( __('The following orders have already been shipped with MyFlyingBox: %s', 'my-flying-box'), implode(', ', $already_shipped));
		}
		if ( count($booked) > 0) {
			$notices[] = sprintf( __('Shipment has been booked for the following orders: %s', 'my-flying-box'), implode(', ', $booked));
		}

		set_transient('mfb_bulk_notices', $notices, 180);
		set_transient('mfb_bulk_errors',  $errors,  180);
	}

	/*
	 * Fonction de création des étiquettes en un seul PDF
	 */
	function creation_impression()
	{
		$uuid_list = array();
		foreach( $_REQUEST['post'] as $post_id ) {
			//$order = new WC_Order( $post_id );
			$shipment = MFB_Shipment::get_last_booked_for_order($post_id);
			if ($shipment) {
				$uuid = get_post_meta( $shipment->id, '_api_uuid', true );
				array_push($uuid_list, $uuid);
			}
		}

		if ( !empty( $uuid_list ) ){
			$labels = Order::multiple_labels($uuid_list);
			$filename = 'MFB-labels.pdf';

			header('Content-type: application/pdf');
			header("Content-Transfer-Encoding: binary");
			header('Content-Disposition: attachment; filename="'.$filename.'"');
			print($labels);
			die();
		}
	}

	public function bulk_admin_notices($message, $error = false) {
		// Bulk actions
		$errors  = get_transient('mfb_bulk_errors');
		$notices = get_transient('mfb_bulk_notices');

		if (is_array($errors)) {
			foreach($errors as $error) {
				echo '<div id="message" class="error">';
				echo "<p><strong>$error</strong></p></div>";
			}
		}
		if (is_array($notices)) {
			foreach($notices as $notice) {
				echo '<div id="message" class="updated fade">';
				echo "<p><strong>$notice</strong></p></div>";
			}
		}
		delete_transient('mfb_bulk_errors');
		delete_transient('mfb_bulk_notices');
	}


}
