<?php

/*
Plugin Name: WooCommerce Jeeb Payment Gateway
Plugin URI: https://jeeb.io/
Description: Jeeb payment gateway for WooCommerce
Version: 3.0.0
Author: Jeeb
 */

if (!defined('ABSPATH')) {
    exit;
}

// Exit if accessed directly
add_action('plugins_loaded', 'jeeb_payment_gateway_init', 0);

// Load dependencies
add_action('admin_enqueue_scripts', 'admin_scripts', 999);
function admin_scripts()
{
    if (is_admin()) {
        wp_enqueue_style('jeeb_admin_style', plugins_url('admin.css', __FILE__));
        wp_enqueue_script('jeeb_admin_script', plugins_url('admin.js', __FILE__), array('jquery'), '1.0', true);
    }
}

/* Add the Gateway to WooCommerce */

function woocommerce_add_jeeb_payment_gateway($methods)
{
    $methods[] = 'WC_Jeeb_Payment_Gateway';
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'woocommerce_add_jeeb_payment_gateway');

function jeeb_payment_gateway_init()
{

    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Jeeb_Payment_Gateway extends WC_Payment_Gateway
    {
        const PLUGIN_NAME = 'woocommerce';
        const PLUGIN_VERSION = '3.0';

        public function error_log($contents)
        {
            if (false === isset($contents) || true === empty($contents)) {
                return;
            }

            if (true === is_array($contents)) {
                $contents = var_export($contents, true);
            } else if (true === is_object($contents)) {
                $contents = json_encode($contents);
            }

            error_log($contents);
        }

        public function __construct()
        {
            $this->id = 'jeebpaymentgateway';
            $this->method_title = 'Jeeb Payment Gateway';
            $this->method_description = 'Accept BTC and other famous cryptocurrencies.';
            $this->has_fields = false;

            $this->init_form_fields();
            $this->init_settings();
            $this->init_plugin();

            //Actions
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            }

            add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page'));

            //Payment Listener/API hook
            add_action('init', array(&$this, 'process_response'));

            add_action('woocommerce_thankyou_order_received_text', array(&$this, 'process_response'));

            add_action('woocommerce_api_wc_jeeb_payment_gateway', array($this, 'process_webhook'));
        }

        function init_plugin()
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
            $this->signature = $this->settings['signature'];
            $this->notify_url = WC()->api_request_url('WC_Jeeb_Payment_Gateway');
            $this->base_url = "https://core.jeeb.io/api/";
            $this->test = $this->settings['test'];
            $this->base_cur = $this->settings['basecoin'];
            $this->lang = $this->settings['lang'];
            $this->allow_reject = $this->settings['allowrefund'];
            $this->expiration = $this->settings['expiration'];
            $this->target_cur = null;

            $this->icon = $this->settings['btnurl'] . '" class="jeeb_logo"';

            if (is_array($this->settings['targetcoin'])) {
                for ($i = 0; $i < sizeof($this->settings['targetcoin']); $i++) {
                    $this->target_cur .= $this->settings['targetcoin'][$i];
                    if ($i != sizeof($this->settings['targetcoin']) - 1) {
                        $this->target_cur .= '/';
                    }
                }
            } else {
                $this->target_cur = 'BTC';
            }

            if ($this->lang === 'none') {
                $this->lang = null;
            }

        }

        function init_form_fields()
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

                'signature' => array(
                    'title' => 'Signature',
                    'type' => 'text',
                    'description' => 'The signature provided by Jeeb for you merchant.',
                ),

                'basecoin' => array(
                    'title' => 'Base Currency',
                    'type' => 'select',
                    'description' => 'The base currency of your website.',
                    'options' => array(
                        'btc' => 'BTC (Bitcoin)',
                        'usd' => 'USD (US Dollar)',
                        'eur' => 'EUR (Euro)',
                        'irr' => 'IRR (Iranian Rial)',
                        'toman' => 'TOMAN (Iranian Toman)',
                    ),
                ),

                'targetcoin' => array(
                    'title' => 'Payable Currencies',
                    'type' => 'multiselect',
                    'class' => 'jeeb-customized-multiselect',
                    'description' => 'The currencies which users can use for payments.',
                    'options' => array(
                        'btc' => 'BTC',
                        'ltc' => 'LTC',
                        'eth' => 'ETH',
                        'xrp' => 'XRP',
                        'xmr' => 'XMR',
                        'bch' => 'BCH',
                        'test-btc' => 'TEST-BTC',
                        'test-ltc' => 'TEST-LTC',
                    ),
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

                'allowrefund' => array(
                    'title' => 'Allow Refund',
                    'type' => 'checkbox',
                    'label' => 'Allows payments to be refunded.',
                    'default' => 'yes',
                ),

                'test' => array(
                    'title' => 'Allow TestNets',
                    'type' => 'checkbox',
                    'label' => 'Allows testnets such as TEST-BTC to get processed.',
                    'default' => 'no',
                ),

                'expiration' => array(
                    'title' => 'Expiration Time',
                    'type' => 'text',
                    'description' => 'Expands default payments expiration time. It should be between 15 to 2880 (mins).',
                ),

                'btnlang' => array(
                    'title' => 'Checkout Button Language',
                    'type' => 'select',
                    'description' => 'Jeeb\'s checkout button preferred language.',
                    'options' => array(
                        'en' => 'English',
                        'fa' => 'Persian',
                    ),
                ),

                'btntheme' => array(
                    'title' => 'Checkout Button Theme',
                    'type' => 'select',
                    'description' => 'Jeeb\'s checkout button preferred theme.',
                    'options' => array(
                        'blue' => 'Blue',
                        'white' => 'White',
                        'transparent' => 'Transparent',
                    ),
                ),

                'btnurl' => array('type' => 'text'),

            );
        }

        public function admin_options()
        {
            echo '<h3><span><img class="jeeb-logo" src="https://jeeb.io/cdn/en/trans-blue-jeeb.svg"></img</span> Payment Gateway Settings</h3>';
            echo '<p>The first Iranian platform for accepting and processing cryptocurrencies payments.</p>';
            echo '<table class="form-table" id="jeeb-form-table">';
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';
            echo '<input type="hidden" name="jeebCurBtnUrl" id="jeebCurBtnUrl" value="' . $this->settings['btnurl'] . '"/>';
        }

        // Gets bitcoin equivalent of base currency and order's payable amount
        private function convert_base_to_bitcoin($order_id)
        {
            global $woocommerce;

            $order = new WC_Order($order_id);
            $amount = $order->total;

            if ($this->base_cur == 'toman') {
                $this->base_cur = 'irr';
                $amount *= 10;
            }

            $url = $this->base_url . 'currency?value=' . $amount . '&base=' . $this->base_cur . '&target=btc';

            $request = wp_remote_get($url,
                array(
                    'timeout' => 120,
                    'headers' => array("content-type" => "application/json"),
                    'user-agent' => self::PLUGIN_NAME . '/' . self::PLUGIN_VERSION,
                ));
            $body = wp_remote_retrieve_body($request);
            $data = json_decode($body, true);

            return (float) $data["result"];
        }

        // Creates payments on Jeeb
        private function create_payment($order_id, $amount)
        {
            global $woocommerce;
            $order = new WC_Order($order_id);
            $data = array(
                "orderNo" => $order_id,
                "value" => $amount,
                "coins" => $this->target_cur,
                "webhookUrl" => $this->notify_url,
                "callBackUrl" => $this->get_return_url(),
                "allowReject" => $this->allow_reject === 'yes' ? true : false,
                "allowTestNet" => $this->test === 'yes' ? true : false,
                "language" => $this->lang,
                "expiration" => $this->expiration,
            );
            $data_string = json_encode($data);

            $url = $this->base_url . 'payments/' . $this->signature . '/issue/';

            $response = wp_remote_post(
                $url,
                array(
                    'method' => 'POST',
                    'timeout' => 120,
                    'headers' => array("content-type" => "application/json"),
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

            $url = $this->base_url . 'payments/' . $this->signature . '/confirm';

            $response = wp_remote_post(
                $url,
                array(
                    'method' => 'POST',
                    'timeout' => 120,
                    'headers' => array("content-type" => "application/json"),
                    'user-agent' => self::PLUGIN_NAME . '/' . self::PLUGIN_VERSION,
                    'body' => $data_string,
                )
            );

            $body = wp_remote_retrieve_body($response);
            $response = json_decode($body, true);

            return (bool) $response['result']['isConfirmed'];
        }

        // Redirects users to Jeeb
        private function redirect_payment($token)
        {
            $redirect_url = $this->base_url . "payments/invoice?token=" . $token;
            header('Location: ' . $redirect_url);
        }

        // Displaying text on the receipt page and sending requests to Jeeb server.
        public function receipt_page($order)
        {
            // Convert base currency to bitcoin
            $amount = $this->convert_base_to_bitcoin($order);
            // Create payment on Jeeb
            $token = $this->create_payment($order, $amount);
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

            if ($_REQUEST["stateId"] == 3) {
                echo "<h3>Thank you!</h3><p>We're waiting for your transaction to gets confirmed. Please be patient.</p>";
            } else if ($_REQUEST["stateId"] == 5) {
                echo "<h3>Oops!</h3><p>Your Payment is expired or canceled. To pay again please go to checkout page.</p>";
            } else {
                echo '<h3>We\'re sorry!</h3><p>An unknown state occurred in your payment. Please contact our support and report the incident.</p>';
            }
        }

        // Processes received webhooks from Jeeb
        public function process_webhook()
        {
            @ob_clean();

            $postdata = file_get_contents("php://input");
            $json = json_decode($postdata, true);

            if ($json["signature"] == $this->signature) {
                global $woocommerce;
                global $wpdb;

                $order = new WC_Order($json["orderNo"]);

                $token = $json["token"];

                if ($json['stateId'] == 2) {
                    $order->add_order_note('Jeeb: Pending transaction.');
                } else if ($json['stateId'] == 3) {
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
                    // Order is Paid but not yet confirmed, put it On-Hold (Awaiting Payment).
                    $order->update_status('on-hold', 'Jeeb: Pending confirmation.');

                } else if ($json['stateId'] == 4) {
                    $order->add_order_note('Jeeb: Confirmation occurred for transaction.');

                    $is_confirmed = $this->confirm_payment($token);
                    if ($is_confirmed) {
                        $order->add_order_note('Jeeb: Merchant confirmation obtained. Payment is completed.');
                        $order->payment_complete();
                    } else {
                        $order->update_status('failed', 'Jeeb: Double spending avoided.');
                    }
                } else if ($json['stateId'] == 5) {
                    $order->update_status('cancelled', 'Jeeb: Payment is expired or canceled.');
                } else if ($json['stateId'] == 6) {
                    $order->update_status('refunded', 'Jeeb: Partial-paid payment occurred, transaction was refunded automatically.');
                } else if ($json['stateId'] == 7) {
                    $order->update_status('refunded', 'Jeeb: Overpaid payment occurred, transaction was refunded automatically.');
                } else {
                    $order->add_order_note('Jeeb: Unknown state received. Please report this incident.');
                }
                header("HTTP/1.1 200 OK");
            }
            header("HTTP/1.0 404 Not Found");
        }
    }
}
