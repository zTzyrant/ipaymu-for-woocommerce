<?php
/*
  Plugin Name: iPaymu Payment Gateway
  Plugin URI: https://github.com/ipaymu/ipaymu-for-woocommerce
  Description: iPaymu Indonesia Online Payment - Plug & Play, Without Website. Helping businesses to accept payments from consumers which provides the payment methods they use every day.
  Version: 2.0.1
  Author: iPaymu Development Team
  Author URI: https://ipaymu.com
  License: GPLv2 or later
  License URI: https://www.gnu.org/licenses/gpl-2.0.html
  Requires at least: 6.0
  Tested up to: 6.9
  Requires PHP: 7.4
  WC requires at least: 8.0.0
  WC tested up to: 8.6.0
  Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Load the gateway class.
 */
function ipaymu_load_gateway() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    require_once plugin_dir_path( __FILE__ ) . 'gateway.php';
}
add_action( 'plugins_loaded', 'ipaymu_load_gateway', 0 );

/**
 * Register the gateway with WooCommerce.
 */
function ipaymu_register_gateway( $methods ) {
    $methods[] = 'Ipaymu_WC_Gateway';
    return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'ipaymu_register_gateway' );

/**
 * Declare compatibility with WooCommerce features (Blocks & HPOS).
 */
function ipaymu_declare_cart_checkout_blocks_compatibility() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
        // Mark as compatible with High-Performance Order Storage (HPOS / custom order tables).
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
}
add_action( 'before_woocommerce_init', 'ipaymu_declare_cart_checkout_blocks_compatibility' );

/**
 * Register the Blocks integration.
 */
function ipaymu_register_blocks_support() {
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }

    require_once plugin_dir_path( __FILE__ ) . 'block.php';

    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
            $payment_method_registry->register( new Ipaymu_Blocks() );
        }
    );
}
add_action( 'woocommerce_blocks_loaded', 'ipaymu_register_blocks_support' );
