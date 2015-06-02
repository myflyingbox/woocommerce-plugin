<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<h2><?php _e( "Track your shipment", 'my-flying-box' ); ?></h2>
<?php if ( count($links) > 1 ) { ?>
<p><?php _e( "Direct links to track your shipments:", 'my-flying-box' ); ?>
<?php } else { ?>
<p><?php _e( "Direct link to track your shipment:", 'my-flying-box' ); ?>
<?php } ?>
<?php foreach ( $links as $link ) { ?>
<br/><a href="<?php echo $link['link'] ?>"><?php echo $link['code']; ?></a>
<?php } ?>
</p>
