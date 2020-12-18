<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class My_Flying_Box_Multiple_Shipment  extends WC_Shipping_Method {

	/*
	 * Ajout des différentes actions wordpress
	 */
	public function __construct() {
		add_action( 'admin_footer-edit.php', array($this,'my_flying_box_admin_footer') );
		add_action( 'admin_action_mfb_creer_expedition', array($this,'creation_expedition') );
		add_action( 'admin_action_mfb_creer_impression', array($this,'creation_impression') );
		add_action( 'admin_action_mfb_creer_retour', array($this,'creation_expedition_retour') );
		add_action( 'admin_action_mfb_creer_impression_retour', array($this,'creation_impression_retour') );
		add_action( 'admin_notices', array( $this, 'bulk_admin_notices' ) );
	}


	/*
	 * Ajout des actions dans le menu déroulant
	 */
	function my_flying_box_admin_footer()
	{
		global $post_type;
		/* Ici on prend que pour les articles et on ajoute l'opion export en haut (action) et en bas (action2) du tableau */
		if($post_type == 'shop_order') {
			?>
				<script type="text/javascript">
					jQuery(document).ready(function() {
						jQuery('<option>').val('mfb_creer_expedition').text('Créer et commander les expeditions').appendTo("select[name='action']");
						jQuery('<option>').val('mfb_creer_expedition').text('Créer et commander les expeditions').appendTo("select[name='action2']");
						jQuery('<option>').val('mfb_creer_impression').text("Télécharger les étiquettes d'expedition").appendTo("select[name='action']");
						jQuery('<option>').val('mfb_creer_impression').text("Télécharger les étiquettes d'expedition").appendTo("select[name='action2']");
						jQuery('<option>').val('mfb_creer_retour').text('Créer et commander les expeditions retour').appendTo("select[name='action']");
						jQuery('<option>').val('mfb_creer_retour').text('Créer et commander les expeditions retour').appendTo("select[name='action2']");
						jQuery('<option>').val('mfb_creer_impression_retour').text("Télécharger les étiquettes de retour").appendTo("select[name='action']");
						jQuery('<option>').val('mfb_creer_impression_retour').text("Télécharger les étiquettes de retour").appendTo("select[name='action2']");
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
		$already_included = array();
		$booked = array();
		$bulk = new MFB_Bulk_Order();


		foreach( $_REQUEST['post'] as $post_id ) {

			$order = new WC_Order( $post_id );
			$res = $bulk->add_order( $order );

			if ( $res['success'] ) {
				$booked[] = $order->get_order_number();

			} elseif ( $res['error'] == 'already_shipped' ) {
				$already_shipped[] = $order->get_order_number();

			} elseif ( $res['error'] == 'already_included' ) {
				$already_included[] = $order->get_order_number();
			}
		}
		if ( count($booked) > 0 ) {
			$bulk->save();

			$queue = new MFB_Bulk_Order_Background_Process();

			foreach( $bulk->wc_order_ids as $wc_order_id ) {
				$queue->push_to_queue( $wc_order_id );
			}

			$bulk->update_status('mfb-processing');

			$queue->save()->dispatch();
		} else {
			$errors[] = __('No bulk order was created!', 'my-flying-box');
		}

		if ( count($already_shipped) > 0) {
			$errors[] = sprintf( __('The following orders have already been shipped with MyFlyingBox: %s', 'my-flying-box'), implode(', ', $already_shipped));
		}
		if ( count($already_included) > 0) {
			$errors[] = sprintf( __('The following orders have already been included in the bulk order: %s', 'my-flying-box'), implode(', ', $already_included));
		}
		if ( count($booked) > 0) {
			$notices[] = sprintf( __('The following orders has been added to shipment booking queue: %s', 'my-flying-box'), implode(', ', $booked));
		}

		set_transient('mfb_bulk_notices', $notices, 180);
		set_transient('mfb_bulk_errors',  $errors,  180);
	}

	/*
	 * Fonction de création des expéditions retour
	 */
	function creation_expedition_retour()
	{
		$notices = array();
		$errors = array();
		$no_initial_shipment = array();
		$already_returned = array();
		$already_included = array();
		$booked = array();
		$bulk = new MFB_Bulk_Order();
		$bulk->return_shipments = true;

		foreach( $_REQUEST['post'] as $post_id ) {

			$order = new WC_Order( $post_id );
			$res = $bulk->add_order( $order );

			if ( $res['success'] ) {
				$booked[] = $order->get_order_number();

			} elseif ( $res['error'] == 'no_initial_shipment' ) {
				$no_initial_shipment[] = $order->get_order_number();

			} elseif ( $res['error'] == 'already_returned' ) {
				$already_returned[] = $order->get_order_number();

			} elseif ( $res['error'] == 'already_included' ) {
				$already_included[] = $order->get_order_number();
			}
		}
		if ( count($booked) > 0 ) {
			$bulk->save();

			$queue = new MFB_Bulk_Order_Return_Background_Process();
			foreach( $bulk->wc_order_ids as $wc_order_id ) {
				$queue->push_to_queue( $wc_order_id );
			}

			$bulk->update_status('mfb-processing');

			$queue->save()->dispatch();
		} else {
			$errors[] = __('No bulk order was created!', 'my-flying-box');
		}

		if ( count($no_initial_shipment) > 0) {
			$errors[] = sprintf( __('The following orders have no initial shipment booked with MyFlyingBox: %s', 'my-flying-box'), implode(', ', $no_initial_shipment));
		}
		if ( count($already_returned) > 0) {
			$errors[] = sprintf( __('The following orders already have a return shipment booked with MyFlyingBox: %s', 'my-flying-box'), implode(', ', $no_initial_shipment));
		}
		if ( count($already_included) > 0) {
			$errors[] = sprintf( __('The following orders have already been included in the bulk order for returns: %s', 'my-flying-box'), implode(', ', $already_included));
		}
		if ( count($booked) > 0) {
			$notices[] = sprintf( __('The following orders has been added to return shipment booking queue: %s', 'my-flying-box'), implode(', ', $booked));
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
				$uuid = get_post_meta( $shipment->get_id(), '_api_uuid', true );
				array_push($uuid_list, $uuid);
			}
		}

		if ( !empty( $uuid_list ) ){
			$labels = Lce\Resource\Order::multiple_labels($uuid_list);
			$filename = 'MFB-labels.pdf';

			header('Content-type: application/pdf');
			header("Content-Transfer-Encoding: binary");
			header('Content-Disposition: attachment; filename="'.$filename.'"');
			print($labels);
			die();
		}
	}

	/*
	 * Fonction de création des étiquettes en un seul PDF
	 */
	function creation_impression_retour()
	{
		$uuid_list = array();
		foreach( $_REQUEST['post'] as $post_id ) {
			//$order = new WC_Order( $post_id );
			$shipment = MFB_Shipment::get_last_booked_for_order($post_id, true);
			if ($shipment) {
				$uuid = get_post_meta( $shipment->id, '_api_uuid', true );
				array_push($uuid_list, $uuid);
			}
		}

		if ( !empty( $uuid_list ) ){
			$labels = Lce\Resource\Order::multiple_labels($uuid_list);
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


	public static function output() {
		global $current_section, $current_tab;

		$bulk_orders = MFB_Bulk_Order::get_all();

		include 'views/html-admin-bulk-orders-index.php';

	}

}
