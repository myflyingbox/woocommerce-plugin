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
		# Array with the selected User IDs
		foreach( $_REQUEST['post'] as $post_id ) {
			$order = new WC_Order( $post_id );
			$shipment = MFB_Shipment::create_from_order( $order );
		}
		// $_REQUEST['post'] if used on edit.php screen
	}

	/*
	 * Fonction de création des étiquettes en un seul PDF
	 */
	function creation_impression()
	{
		$uuid_list = array();
		foreach( $_REQUEST['post'] as $post_id ) {
			//$order = new WC_Order( $post_id );
			$uuid = get_post_meta( $post_id, '_api_uuid', true );
			array_push($uuid_list, $uuid);
			//$shipment = MFB_Shipment::create_from_order( $order );
		}
		
		if (!empty($uuid_list)){
			Order::multiple_labels($uuid_list);
			$filename = 'MFB-labels.pdf';

			header('Content-type: application/pdf');
			header("Content-Transfer-Encoding: binary");
			header('Content-Disposition: attachment; filename="'.$filename.'"');
			print($labels);
			die();
		}
	}
}