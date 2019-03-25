<?php

/*
Plugin Name: WooCommerce Jeeb Payment Gateway
Plugin URI: https://core.jeeb.io
Description: Pay With Jeeb Payment gateway for woocommerce
Version: 1.0.0
Author: Jeeb
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_action('plugins_loaded', 'woocommerce_paywith_jeeb_init', 0);


function woocommerce_paywith_jeeb_init(){

    if(!class_exists('WC_Payment_Gateway')) return;


    class WC_Paywith_Jeeb extends WC_Payment_Gateway{

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

        public function __construct(){
            $this->id = 'paywithjeeb';
            $this->method_title = 'Pay With Jeeb';
            $this->has_fields = false;

            $this->init_form_fields();
            $this->init_settings();

            $this -> title = $this -> settings['title'];
            $this -> description = $this -> settings['description'];
            $this -> signature = $this -> settings['signature'];
            $this -> notify_url = WC()->api_request_url('WC_Paywith_Jeeb');
            $this -> base_url = "https://core.jeeb.io/api/";
            $this -> test = $this -> settings['test'];
            $this -> base_cur = $this -> settings['basecoin'];
            $this -> lang = $this -> settings['lang'];
            $this -> target_cur = null;
            // if($this -> base_cur == 'toman')
            for($i=0;$i<sizeof($this -> settings['targetcoin']);$i++){
              $this -> target_cur .= $this -> settings['targetcoin'][$i];
              if( $i!=sizeof($this -> settings['targetcoin'])-1) {
                $this -> target_cur .= '/';
              }
            }
            if($this -> lang === 'none')
              $this -> lang = NULL;

            //Actions
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_'.$this->id, array(&$this, 'process_admin_options' ) );
            } else {
                add_action( 'woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options' ) );
            }

            add_action('woocommerce_receipt_'.$this->id, array(&$this, 'receipt_page'));

            //Payment Listener/API hook
            add_action('init', array(&$this, 'paywith_jeeb_response'));
            //update for woocommerce >2.0
            add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'paywith_jeeb_response' ) );

            add_action('woocommerce_thankyou_order_received_text', array( &$this, 'payment_response'));

            add_action('woocommerce_api_wc_paywith_jeeb', array($this, 'ipn_callback'));
        }

        function init_form_fields(){

            $this -> form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'type' => 'checkbox',
                    'label' => 'Enable Pay With Jeeb Payment Module.',
                    'default' => 'no'),
                'title' => array(
                    'title' => 'Title',
                    'type'=> 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'Pay With Jeeb'),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Pay securely with bitcoins through Jeeb Payment Gateway.'),
                'signature' => array(
                    'title' => 'Signature',
                    'type' => 'text',
                    'description' => 'This signature is the one provided by Jeeb.'),
                'basecoin' => array(
                    'title' => 'Base-Currency',
                    'type' => 'select',
                    'description' => 'The base currency of your website.',
                    'options' => array(
                      'btc' => 'BTC',
                      'eur' => 'EUR',
                      'irr' => 'IRR',
                      'toman'=>'TOMAN',
                      'usd' => 'USD'
                     ),
                   ),
                'targetcoin' => array(
                    'title' => 'Target-Currency',
                    'type' => 'multiselect',
                    'description' => 'The target currency to which your base currency will get converted.',
                    'options' => array(
                      'btc' => 'BTC',
                      'eth' => 'ETH',
                      'xrp' => 'XRP',
                      'xmr' => 'XMR',
                      'bch' => 'BCH',
                      'ltc' => 'LTC',
                      'test-btc' => 'TEST-BTC'
                     ),
                   ),
                 'lang' => array(
                     'title' => 'Language',
                     'type' => 'select',
                     'description' => 'Set the language of the payment portal.',
                     'options' => array(
                       'none' => 'Auto-Select',
                       'en'   => 'English',
                       'fa'   =>'Persian'
                      ),
                    ),
          'test' => array(
        				    'title'   => __( 'Test Jeeb', 'wcjeeb' ),
        				    'type'    => 'checkbox',
        				    'label'   => __( 'Connect to the Test Jeeb server for testing.', 'wcjeeb' ),
        				    'default' => 'no')
            );
        }

        public function admin_options(){
            echo '<h3>Pay With Jeeb Payment Gateway</h3>';
            echo '<p>Jeeb is most popular payment gateway for online shopping in Iran.</p>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this -> generate_settings_html();
            echo '</table>';

        }
        // Get bitcoin equivalent to irr
        function convert_base_to_target ( $order_id ) {
            global $woocommerce;
            $order = new WC_Order( $order_id );
            $amount = $order->total;
            if($this -> base_cur=='toman'){
              $this -> base_cur='irr';
              $amount *= 10;
            }
            $url = $this -> base_url.'currency?'.$this -> signature.'&value='.$amount.'&base='.$this -> base_cur.'&target=btc';

            error_log("Requesting Covert API with Params");
            error_log("Url : " . $url);

            $request = wp_remote_get( $url,
            array(
              'timeout'     => 120
            ) );
            $body = wp_remote_retrieve_body( $request );
            $data = json_decode( $body , true);

            error_log("Response from Convert API");
            error_log($data["result"]);
            // Return the equivalent bitcoin value acquired from Jeeb server.
            return (float) $data["result"];


        }

        // Create invoice for payment
        function create_invoice( $order_id , $amount) {
            global $woocommerce;
            $order = new WC_Order( $order_id );
            $data = array(
              "orderNo"      => $order_id,
              "value"        => $amount,
              "coins"        => $this -> target_cur,
              "webhookUrl"   => $this -> notify_url,
              "callBackUrl"  => $this -> get_return_url(),
              "allowReject"  => $this -> test === 'yes' ? false : true,
              "allowTestNet" => $this -> test === 'yes' ? true : false,
              "language"     => $this -> lang
            );
            $data_string = json_encode($data);

            $url = $this -> base_url . 'payments/' . $this->signature . '/issue/';

            error_log("Creating Invoice with Params");
            error_log("Url : " . $url);
            error_log("Params : " . $data_string);

            $response = wp_remote_post(
                            $url,
                            array(
                                'method'      => 'POST',
                                'timeout'     => 45,
                                'headers'     => array( "content-type" => "application/json"),
                                'body' => $data_string
                            )
                        );

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body , true);

            error_log("Response from Create Invoice API");
            error_log("Response => " . json_encode($data["result"]));

            return $data['result']['token'];

        }

        function redirect_payment( $token ) {

          // Using Auto-submit form to redirect user with the token
          return "<form id='form' method='post' action='". $this -> base_url . "payments/invoice" . "'>".
                  "<input type='hidden' autocomplete='off' name='token' value='".$token."'/>".
                 "</form>".
                 "<script type='text/javascript'>".
                      "document.getElementById('form').submit();".
                 "</script>";

        }

        // Displaying text on the receipt page and sending requests to Jeeb server.
        function receipt_page( $order ) {
            echo '<p>Thank you ! Your order is now pending payment. You should be automatically redirected to Jeeb to make payment.</p>';
            // Convert Base to Target
            $amount = $this->convert_base_to_target ( $order );
            // Create Invoice for payment in the Jeeb server
            $token = $this->create_invoice ( $order , $amount );
            // Redirecting user for the payment
            echo $this->redirect_payment ( $token );
        }

        // Process payment
        function process_payment( $order_id ) {

            $order = new WC_Order( $order_id );

            $order->update_status( 'pending', __( 'Awaiting Bitcoin payment', 'wcjeeb' ) );

            if ( version_compare( WOOCOMMERCE_VERSION, '2.1.0', '>=' ) ) {
                /* 2.1.0 */
                $checkout_payment_url = $order->get_checkout_payment_url( true );
            } else {
                /* 2.0.0 */
                $checkout_payment_url = get_permalink( get_option ( 'woocommerce_pay_page_id' ) );
            }

            return array(
                'result' 	=> 'success',
                'redirect'	=> $checkout_payment_url
            );


        }
        // Process the payment response acquired from Jeeb
        function payment_response( $order_id ) {
            global $woocommerce;
            // error_log("Hey".json_encode($_REQUEST));

            $order = new WC_Order($order_id);

            if ($_REQUEST["stateId"]==3){
              echo "Your Payment was successful and we are awaiting for blockchain network to confirm the payment.";
            }
            if ($_REQUEST["stateId"]==5){
              echo "Your Payment was expired. To pay again please go to checkout page.";
              $order->add_order_note(__('Payment was unsuccessful', 'wcjeeb'));
              // Cancel order
              $order->cancel_order('Bitcoin payment expired.');
            }
            if ($_REQUEST["stateId"]==7){
              echo "Partial payment Received";
              $order->add_order_note(__('Partial Payment was received', 'wcjeeb'));
              // Partial order received, waiting for full payment
              $order->cancel_order(__('Partial Payment was received, hence the payment was rejected.', 'wcjeeb'));
            }

        }

        // Process the notification acquired from Jeeb server
        public function ipn_callback(){
            @ob_clean();

            $postdata = file_get_contents("php://input");
            $json = json_decode($postdata, true);

            if($json["signature"] == $this -> signature)
            {
              global $woocommerce;
              global $wpdb;

              $order = new WC_Order($json["orderNo"]);

              $token = $json["token"];

              if ( $json['stateId']== 2 ) {
                $order->add_order_note(__('Notification from Jeeb - Waiting for payment', 'wcjeeb'));
                error_log("Notification from Jeeb - Waiting for payment");
              }
              else if ( $json['stateId']== 3 ) {
                $order->add_order_note(__('Notification from Jeeb - Waiting for payment confirmation', 'wcjeeb'));

                // Reduce stock level
                if (function_exists('wc_reduce_stock_levels'))
                {
                wc_reduce_stock_levels($order_id);
                }
                else
                {
                    $order->reduce_order_stock();
                }

                // Empty cart
                WC()->cart->empty_cart();

                // Order is Paid but not yet confirmed, put it On-Hold (Awaiting Payment).
                $order->update_status('on-hold', __('Payment received, awaiting confirmation.', 'wcjeeb'));
                error_log("Notification from Jeeb - Waiting for payment confirmation");
              }
              else if ( $json['stateId']== 4 ) {
                $order->add_order_note(__('Payment is now confirmed by blockchain network', 'wcjeeb'));
                error_log("Payment is now confirmed by blockchain network");

                $data = array(
                  "token" => $token,
                );

                $data_string = json_encode($data);

                $url = $this -> base_url . 'payments/' .$json["signature"] . '/confirm' ;
                $response = wp_remote_post(
                                $url,
                                array(
                                    'method'      => 'POST',
                                    'timeout'     => 45,
                                    'headers'     => array( "content-type" => "application/json"),
                                    'body' => $data_string
                                )
                            );

                $body = wp_remote_retrieve_body( $response );
                $response = json_decode( $body , true);

                if($response['result']['isConfirmed']){
                  $order->add_order_note(__('Confirm Payment with jeeb was successful', 'wcjeeb'));
                  error_log("Confirm Payment with jeeb was successful");

                  $order->payment_complete();

                }
                else {
                  $order->add_order_note(__('Confirm Payment was rejected by Jeeb', 'wcjeeb'));
                  error_log("Confirm Payment was rejected by Jeeb");

                  $order->update_status('on-hold', __('Jeeb confirm payment failed', 'wcjeeb'));

                }
              }
              else if ( $json['stateId']== 5 ) {
                $order->add_order_note(__('Notification from Jeeb - Payment was expired', 'wcjeeb'));
                error_log("Notification from Jeeb - Payment was expired");
              }
              else if ( $json['stateId']== 6 ) {
                $order->add_order_note(__('Notification from Jeeb - Over payment occurred', 'wcjeeb'));
                error_log("Notification from Jeeb - Over payment occurred");
              }
              else if ( $json['stateId']== 7 ) {
                $order->add_order_note(__('Notification from Jeeb - Under payment occurred', 'wcjeeb'));
                error_log("Notification from Jeeb - Under payment occurred");
              }
              else{
                $order->add_order_note(__('Notification from Jeeb could not be proccessed - Error in reading state Id ', 'wcjeeb'));
                error_log("Notification from Jeeb could not be proccessed - Error in reading state Id");
              }
          }
          header("HTTP/1.1 200 OK");

        }

        // End of class
}

    /* Add the Gateway to WooCommerce */

    function woocommerce_add_paywith_jeeb_gateway($methods) {
        $methods[] = 'WC_Paywith_Jeeb';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways','woocommerce_add_paywith_jeeb_gateway');
}

?>
