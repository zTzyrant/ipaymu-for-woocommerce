=== iPaymu Payment Gateway for WooCommerce ===
Contributors: ipaymu
Tags: payment, payment-gateway, indonesia, ecommerce, checkout
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 6.5
Stable tag: 2.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Official iPaymu Payment Gateway for WooCommerce. Supports VA, QRIS, Retail, Direct Debit, and Credit Card payments.

== Description ==

This plugin integrates the iPaymu Indonesia payment system into WooCommerce.
It supports Virtual Account, QRIS, Minimarket Retail, Direct Debit, Credit Card, and other payment channels.

To use this plugin you must have a registered iPaymu account with your VA and API Key.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate via **Plugins → Installed Plugins**.
3. Open **WooCommerce → Settings → Payments → iPaymu**.
4. Enter the required API credentials.

== Frequently Asked Questions ==

= Do I need an iPaymu account? =
Yes, VA and API Key are required.

= Does this support HPOS? =
Yes, full compatibility is declared.

= Does this plugin support WooCommerce Blocks Checkout? =
Yes.

= Is SSL required? =
Recommended for secure payment processing.

== Screenshots ==

1. Payment settings page
2. Checkout payment method display
3. Payment instruction screen

== Changelog ==

= 2.0.1 =
* Add HPOS compatibility
* Add WooCommerce Blocks support
* Improve error handling
* Fix expired_time bug
* Align request format with API V2 sample

== Upgrade Notice ==

= 2.0.1 =
This version includes important compatibility updates for HPOS and Checkout Blocks.

== Webhook Endpoint ==

The plugin exposes a webhook endpoint that WooCommerce uses for server-to-server
notifications from iPaymu. The endpoint query parameter is:

```
?wc-api=Ipaymu_WC_Gateway
```

Example: `https://example.com/?wc-api=Ipaymu_WC_Gateway`

Note: Older releases of this plugin used `?wc-api=WC_Gateway_iPaymu`. If you have
external integrations or webhook configurations that post to the older endpoint,
please update them to use `Ipaymu_WC_Gateway` so notifications reach this handler.
