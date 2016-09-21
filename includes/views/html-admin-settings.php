<?php
/**
 * Admin View: Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>

<div class="wrap woocommerce">
	<form method="post" id="mainform" action="" enctype="multipart/form-data">
		<div class="icon32 icon32-woocommerce-settings" id="icon-woocommerce"><br /></div><h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
			<?php
				foreach ( $tabs as $name => $label )
					echo '<a href="' . admin_url( 'admin.php?page=my-flying-box-settings&tab=' . $name ) . '" class="nav-tab ' . ( $current_tab == $name ? 'nav-tab-active' : '' ) . '">' . $label . '</a>';

				do_action( 'woocommerce_settings_tabs' );
			?>
		</h2>

		<div style="cleart:both">
		<?php
			do_action( 'woocommerce_sections_' . $current_tab );
			do_action( 'woocommerce_settings_' . $current_tab );
			// do_action( 'woocommerce_settings_tabs_' . $current_tab ); // @deprecated hook
		?>
		</div>
      <p class="submit">
        <input name="save" class="button-primary" type="submit" value="<?php _e( 'Save changes', 'my-flying-box' ); ?>" />
        <input type="hidden" name="subtab" id="last_tab" />
        <?php wp_nonce_field( 'woocommerce-settings' ); ?>
      </p>
	</form>
</div>
