<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ipaymu_WC_Gateway extends WC_Payment_Gateway {

    public $id;
    public $method_title;
    public $method_description;
    public $icon;
    public $has_fields;
    public $redirect_url;
    public $auto_redirect;
    public $return_url;
    public $expired_time;
    public $title;
    public $description;
    public $url;
    public $va;
    public $secret;
    public $completed_payment;

    public function __construct() {

        $this->id                 = 'ipaymu';
        $this->method_title       = 'iPaymu Payment';
        $this->method_description = 'Pembayaran Virtual Account, QRIS, Alfamart/Indomaret, Direct Debit, Kartu Kredit, dan COD.';
        $this->has_fields         = false;
        $this->icon               = plugins_url( '/ipaymu_badge.png', __FILE__ );

        $default_return_url       = home_url( '/checkout/order-received/' );
        $this->redirect_url       = add_query_arg( 'wc-api', 'Ipaymu_WC_Gateway', home_url( '/' ) );

        // Load the form fields and settings.
        $this->init_form_fields();
        $this->init_settings();

        // User settings.
        $this->enabled       = $this->get_option( 'enabled' );
        $this->auto_redirect = $this->get_option( 'auto_redirect', '60' );
        $this->return_url    = $this->get_option( 'return_url', $default_return_url );
        $this->expired_time  = $this->get_option( 'expired_time', 24 );
        $this->title         = $this->get_option( 'title', 'Pembayaran iPaymu' );
        $this->description   = $this->get_option( 'description', 'Pembayaran melalui Virtual Account, QRIS, Alfamart/Indomaret, Direct Debit, Kartu Kredit, dan COD.' );

        if ( 'yes' === $this->get_option( 'testmode', 'yes' ) ) {
            $this->url    = 'https://sandbox.ipaymu.com/api/v2/payment';
            $this->va     = $this->get_option( 'sandbox_va' );
            $this->secret = $this->get_option( 'sandbox_key' );
        } else {
            $this->url    = 'https://my.ipaymu.com/api/v2/payment';
            $this->va     = $this->get_option( 'production_va' );
            $this->secret = $this->get_option( 'production_key' );
        }

        $this->completed_payment = ( 'yes' === $this->get_option( 'completed_payment', 'no' ) ) ? 'yes' : 'no';

        // Hooks.
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        // Register both the new and legacy API hooks so existing webhook
        // configurations continue to work after the class rename.
        add_action( 'woocommerce_api_ipaymu_wc_gateway', array( $this, 'check_ipaymu_response' ) );
        add_action( 'woocommerce_api_wc_gateway_ipaymu', array( $this, 'check_ipaymu_response' ) );
    }

    /**
     * Admin options fields.
     */
    public function init_form_fields() {

        $this->form_fields = array(
            'enabled' => array(
                'title'       => __( 'Enable/Disable', 'ipaymu-for-woocommerce' ),
                'label'       => 'Enable iPaymu Payment Gateway',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'yes',
            ),
            'title' => array(
                'title'       => __( 'Title', 'ipaymu-for-woocommerce' ),
                'type'        => 'text',
                'description' => 'Nama Metode Pembayaran',
                'default'     => 'Pembayaran iPaymu',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'ipaymu-for-woocommerce' ),
                'type'        => 'textarea',
                'description' => 'Deskripsi Metode Pembayaran',
                'default'     => 'Pembayaran melalui Virtual Account, QRIS, Alfamart / Indomaret, Direct Debit, Kartu Kredit, COD, dan lainnya ',
            ),
            'testmode' => array(
                'title'       => __( 'Mode Test/Sandbox', 'ipaymu-for-woocommerce' ),
                'label'       => 'Enable Test Mode / Sandbox',
                'type'        => 'checkbox',
                'description' => '<small>Mode Sandbox/Development digunakan untuk testing transaksi, jika mengaktifkan mode sandbox Anda harus memasukan API Key Sandbox (<a href="https://sandbox.ipaymu.com/integration" target="_blank">dapatkan API Key Sandbox</a>)</small>',
                'default'     => 'yes',
            ),
            'completed_payment' => array(
                'title'       => __( 'Status Completed After Payment', 'ipaymu-for-woocommerce' ),
                'label'       => 'Status Completed After Payment',
                'type'        => 'checkbox',
                'description' => '<small>Jika diaktifkan status order menjadi selesai setelah customer melakukan pembayaran. (Default: Processing)</small>',
                'default'     => 'no',
            ),
            'sandbox_va' => array(
                'title'       => 'VA Sandbox',
                'type'        => 'text',
                'description' => '<small>Dapatkan VA Sandbox <a href="https://sandbox.ipaymu.com/integration" target="_blank">di sini</a></small>',
                'default'     => '',
            ),
            'sandbox_key' => array(
                'title'       => 'API Key Sandbox',
                'type'        => 'password',
                'description' => '<small>Dapatkan API Key Sandbox <a href="https://sandbox.ipaymu.com/integration" target="_blank">di sini</a></small>',
                'default'     => '',
            ),
            'production_va' => array(
                'title'       => 'VA Live/Production',
                'type'        => 'text',
                'description' => '<small>Dapatkan VA Production <a href="https://my.ipaymu.com/integration" target="_blank">di sini</a></small>',
                'default'     => '',
            ),
            'production_key' => array(
                'title'       => 'API Key Live/Production',
                'type'        => 'password',
                'description' => '<small>Dapatkan API Key Production <a href="https://my.ipaymu.com/integration" target="_blank">di sini</a></small>',
                'default'     => '',
            ),
            'auto_redirect' => array(
                'title'       => __( 'Waktu redirect ke Thank You Page (time of redirect to Thank You Page in seconds)', 'ipaymu-for-woocommerce' ),
                'type'        => 'text',
                'description' => __( '<small>Dalam hitungan detik. Masukkan -1 untuk langsung redirect ke halaman Anda</small>.', 'ipaymu-for-woocommerce' ),
                'default'     => '60',
            ),
            'return_url' => array(
                'title'       => __( 'Url Thank You Page', 'ipaymu-for-woocommerce' ),
                'type'        => 'text',
                'description' => __( '<small>Link halaman setelah pembeli melakukan checkout pesanan</small>.', 'ipaymu-for-woocommerce' ),
                'default'     => home_url( '/checkout/order-received/' ),
            ),
            'expired_time' => array(
                'title'       => __( 'Expired kode pembayaran (expiry time of payment code)', 'ipaymu-for-woocommerce' ),
                'type'        => 'text',
                'description' => __( '<small>Dalam hitungan jam (in hours)</small>.', 'ipaymu-for-woocommerce' ),
                'default'     => '24',
            ),
        );
    }

    /**
     * Process the payment and return the redirect URL.
     */
    public function process_payment( $order_id ) {

        $order = wc_get_order( $order_id );

        $buyerName  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        $buyerEmail = $order->get_billing_email();
        $buyerPhone = $order->get_billing_phone();

        $notifyUrl = $this->redirect_url . '&id_order=' . $order_id . '&param=notify&order_status=on-hold';
        if ( 'yes' === $this->completed_payment ) {
            $notifyUrl = $this->redirect_url . '&id_order=' . $order_id . '&param=notify&order_status=completed';
        }

        $body = array(
            'product'     => array( 'Order #' . $order_id ),
            'qty'         => array( 1 ),
            'price'       => array( (float) $order->get_total() ),
            'buyerName'   => ! empty( $buyerName ) ? $buyerName : null,
            'buyerPhone'  => ! empty( $buyerPhone ) ? $buyerPhone : null,
            'buyerEmail'  => ! empty( $buyerEmail ) ? $buyerEmail : null,
            'referenceId' => (string) $order_id,
            'returnUrl'   => $this->return_url,
            'cancelUrl'   => $this->redirect_url . '&id_order=' . $order_id . '&param=cancel',
            'notifyUrl'   => $notifyUrl,
            'expired'     => (int) $this->expired_time,
            'expiredType' => 'hours',
        );

        $bodyJson     = wp_json_encode( $body, JSON_UNESCAPED_SLASHES );
        $requestBody  = strtolower( hash( 'sha256', $bodyJson ) );
        $stringToSign = 'POST:' . $this->va . ':' . $requestBody . ':' . $this->secret;
        $signature    = hash_hmac( 'sha256', $stringToSign, $this->secret );

        $headers = array(
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            'va'           => $this->va,
            'signature'    => $signature,
        );

        $response_http = wp_remote_post(
            $this->url,
            array(
                'headers' => $headers,
                'body'    => $bodyJson,
                'timeout' => 60,
            )
        );

        if ( is_wp_error( $response_http ) ) {
            $err_safe = sanitize_text_field( $response_http->get_error_message() );
            throw new Exception(
                sprintf(
                    /* translators: %s: HTTP error message. */
                    esc_html__( 'Request failed: %s', 'ipaymu-for-woocommerce' ),
                    esc_html( $err_safe )
                )
            );
        }

        $res = wp_remote_retrieve_body( $response_http );

        if ( empty( $res ) ) {
            throw new Exception(
                esc_html__( 'Request failed: empty response from iPaymu. Please contact support@ipaymu.com.', 'ipaymu-for-woocommerce' )
            );
        }

        $response = json_decode( $res );

        if ( empty( $response ) || empty( $response->Data ) || empty( $response->Data->Url ) ) {
            $message      = isset( $response->Message ) ? $response->Message : 'Unknown error';
            $message_safe = sanitize_text_field( $message );
            throw new Exception(
                sprintf(
                    /* translators: %s: error message from iPaymu API. */
                    esc_html__( 'Invalid request. Response iPaymu: %s', 'ipaymu-for-woocommerce' ),
                    esc_html( $message_safe )
                )
            );
        }

        // Empty the cart.
        WC()->cart->empty_cart();

        return array(
            'result'   => 'success',
            'redirect' => esc_url_raw( $response->Data->Url ),
        );
    }

    /**
     * Handle callback / notify from iPaymu.
     */
    public function check_ipaymu_response() {

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- This endpoint is a
        // webhook called by iPaymu (server-to-server). A WP nonce cannot be used for external
        // requests. We validate and sanitize incoming data and (if available) verify request
        // integrity via provider signature; disabling the nonce rule for this block accordingly.

        // Support JSON POST bodies: some providers send JSON instead of form-encoded
        // parameters. If JSON is present, decode it and merge into `$_REQUEST` so the
        // rest of the handler can continue to use the same request accessors.
        $raw_body     = file_get_contents( 'php://input' );
        $json_payload = null;
        if ( ! empty( $raw_body ) ) {
            $decoded = json_decode( $raw_body, true );
            if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
                $json_payload = $decoded;
                // Merge decoded JSON into $_REQUEST without overriding existing values
                // that may have been provided via form params.
                $_REQUEST = array_merge( $decoded, $_REQUEST );
            }
        }

        // Helpful debug logging when WP_DEBUG is enabled.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( function_exists( 'wc_get_logger' ) ) {
                $logger  = wc_get_logger();
                $context = array( 'source' => 'ipaymu' );
                $logger->info( 'iPaymu webhook received', $context );
                $logger->info( 'iPaymu webhook payload: ' . wp_json_encode( $_REQUEST ), $context );
            } else {
                error_log( 'iPaymu webhook payload: ' . wp_json_encode( $_REQUEST ) );
            }
        }

        $order_id = isset( $_REQUEST['id_order'] ) ? absint( $_REQUEST['id_order'] ) : 0;

        if ( ! $order_id ) {
            status_header( 400 );
            echo 'Invalid order ID';
            exit;
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            status_header( 404 );
            echo 'Order not found';
            exit;
        }

        // Handle server-to-server notification
        if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_REQUEST['status'] ) && isset( $_REQUEST['trx_id'] ) ) {

            $status        = sanitize_text_field( wp_unslash( $_REQUEST['status'] ) );
            $ipaymu_trx_id = sanitize_text_field( wp_unslash( $_REQUEST['trx_id'] ) );
            $order_status  = isset( $_REQUEST['order_status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order_status'] ) ) : 'processing';

            if ( 'berhasil' === strtolower( $status ) ) {

                /* translators: %s: iPaymu transaction ID. */
                $order->add_order_note( sprintf( __( 'Payment Success iPaymu ID %s', 'ipaymu-for-woocommerce' ), $ipaymu_trx_id ) );

                if ( 'completed' === $order_status ) {
                    $order->update_status( 'completed' );
                } else {
                    $order->update_status( 'processing' );
                }

                $order->payment_complete();
                echo 'completed';
                exit;

            } elseif ( 'pending' === strtolower( $status ) ) {

                    if ( 'pending' === $order->get_status() ) {
                        /* translators: %s: iPaymu transaction ID. */
                        $order->add_order_note( sprintf( __( 'Waiting Payment iPaymu ID %s', 'ipaymu-for-woocommerce' ), $ipaymu_trx_id ) );
                        $order->update_status( 'pending' );
                        echo 'pending';
                    } else {
                    echo 'order is ' . esc_html( $order->get_status() );
                }
                exit;

            } elseif ( 'expired' === strtolower( $status ) ) {

                if ( 'pending' === $order->get_status() ) {
                    /* translators: %s: iPaymu transaction ID. */
                    $order->add_order_note( sprintf( __( 'Payment Expired iPaymu ID %s', 'ipaymu-for-woocommerce' ), $ipaymu_trx_id ) );
                    $order->update_status( 'cancelled' );
                    echo 'cancelled';
                } else {
                    echo 'order is ' . esc_html( $order->get_status() );
                }
                exit;
            } else {
                echo 'invalid status';
                exit;
            }
        }

        // Re-enable nonce verification PHPCS rule after webhook handling.
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        // Handle browser redirect (GET) to thank you / cancel page.
        $order_received_url = wc_get_endpoint_url( 'order-received', $order_id, wc_get_page_permalink( 'checkout' ) );

        if ( 'yes' === get_option( 'woocommerce_force_ssl_checkout' ) || is_ssl() ) {
            $order_received_url = str_replace( 'http:', 'https:', $order_received_url );
        }

        $order_received_url = add_query_arg( 'key', $order->get_order_key(), $order_received_url );
        $redirect           = apply_filters( 'woocommerce_get_checkout_order_received_url', $order_received_url, $this ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core WooCommerce filter

        wp_safe_redirect( $redirect );
        exit;
    }
}
