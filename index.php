<?php

/*
Plugin Name: WooCommerce Jeeb Payment Gateway
Plugin URI: https://jeeb.io/
Description: Jeeb payment gateway for WooCommerce
Version: 3.3
Author: Jeeb
 */

/**
 * Exit if accessed directly
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * registers WC_Jeeb_Payment_Gateway class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'jeen_add_woocommerce_payment_gateway');
function jeen_add_woocommerce_payment_gateway($gateways)
{
    $gateways[] = 'WC_Jeeb_Payment_Gateway';
    return $gateways;
}

/* Implement our desired class */
add_action('plugins_loaded', 'jeeb_init_payment_gateway', 0);
function jeeb_init_payment_gateway()
{

    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Jeeb_Payment_Gateway extends WC_Payment_Gateway
    {
        const PLUGIN_NAME = 'woocommerce';
        const PLUGIN_VERSION = '4.0';

        public function __construct()
        {
            $this->id = 'jeebpaymentgateway';
            $this->method_title = 'Jeeb Payment Gateway';
            $this->method_description = 'Accept BTC and other famous cryptocurrencies.';
            $this->has_fields = false;

            $this->init_form_fields();
            $this->init_settings();

            $this->get_options();
            $this->run_hooks();
        }

        /**
         * Output the gateway settings screen.
         */
        public function admin_options()
        {
            echo '<h3><span><img class="jeeb-logo" src="https://jeeb.io/cdn/en/trans-blue-jeeb.svg"></img</span> Payment Gateway Settings</h3>';
            echo '<p>The first Iranian platform for accepting and processing cryptocurrencies payments.</p>';
            echo '<table class="form-table" id="jeeb-form-table">';
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';
            echo '<input type="hidden" name="jeebCurBtnUrl" id="jeebCurBtnUrl" value="' . $this->settings['btnUrl'] . '"/>';
        }

        public function init_form_fields()
        {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'type' => 'checkbox',
                    'label' => 'Enables Jeeb Payment Gateway Module.',
                    'default' => 'no',
                ),

                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'It controls the title of payment method which users see during checkout.',
                    'default' => 'Jeeb',
                ),

                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'It controls the description of payment method which users see during checkout.',
                    'default' => 'Pay securely with bitcoins through Jeeb Payment Gateway.',
                ),

                'content' => array(
                    'title' => 'Checkout',
                    'type' => 'text',
                    'description' => 'It controls the content of checkout button which users see during checkout.',
                    'default' => 'Pay with Jeeb',
                ),

                'apikey' => array(
                    'title' => 'API Key',
                    'type' => 'text',
                    'description' => 'The API Key that is provided by Jeeb for your merchant.',
                ),

                'baseCurrency' => array(
                    'title' => 'Base Currency',
                    'type' => 'select',
                    'description' => 'The base currency of your website.',
                    'options' => array(
                        'BTC' => 'BTC (Bitcoin)',
                        'USDT' => 'USDT (Tether)',
                        'IRR' => 'IRR (Iranian Rial)',
                        'IRT' => 'IRT (Iranian Toman)',
                        'USD' => 'USD (US Dollar)',
                        'EUR' => 'EUR (Euro)',
                        'GBP' => 'GBP (British Pound)',
                        'CAD' => 'CAD (Canadian Dollar)',
                        'AUD' => 'AUD (Australian Dollar)',
                        'AED' => 'AED (Dirham)',
                        'TRY' => 'TRY (Turkish Lira)',
                        'CNY' => 'CNY (Chinese Yuan)',
                        'JPY' => 'JPY (Japanese Yen)',
                    ),
                    'default' => 'IRT',
                ),

                'payableCurrencies' => array(
                    'title' => 'Payable Currencies',
                    'type' => 'multiselect',
                    'class' => 'jeeb-customized-multiselect',
                    'description' => 'The currencies which users can use for payments.',
                    'options' => array(
                        'BTC' => 'BTC',
                        'ETH' => 'ETH',
                        'LTC' => 'LTC',
                        'DOGE' => 'DOGE',
                        'USDT' => 'USDT',
                        'USDC' => 'USDC',
                        'BNB' => 'BNB',
                        'LINK' => 'LINK',
                        'ZRX' => 'ZRX',
                        'DIA' => 'DIA',
                        'PAX' => 'PAX',
                        'IRT' => 'IRT',
                        'USD' => 'USD',
                        'EUR' => 'EUR',
                        'GBP' => 'GBP',
                        'CAD' => 'CAD',
                        'AUD' => 'AUD',
                        'JPY' => 'JPY',
                        'CNY' => 'CNY',
                        'AED' => 'AED',
                        'TRY' => 'TRY',
                        'TBTC' => 'TBTC',
                        'TETH' => 'TETH',
                    ),
                    'default' => '',
                ),

                'lang' => array(
                    'title' => 'Language',
                    'type' => 'select',
                    'description' => 'The language of the payment area.',
                    'options' => array(
                        'none' => 'Auto',
                        'en' => 'English',
                        'fa' => 'Persian',
                    ),
                ),

                'allowRefund' => array(
                    'title' => 'Allow Refund',
                    'type' => 'checkbox',
                    'label' => 'Allows payments to be refunded.',
                    'default' => 'yes',
                ),

                'test' => array(
                    'title' => 'Allow TestNets',
                    'type' => 'checkbox',
                    'label' => 'Allows testnets such as TBTC to get processed.',
                    'default' => 'no',
                ),

                'expiration' => array(
                    'title' => 'Expiration Time',
                    'type' => 'text',
                    'description' => 'Expands default payments expiration time. It should be between 15 to 2880 (mins).',
                ),

                'btnLang' => array(
                    'title' => 'Checkout Button Language',
                    'type' => 'select',
                    'description' => 'Jeeb\'s checkout button preferred language.',
                    'options' => array(
                        'en' => 'English',
                        'fa' => 'Persian',
                    ),
                ),

                'btnTheme' => array(
                    'title' => 'Checkout Button Theme',
                    'type' => 'select',
                    'description' => 'Jeeb\'s checkout button preferred theme.',
                    'options' => array(
                        'blue' => 'Blue',
                        'white' => 'White',
                        'transparent' => 'Transparent',
                    ),
                ),

                'btnUrl' => array('type' => 'text'),

            );
        }

        private function run_hooks() {
            /* Add the Gateway to WooCommerce */
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            }

            add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page'));

            //Payment Listener/API hook
            add_action('init', array(&$this, 'process_response'));

            add_action('woocommerce_thankyou_order_received_text', array(&$this, 'process_response'));

            add_action('woocommerce_api_jeeb_notifications', array($this, 'process_webhook'));

            /* Enqueue admin scripts */
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'), 999);
        }

        /* Prepare plugin options */
        private function get_options()
        {

            if (isset($this->settings['expiration']) === false ||
                is_numeric($this->settings['expiration']) === false ||
                $this->settings['expiration'] < 15 ||
                $this->settings['expiration'] > 2880) {
                $this->settings['expiration'] = 15;
            }

            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->order_button_text = $this->settings['content'];
            $this->apikey = $this->settings['apikey'];
            $this->notify_url = WC()->api_request_url('jeeb_notifications');
            $this->base_url = "https://core.jeeb.io/api/v3/";
            $this->test = $this->settings['test'];
            $this->base_currency = $this->settings['baseCurrency'];
            $this->lang = $this->settings['lang'];
            $this->allow_refund = $this->settings['allowRefund'];
            $this->expiration = $this->settings['expiration'];
            $this->icon = $this->settings['btnUrl'] . '" class="jeeb_logo"';
            $this->payable_currencies = "";

            if ($this->settings['payableCurrencies']) {
                $this->payable_currencies = implode('/', $this->settings['payableCurrencies']);
            }

            if ($this->lang === 'none') {
                $this->lang = null;
            }

        }

        // Creates payments on Jeeb
        private function create_payment($order_id)
        {
            global $woocommerce;
            $order = new WC_Order($order_id);
            $amount = $order->get_total();

            $data = array(
                "orderNo" => $order_id,
                "client" => "Internal",
                "type" => "Restricted",
                "mode" => "Fast",
                "payableCoins" => $this->payable_currencies,
                "baseAmount" => $amount,
                "baseCurrencyId" => $this->base_currency,
                "webhookUrl" => $this->notify_url,
                "callbackUrl" => $this->get_return_url(),
                "allowReject" => $this->allow_refund === 'yes' ? true : false,
                "allowTestNets" => $this->test === 'yes' ? true : false,
                "language" => $this->lang,
                "expiration" => $this->expiration,
            );

            $data_string = json_encode($data);

            $url = $this->base_url . 'payments/issue/';

            $response = wp_remote_post(
                $url,
                array(
                    'method' => 'POST',
                    'timeout' => 120,
                    'headers' => array(
                        "content-type" => "application/json",
                        "x-api-key" => $this->apikey,
                    ),
                    'user-agent' => self::PLUGIN_NAME . '/' . self::PLUGIN_VERSION,
                    'body' => $data_string,
                )
            );

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            return $data['result']['token'];
        }

        // Confirms payments on Jeeb
        private function confirm_payment($token)
        {
            $data = array(
                "token" => $token,
            );
            $data_string = json_encode($data);

            $url = $this->base_url . 'payments/seal';

            $response = wp_remote_post(
                $url,
                array(
                    'method' => 'POST',
                    'timeout' => 120,
                    'headers' => array(
                        "content-type" => "application/json",
                        "x-api-key" => $this->apikey,
                    ),
                    'user-agent' => self::PLUGIN_NAME . '/' . self::PLUGIN_VERSION,
                    'body' => $data_string,
                )
            );

            $body = wp_remote_retrieve_body($response);

            return (bool) $body['result']['succeed'];
        }

        // Redirects users to Jeeb
        private function redirect_payment($token)
        {
            $redirect_url = $this->base_url . "payments/invoice?token=" . $token;
            header('Location: ' . $redirect_url);
        }

        // Displaying text on the receipt page and sending requests to Jeeb server.
        public function receipt_page($order_id)
        {
            $token = $this->create_payment($order_id);

            // Redirect user to Jeeb
            $this->redirect_payment($token);
        }

        // Process payment
        public function process_payment($order_id)
        {

            $order = new WC_Order($order_id);

            $order->update_status('pending', 'Awaiting for transaction on Jeeb.');

            if (version_compare(WOOCOMMERCE_VERSION, '2.1.0', '>=')) {
                /* 2.1.0 */
                $checkout_payment_url = $order->get_checkout_payment_url(true);
            } else {
                /* 2.0.0 */
                $checkout_payment_url = get_permalink(get_option('woocommerce_pay_page_id'));
            }

            return array(
                'result' => 'success',
                'redirect' => $checkout_payment_url,
            );
        }

        // Process callbacks from Jeeb
        public function process_response($order_id)
        {
            global $woocommerce;

            $order = new WC_Order($order_id);

            switch ($_REQUEST['state']) {
                case 'PendingConfirmation':
                    if ($_REQUEST['refund'] == 'true') {
                        echo "<h3>Oops!</h3><p>Your Payment will be rejected. To pay again please go to checkout page.</p>";
                    } else {
                        echo "<h3>Thank you!</h3><p>We're waiting for your transaction to gets confirmed. Please be patient.</p>";
                    }
                    break;

                case 'Expired':
                    echo "<h3>Oops!</h3><p>Your Payment is expired or canceled. To pay again please go to checkout page.</p>";
                    break;

                default:
                    echo "<h3>Oops!</h3><p>Payment failed. To pay again please go to checkout page.</p>";
                    break;
            }
        }

        // Processes received webhooks from Jeeb
        public function process_webhook()
        {
            @ob_clean();

            $postdata = file_get_contents("php://input");
            $json = json_decode($postdata, true);

            if (isset($json["token"])) {
                global $woocommerce;
                global $wpdb;

                $order = new WC_Order($json["orderNo"]);

                $token = $json["token"];

                if ($json['state'] == 'PendingTransaction') {
                    $order->add_order_note('Jeeb: Pending transaction.');
                } else if ($json['state'] == 'PendingConfirmation') {

                    // Reduce stock level
                    if (function_exists('wc_reduce_stock_levels')) {
                        wc_reduce_stock_levels($order_id);
                    } else {
                        $order->reduce_order_stock();
                    }

                    // Empty cart
                    WC()->cart->empty_cart();

                    // Add Jeeb's reference no
                    $order->set_transaction_id($json['referenceNo']);

                    // Transaction done successfully but not yet confirmed, so set the Order as On-Hold (Awaiting Payment).
                    if ($json['refund'] == false) {
                        $order->update_status('on-hold', 'Jeeb: Pending confirmation.');
                    } else {
                        // Transaction amount was not equal to payable amount, so set the Order as Refunded.
                        $order->update_status('Refunded', 'Jeeb: Payment will be rejected.');
                    }

                } else if ($json['state'] == 'Completed') {
                    $order->add_order_note('Jeeb: Confirmation occurred for transaction.');

                    $is_confirmed = $this->confirm_payment($token);
                    if ($is_confirmed) {
                        $order->add_order_note('Jeeb: Merchant confirmation obtained. Payment is completed.');
                        $order->payment_complete();
                    } else {
                        $order->update_status('failed', 'Jeeb: Double spending avoided.');
                    }
                } else if ($json['state'] == 'Expired') {
                    $order->update_status('cancelled', 'Jeeb: Payment is expired or canceled.');
                } else if ($json['state'] == 'Rejected') {
                    $order->add_order_note('Jeeb: Payment has been rejected.');
                } else {
                    $order->add_order_note('Jeeb: Unknown state received. Please report this incident.');
                }
                header("HTTP/1.1 200 OK");
                die('1');
            }
            header("HTTP/1.0 404 Not Found");
        }

        /* Enqueue admin scripts */
        function enqueue_admin_scripts()
        {
            if (is_admin()) {
                wp_enqueue_style('jeeb_admin_style', plugins_url('admin.css', __FILE__));
                wp_enqueue_script('jeeb_admin_script', plugins_url('admin.js', __FILE__), array('jquery'), '1.0', true);
            }
        }
    }
}
