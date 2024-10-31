=== NebuCore ===
Contributors: nebucore
Tags: nebucore, order, woocommerce, woo commerce, e-commerce, json
Requires at least: 4.6
Tested up to: 4.9
Stable tag: 0.1.4
Version: 0.1.4
Requires PHP: 5.2.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WooCommerce extension to send order data JSON to NebuCore on successful WC order.

== Description ==

This plugin is an extension to [WooCommerce](https://woocommerce.com/) platform. You should have WooCommerce plugin installed and active before using this plugin.

This plugin sends the order details of each new order recorded by WooCommerce to the external servers of NebuCore as JSON.
To properly send the info, the plugin should be configured first. You should get an API key and password to be used for this plugin. Configure this plugin with your API key and password by using Woocommerce Settings i.e. Woocommerce->Settings->Advanced->NebuCore.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wc-nebucore` directory, or install the plugin directly through the WordPress plugins screen
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Woocommerce->Settings->Advanced->NebuCore screen to configure the plugin

== Frequently Asked Questions ==

= Where can I get the NebuCore API key and password from? =

You may contact [NebuCore](https://support.nebucore.com/) and request one for your account.

= What is API key? =

An API (Application Programming Interface) key is a unique key to identify your requests without sharing your actual login credentials over network or to the public.

== Changelog ==

= 0.1.4 =
* Update order status when tracking info added.

= 0.1.3 =
* Minor architectural changes.

= 0.1.2 =
* Minor architectural changes.

= 0.1.1 =
* Shipment tracking info can be added by NebuCore.
* Minor bug fixes.

= 0.1.0 =
* First version.
