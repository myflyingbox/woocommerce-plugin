=== My Flying Box ===
Contributors: tbelliard
Tags: wordpress, plugin, template, woocommerce
Requires at least: 3.9
Tested up to: 4.0
Stable tag: 1.0
License: MIT
License URI: http://opensource.org/licenses/MIT

WooCommerce extension to provide integration of the My Flying Box shipping services.

== Description ==

My Flying Box provides a wide catalog of shipping services with many carriers, at great negociated rates. It is especially useful for small to medium sized e-commerce companies that do not yet have a high volume, or for companies with multiple shipping needs.

This plugin gives you access to My Flying Box services directly in WooCommerce, by using the public API.

You will need a My Flying Box account to use this plugin. Contact us at info@myflyinbox.com for more information or to open an account.

== Installation ==

Installing "My Flying Box" can only be done from source at the moment:

1. Go to your Wordpress plugin folder (WP_ROOT/wp-content/plugins/)
2. git clone --recursive https://github.com/myflyingbox/woocommerce-plugin.git my-flying-box
3. cd my-flying-box/includes/lib/php-lce
4. curl -s http://getcomposer.org/installer | php
5. php composer.phar install
6. Open WordPress admin panel, and activate the module

== Changelog ==

= 0.1 =
* 2015-03-24
* Initial beta release

