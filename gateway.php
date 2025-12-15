<?php
class WC_Gateway_iPaymu extends \WC_Payment_Gateway
{
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


    // Constructor method
    public function __construct()
    {

        $this->id                 = 'ipaymu';
        
        //Payment Gateway title
        $this->method_title       = 'iPaymu Payment';
        $this->method_description = 'Pembayaran Virtual Account, QRIS, Alfamart/Indomaret, Direct Debit, Kartu Kredit, dan COD.';
        
        //true only in case of direct payment method, false in our case
        $this->has_fields         = false;
        //payment gateway logo
        $this->icon               = plugins_url('/ipaymu_badge.png', __FILE__);

        //redirect URL
        $returnUrl                = home_url('/checkout/order-received/');

        $this->redirect_url       = add_query_arg('wc-api', 'WC_Gateway_iPaymu', home_url('/'));


        //Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->enabled         = $this->get_option( 'enabled' );
        $this->auto_redirect   = $this->get_option('auto_redirect');
        $this->return_url      = $this->get_option('return_url') ?? $returnUrl;
        $this->expired_time    = $this->get_option('expired_time') ?? 24;
        $this->title           = $this->get_option( 'title' );
        $this->description     = $this->get_option('description') ??  'Pembayaran Virtual Account, QRIS, Alfamart/Indomaret, Direct Debit, Kartu Kredit, dan COD.';

        if ($this->get_option( 'testmode' ) == 'yes') {
            $this->url = 'https://sandbox.ipaymu.com/api/v2/payment';
            $this->va     = $this->get_option('sandbox_va');
            $this->secret = $this->get_option('sandbox_key');
        } else {
            $this->url    = 'https://my.ipaymu.com/api/v2/payment';
            $this->va     = $this->get_option('production_va');
            $this->secret = $this->get_option('production_key');
        }

        if ($this->get_option( 'completed_payment' ) == 'yes') {
            $this->completed_payment = 'yes';
            
        } else {
            $this->completed_payment = 'no';
        }

        // Actions
        add_action('woocommerce_receipt_ipaymu', array(&$this, 'receipt_page'));

        // Payment listener/API hook
        add_action('woocommerce_api_wc_gateway_ipaymu', array($this, 'check_ipaymu_response'));


        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    function init_form_fields()
    {

        $this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable/Disable', 'ipaymu-payment-gateway'),
                'label'       => 'Enable iPaymu Payment Gateway',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'yes'
            ),
            'title' => array(
                'title' => __('Title', 'ipaymu-payment-gateway'),
                'type'        => 'text',
                'description' => 'Nama Metode Pembayaran',
                'default'     => 'Pembayaran iPaymu',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title' => __('Description', 'ipaymu-payment-gateway'),
                'type'        => 'textarea',
                'description' => 'Deskripsi Metode Pembayaran',
                'default'     => 'Pembayaran melalui Virtual Account, QRIS, Alfamart / Indomaret, Direct Debit, Kartu Kredit, COD, dan lainnya ',
            ),
            'testmode' => array(
                'title'   => __('Mode Test/Sandbox', 'ipaymu-payment-gateway'),
                'label'       => 'Enable Test Mode / Sandbox',
                'type'        => 'checkbox',
                'description' => '<small>Mode Sandbox/Development digunakan untuk testing transaksi, jika mengaktifkan mode sandbox Anda harus memasukan API Key Sandbox (<a href="https://sandbox.ipaymu.com/integration" target="_blank">dapatkan API Key Sandbox</a>)</small>',
                'default'     => 'yes',
            ),
            'completed_payment' => array(
                'title'   => __('Status Completed After Payment', 'ipaymu-payment-gateway'),
                'label'       => 'Status Completed After Payment',
                'type'        => 'checkbox',
                'description' => '<small>Jika diaktifkan status order menjadi selesai setelah customer melakukan pembayaran. (Default: Processing)</small>',
                'default'     => 'no',
            ),
            'sandbox_va' => array(
                'title'       => 'VA Sandbox',
                'type'        => 'text',
                'description' => '<small>Dapatkan VA Sandbox <a href="https://sandbox.ipaymu.com/integration" target="_blank">di sini</a></small>',
                'default'     => ''
            ),
            'sandbox_key' => array(
                'title'       => 'API Key Sandbox',
                'type'        => 'password',
                'description' => '<small>Dapatkan API Key Sandbox <a href="https://sandbox.ipaymu.com/integration" target="_blank">di sini</a></small>',
                'default'     => ''
            ),
            'production_va' => array(
                'title'       => 'VA Live/Production',
                'type'        => 'text',
                'description' => '<small>Dapatkan VA Production <a href="https://my.ipaymu.com/integration" target="_blank">di sini</a></small>',
                'default'     => ''
            ),
            'production_key' => array(
                'title'       => 'API Key Live/Production',
                'type'        => 'password',
                'description' => '<small>Dapatkan API Key Production <a href="https://my.ipaymu.com/integration" target="_blank">di sini</a></small>',
                'default'     => ''
            ),
            'auto_redirect' => array(
                'title' => __('Waktu redirect ke Thank You Page (time of redirect to Thank You Page in seconds)', 'ipaymu-payment-gateway'),
                'type' => 'text',
                'description' => __('<small>Dalam hitungan detik. Masukkan -1 untuk langsung redirect ke halaman Anda</small>.', 'ipaymu-payment-gateway'),
                'default' => '60'
            ),
            'return_url' => array(
                'title' => __('Url Thank You Page', 'ipaymu-payment-gateway'),
                'type' => 'text',
                'description' => __('<small>Link halaman setelah pembeli melakukan checkout pesanan</small>.', 'ipaymu-payment-gateway'),
                'default' => home_url('/checkout/order-received/')
            ),
            'expired_time' => array(
                'title' => __('Expired kode pembayaran (expiry time of payment code)', 'ipaymu-payment-gateway'),
                'type' => 'text',
                'description' => __('<small>Dalam hitungan jam (in hours)</small>.', 'ipaymu-payment-gateway'),
                'default' => '24'
            )
        );
    }

    function process_payment($order_id)
    {

        $order = new \WC_Order($order_id);

        $buyerName  = $order->get_billing_first_name() . $order->get_billing_last_name();
        $buyerEmail = $order->get_billing_email();
        $buyerPhone = $order->get_billing_phone();

        $body['product'] = [];
        $body['qty']     = [];
        $body['price']   = [];

        $width  = array();
        $height = array();
        $length = array();
        $weight = array();

        foreach ($order->get_items() as $kitem => $item) {
            $itemQty = $item->get_quantity();
            if (!$itemQty) {
                continue;
            }

            $itemWeight = is_numeric($item->get_product()->get_weight()) ? $item->get_product()->get_weight() : 0;
            if ($itemWeight) {
                // $weightVal = wc_get_weight($itemWeight * $itemQty, 'kg');
                array_push($weight, $itemWeight * $itemQty);
            }

            $itemWidth = is_numeric($item->get_product()->get_width()) ? $item->get_product()->get_width() : 0;
            if ($itemWidth) {
                // $widthVal = wc_get_dimension($itemWidth, 'cm');
                array_push($width, $itemWidth);
            }

            $itemHeight = is_numeric($item->get_product()->get_height()) ? $item->get_product()->get_height() : 0;
            if ($itemHeight) {
                // $heightVal = wc_get_dimension($itemHeight, 'cm');
                array_push($height, $itemHeight);
            }

            $itemLength = is_numeric($item->get_product()->get_length()) ? $item->get_product()->get_length() : 0;
            if ($itemLength) {
                // $lengthVal = wc_get_dimension($itemLength, 'cm');
                array_push($length, $itemLength);
            }
        }

        $weightVal = 0;
        $lengthVal = 0;
        $widthVal  = 0;
        $heightVal = 0;
        if (!empty($weight)) {
            $weightVal      = ceil(floatval(wc_get_weight(array_sum($weight), 'kg')));
        }

        if (!empty($length)) {
            $lengthVal      = ceil(floatval(wc_get_dimension(max($length), 'cm')));
        }

        if (!empty($width)) {
            $widthVal      = ceil(floatval(wc_get_dimension(max($width), 'cm')));
        }

        if (!empty($height)) {
            $heightVal      = ceil(floatval(wc_get_dimension(max($height), 'cm')));
        }


        $body['weight'][0]      = $weightVal;
        $body['length'][0]      = $lengthVal;
        $body['width'][0]       = $widthVal;
        $body['height'][0]      = $heightVal;
        $body['dimension'][0]   = $lengthVal . ':' . $widthVal . ':' . $heightVal;

        $body['product'][0]     = 'Order #' . trim(strval($order_id));
        $body['qty'][0]         = 1;
        $body['price'][0]       = $order->get_total();


        if (!empty($buyerName)) {
            $body['buyerName']          = trim($buyerName ?? null);
        } else {
            $body['buyerName']          = null;
        }

        if (!empty($buyerPhone)) {
            $body['buyerPhone']          = trim($buyerPhone ?? null);
        } else {
            $body['buyerPhone']          = null;
        }

        if (!empty($buyerEmail)) {
            $body['buyerEmail']          = trim($buyerEmail ?? null);
        } else {
            $body['buyerEmail']          = null;
        }

        $notifyUrl = trim($this->redirect_url . '&id_order=' . $order_id . '&param=notify&order_status=on-hold');
        if (!empty($this->completed_payment) && $this->completed_payment == 'yes') {
            $notifyUrl = trim($this->redirect_url . '&id_order=' . $order_id . '&param=notify&order_status=completed');
        }
        $body['referenceId']         = trim(strval($order_id));
        $body['returnUrl']           = trim($this->return_url);
        $body['notifyUrl']           = trim($notifyUrl);
        $body['cancelUrl']           = trim($this->redirect_url . '&id_order=' . $order_id . '&param=cancel');
        $body['expired']             = $this->expired_time ?? 24;
        $body['expiredType']         = 'hours';

        $bodyJson     = json_encode($body, JSON_UNESCAPED_SLASHES);
        $requestBody  = strtolower(hash('sha256', $bodyJson));
        $stringToSign = 'POST:' . $this->va . ':' . $requestBody . ':' . $this->secret;
        $signature    = hash_hmac('sha256', $stringToSign, $this->secret);

        $headers = array(
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            'va'           => $this->va,
            'signature'    => $signature,
        );

        $response = wp_remote_post($this->url, array(
            'headers' => $headers,
            'body'    => $bodyJson,
            'timeout' => 45
        ));

        if (is_wp_error($response)) {
            throw new Exception('Invalid Response from iPaymu. Please contact support@ipaymu.com');
            exit;
        }

        $res = wp_remote_retrieve_body($response);
        $err = ''; // wp_remote_post handles errors via is_wp_error

        if (!empty($err)) {
            throw new Exception('Invalid Response from iPaymu. Please contact support@ipaymu.com');
            exit;
            // return new WP_Error( 'ipaymu_request', 'Invalid request: ' . $err);
        }
        if (empty($res)) {
            // return new WP_Error( 'ipaymu_request', 'Invalid request');
            throw new Exception('Request Failed: Invalid Response from iPaymu. Please contact support@ipaymu.com');
            exit;
        }

        $response = json_decode($res);
        if (empty($response->Data->Url)) {
            $message = isset($response->Message) ? $response->Message : 'Unknown error';
            throw new Exception('Invalid request. Response iPaymu: ' . esc_html($message));
            exit;
        }

        WC()->cart->empty_cart();

        return array(
            'result' => 'success',
            'redirect' => $response->Data->Url
        );
    }


    function check_ipaymu_response()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! isset( $_REQUEST['id_order'] ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $order_id = absint( $_REQUEST['id_order'] );
        $order    = new WC_Order( $order_id );

        $order_received_url = wc_get_endpoint_url('order-received', $order_id, wc_get_page_permalink('checkout'));

        if ('yes' === get_option('woocommerce_force_ssl_checkout') || is_ssl()) {
            $order_received_url = str_replace('http:', 'https:', $order_received_url);
        }

        if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] == 'POST' ) {
            
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $status       = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $trx_id       = isset( $_REQUEST['trx_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['trx_id'] ) ) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $order_status = isset( $_REQUEST['order_status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order_status'] ) ) : '';

            if ( $status == 'berhasil' ) {
                /* translators: %s: transaction ID */
                $order->add_order_note( sprintf( __( 'Payment Success iPaymu ID %s', 'ipaymu-payment-gateway' ), $trx_id ) );
                
                if ( $order_status == 'completed' ) {
                    $order->update_status('completed');
                } else {
                    $order->update_status('processing');
                }
                $order->payment_complete();
                echo 'completed';
                exit;
            } else if ( $status == 'pending' ) {
                if ($order->get_status() == 'pending') { 
                    /* translators: %s: transaction ID */
                    $order->add_order_note( sprintf( __( 'Waiting Payment iPaymu ID %s', 'ipaymu-payment-gateway' ), $trx_id ) );
                    // $order->update_status('on-hold');
                    $order->update_status('pending');
                    echo 'on-hold';
                } else {
                     echo esc_html( 'order is ' . $order->get_status() );   
                }
                
                exit;
            } else if ( $status == 'expired' ) {
                if ($order->get_status() == 'pending') {
                    /* translators: %s: transaction ID */
                    $order->add_order_note( sprintf( __( 'Payment Expired iPaymu ID %s expired', 'ipaymu-payment-gateway' ), $trx_id ) );
                    $order->update_status('cancelled');
                    echo 'cancelled';
                } else {
                    echo esc_html( 'order is ' . $order->get_status() );
                }
                
                exit;
            } else {
                echo 'invalid status';
                exit;
            }
        }

        $order_received_url = add_query_arg('key', $order->get_order_key(), $order_received_url);
        $redirect =  apply_filters('woocommerce_get_checkout_order_received_url', $order_received_url, $this);

        wp_safe_redirect($redirect);
        exit;
    }

    function receipt_page($order) {
        echo wp_kses( $this->generate_ipaymu_form($order), array( 'form' => array( 'action' => array(), 'method' => array(), 'id' => array(), 'name' => array() ), 'input' => array( 'type' => array(), 'name' => array(), 'value' => array(), 'id' => array() ), 'script' => array( 'type' => array(), 'src' => array() ) ) );
    }

    function generate_ipaymu_form($order_id) {
        global $woocommerce;
        
        $order = new WC_Order($order_id);
        
        
        $url = 'https://my.ipaymu.com/api/v2/payment';
        if ($this->sandbox_mode == 'yes') {
            $url = 'https://sandbox.ipaymu.com/api/v2/payment';    
        }

        $buyerName  = $order->get_billing_first_name() . $order->get_billing_last_name();
        $buyerEmail = $order->get_billing_email();
        $buyerPhone = $order->get_billing_phone();

        $body['product'] = [];
        $body['qty']     = [];
        $body['price']   = [];

        $width  = array();
        $height = array();
        $length = array();
        $weight = array();

        // $totalPrice = 0;
        // $i = 0;

        foreach ($order->get_items() as $kitem => $item) {
            $itemQty = $item->get_quantity();
            if (!$itemQty) {
                continue;
            }

            // $product        = $item->get_product();
            // $weightVal = 0;
            // $lengthVal = 0;
            // $widthVal  = 0;
            // $heightVal = 0;

            $itemWeight = is_numeric($item->get_product()->get_weight() ) ? $item->get_product()->get_weight() : 0;
            if ($itemWeight) {
                // $weightVal = wc_get_weight($itemWeight * $itemQty, 'kg');
                array_push( $weight, $itemWeight * $itemQty );
            }

            $itemWidth = is_numeric($item->get_product()->get_width() ) ? $item->get_product()->get_width() : 0;
            if ($itemWidth) {
                // $widthVal = wc_get_dimension($itemWidth, 'cm');
                array_push( $width, $itemWidth );
            }

            $itemHeight = is_numeric($item->get_product()->get_height() ) ? $item->get_product()->get_height() : 0;
            if ($itemHeight) {
                // $heightVal = wc_get_dimension($itemHeight, 'cm');
                array_push( $height, $itemHeight );
            }

            $itemLength = is_numeric($item->get_product()->get_length() ) ? $item->get_product()->get_length() : 0;
            if ($itemLength) {
                // $lengthVal = wc_get_dimension($itemLength, 'cm');
                array_push( $length, $itemLength );
            }
        
        }
        
        $weightVal = 0;
        $lengthVal = 0;
        $widthVal  = 0;
        $heightVal = 0;
        if (!empty($weight)) {
            $weightVal      = ceil(wc_get_weight(array_sum( $weight ), 'kg'));
        }

        if (!empty($length)) {
            $lengthVal      = ceil(wc_get_dimension( max( $length ), 'cm' ));
        }

        if (!empty($width)) {
            $widthVal      = ceil(wc_get_dimension( max( $width ), 'cm' ));
        }

        if (!empty($height)) {
            $heightVal      = ceil(wc_get_dimension( max( $height ), 'cm' ));
        }
        
        
        $body['weight'][0]      = $weightVal;
        $body['length'][0]      = $lengthVal;
        $body['width'][0]       = $widthVal;
        $body['height'][0]      = $heightVal;
        $body['dimension'][0]   = $lengthVal . ':' . $widthVal . ':' . $heightVal;

        $body['product'][0]     = 'Order #' . trim($order_id);
        $body['qty'][0]         = 1;
        $body['price'][0]       = $order->get_total();
        
        $body['buyerName']           = trim($buyerName ?? null);
        $body['buyerPhone']          = trim($buyerPhone ?? null);
        $body['buyerEmail']          = trim($buyerEmail ?? null);
        $body['referenceId']         = trim($order_id);
        $body['returnUrl']           = trim($this->return_url);
        $body['notifyUrl']           = trim($this->redirect_url.'&id_order='.$order_id.'&param=notify');
        $body['cancelUrl']           = trim($this->redirect_url.'&id_order='.$order_id.'&param=cancel');
        $body['expired']             = trim($this->expired_time ?? 24);
        $body['expiredType']         = 'hours';


        $bodyJson     = json_encode($body, JSON_UNESCAPED_SLASHES);
        $requestBody  = strtolower(hash('sha256', $bodyJson));
        $secret       = $this->secret;
        $va           = $this->va;
        $stringToSign = 'POST:' . $va . ':' . $requestBody . ':' . $secret;
        $signature    = hash_hmac('sha256', $stringToSign, $secret);

        $headers = array(
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            'va'           => $va,
            'signature'    => $signature,
        );

        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body'    => $bodyJson,
            'timeout' => 45
        ));

        if (is_wp_error($response)) {
            echo esc_html( 'Invalid request: ' . $response->get_error_message() );
            exit;
        }

        $res = wp_remote_retrieve_body($response);
        $err = ''; 

        if (empty($res)) {
            echo 'Request Failed: Invalid response';
            exit;
        }

        $response_data = json_decode($res);
        if (empty($response_data->Data->Url)) {
            echo esc_html( 'Invalid request: ' . ( isset( $response_data->Message ) ? $response_data->Message : 'Unknown error' ) );
            exit;
            
        }
        wp_safe_redirect($response_data->Data->Url);
        exit;
    }

}
