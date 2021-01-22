=== My Flying Box ===
Contributors: tbelliard
Tags: wordpress, plugin, template, woocommerce
Requires at least: 3.9
Tested up to: 5.5
Stable tag: 1.0
License: MIT
License URI: http://opensource.org/licenses/MIT

WooCommerce extension to provide integration of the My Flying Box shipping services.

== Description ==

My Flying Box provides a wide catalog of shipping services with many carriers, at great negociated rates. It is especially useful for small to medium sized e-commerce companies that do not yet have a high volume, or for companies with multiple shipping needs.

This plugin gives you access to My Flying Box services directly in WooCommerce, by using the public API.

You will need a My Flying Box account to use this plugin. Contact us at info@myflyinbox.com for more information or to open an account.

== Installation ==

Go to the 'releases' page https://github.com/myflyingbox/woocommerce-plugin/releases and download the latest package (woocommerce-myflyingbox-v*.zip).

Upload this package through the standard wordpress extension installation mechanisms.

== Development ==

Here are some useful commands for developing on this module.

=== CSS

Styles are managed with Less, and compiled with Grunt.

Make sure to install nodejs, with nvm:
curl https://raw.githubusercontent.com/creationix/nvm/master/install.sh | bash
source ~/.profile

Then run: npm install
This will install all dependencies based on package-lock.json

Finally, run grunt to compile JS and CSS, using npx, the tool provided by nvm to run module executables in the current nvm context: npx grunt

=== Installation from source

Installation from source needs composer to load dependencies.

1. Go to your Wordpress plugin folder (WP_ROOT/wp-content/plugins/)
2. git clone --recursive https://github.com/myflyingbox/woocommerce-plugin.git my-flying-box
3. cd my-flying-box/includes/lib/php-lce
4. curl -s http://getcomposer.org/installer | php
5. php composer.phar install
6. Open WordPress admin panel, and activate the module

== Translations ==

Wordpress uses gettext to manage translations. The easiest way to update plugin translations is to use a tool like POEdit.


== Changelog ==

Please see release history on Github (https://github.com/myflyingbox/woocommerce-plugin/releases)
