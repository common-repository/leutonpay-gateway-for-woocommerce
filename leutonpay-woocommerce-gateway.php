<?php

/**
 * Plugin Name:       LeutonPay - Payment gateway for WooCommerce
 * Plugin URI:        https://www.leuton.com
 * Description:       LeutonPay - Payment gateway for WooCommerce is a modern plugin that allows you to sell anywhere your customers are. Offer your customers a modern payment solution and let them pay you however they want by MTN Mobile Money, VISA and MasterCard
 * Version:           1.0.5
 * Author:            Leuton Group
 * Author URI:        https://leuton.com 
 * License:           GPL v2
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       leuton
 */


/**
 * Try to prevent direct access data leaks
 */
if (!defined('ABSPATH')) exit;


/**
 * Check if WooCommerce is present and active
 **/
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;


/**
 * Add LeutonPay to WooCommerce available gateways
 * @param array $gateways all available WooCommerce gateways
 * @return array $gateways all WooCommerce gateways + LeutonPay gateway
 */
function leutonpay_add_to_woocommerce(array $gateways): array
{
    $gateways[] = 'LeutonpayWoocommerceGateway';
    return $gateways;
}

add_filter('woocommerce_payment_gateways', 'leutonpay_add_to_woocommerce');


/**
 * Called when the plugin loads
 */
function leutonpay_gateway_init()
{
    class LeutonpayWoocommerceGateway extends WC_Payment_Gateway
    {
        const ID = 'leutonpay';
        const ENDPOINT = '/link';
        const BASE_API_URL = 'https://proxy.leuton.com/v1/checkout/';
        const SERVER_IP = '194.163.174.234';
        const CURRENCIES = ['XAF', 'EUR'];
        const SUPPORTED_CURRENCIES = ['XAF' => 1, 'EUR' => 655];

        /**
         * @var string
         */
        private $app_key;
        /**
         * @var string
         */
        private $app_secret;
        /**
         * @var string
         */
        private $callback_url;
        /**
         * @var bool
         */
        private $autocomplete_orders;

        /**
         * Constructor for the gateway.
         */
        public function __construct()
        {
            // The global ID for this Payment method
            $this->id = self::ID;

            // This basically defines your settings which are then loaded with init_settings()
            $this->init_form_fields();

            // After init_settings() is called, you can get the settings and load them into variables
            $this->init_settings();

            // Boolean. Can be set to true if you want payment fields to show on the checkout
            $this->has_fields = false;

            // Image to be displayed to the user
            $image_url = $this->get_image_url();
            // check if LeutonPay icon image exists or not
            if (@getimagesize($image_url)) {
                //Show an image on the frontend
                $this->icon = $image_url;
            }

            // Payment method name for order details and emails
            $this->title = "LeutonPay";

            // Payment method name for admin pages
            $this->method_title = "LeutonPay - Payment gateway for WooCommerce";

            // The description for this Payment Gateway, shown on the actual Payment options page on the backend
            $this->method_description = __(
                "LeutonPay - Payment gateway for WooCommerce is a plugin that allows you to sell anywhere your customers are throw MTN Mobile Money, VISA or MasterCard", 'leuton'
            );

            // Define user set variables
            $this->order_button_text = $this->get_option('order_button_text');
            $this->description = $this->get_option('description');
            $this->app_key = $this->get_option('app_key');
            $this->app_secret = $this->get_option('app_secret');
            $this->callback_url = $this->get_option('callback_url');
            $this->autocomplete_orders = $this->get_option('autocomplete_orders') === 'yes';

            // Used to perform plugin information updated by the admin
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        }

        /**
         * Generates a callback url only once
         */
        private function generate_callback_url()
        {
            if (!$this->get_option('callback_url')) {
                $this->update_option(
                    'callback_url',
                    get_home_url() . '/wp-json/callback/' . md5(uniqid() . wp_rand())
                );
            }
        }

        /**
         * Image to be displayed to the user
         */
        private function get_image_url(): string
        {
            $payment_methods = $this->get_option('payment_methods');
            $image = 'my_coolpay_operators.png';

            if ($payment_methods === 'MOBILE_MONEY')
                $image = 'momo_om.png';
            else if ($payment_methods === 'CREDIT_CARD')
                $image = 'visa_master_card.png';

            return WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__)) . '/images/' . $image;
        }

        /**
         * Initializes gateway settings form
         */
        public function init_form_fields()
        {
            // Generate a callback url
            $this->generate_callback_url();

            $this->form_fields = apply_filters(
                'leutonpay_form_fields',
                [
                    'enabled' => [
                        'title' => __('Enable/Disable', 'leuton'),
                        'type' => 'checkbox',
                        'label' => __('Enable LeutonPay', 'leuton'),
                        'description' => __('Check to enable this plugin', 'leuton'),
                        'default' => 'no',
                        'desc_tip' => true,
                    ],
                    'callback_url' => [
                        'title' => __('Callback URL', 'leuton'),
                        'type' => 'hidden',
                        'description' => sprintf(__('Copy the URL below and paste it in settings of your application in LeutonPay : %s', 'leuton'), 
                            '<code><pre><strong>'.$this->get_option('callback_url').'</code></pre></strong>'),
                        
               
                    ],
                    'description' => [
                        'title' => __('Description', 'leuton'),
                        'type' => 'textarea',
                        'description' => __('Payment method description, visible by customers on your checkout page','leuton'),
                        'default' => __('Payment never been easy and safely using, MTN Mobile Money', 'leuton'),
                        'desc_tip' => true,
                    ],
                    'app_key' => [
                        'title' => __('App Key', 'leuton'),
                        'type' => 'text',
                        'description' => __('Copy the app key of your application in LeutonPay and paste here', 'leuton'),
                        'default' => '',
                        'desc_tip' => true,
                    ],
                    'app_secret' => [
                        'title' => __('App Secret', 'leuton'),
                        'type' => 'text',
                        'description' => __('Copy the app secret of your application in LeutonPay and paste here', 'leuton'),
                        'default' => '',
                        'desc_tip' => true,
                    ],
                    'payment_methods' => [
                        'title' => __('Enabled payment methods', 'leuton'),
                        'type' => 'select',
                        'description' => __('This will change operators logos displayed on your checkout page', 'leuton'),
                        'default' => "ALL",
                        'options' => [
                            "ALL" => __('All', 'leuton'),
                            "MOBILE_MONEY" => __('Mobile Money (OM + MoMo)','leuton'),
                            "CREDIT_CARD" => __('Credit Card (VISA + MasterCard)', 'leuton'),
                        ],
                        'desc_tip' => true,
                    ],
                    'order_button_text' => [
                        'title' => __('Payment button text', 'leuton'),
                        'type' => 'text',
                        'description' => __('Text of the payment button on which customers click to make the payment', 'leuton'),
                        'default' => __('Proceed with LeutonPay', 'leuton'),
                        'desc_tip' => true,
                    ],
                    'currency' => [
                        'title' => __('LeutonPay currency', 'leuton'),
                        'type' => 'select',
                        'description' => __('This is the currency that your customers will see on payment page. If you choose Euro, only card payment will be available on payment page', 'leuton'),
                        'default' => "default",
                        'options' => [
                            "default" => __("Same as WooCommerce", 'leuton'),
                            "XAF" => "CFA Franc (FCFA)",
                            "EUR" => __("Euro (€)", 'leuton'),
                        ],
                        'desc_tip' => true,
                    ],
                    'autocomplete_orders' => array(
                        'title' => __('Autocomplete orders', 'leuton'),
                        'label' => __('Autocomplete orders on payment success', 'leuton'),
                        'type' => 'checkbox',
                        'description' => __('If enabled, orders statuses will go directly to complete after successful payment', 'leuton'),
                        'default' => 'no',
                        'desc_tip' => true,
                    ),
                ]);
        }

        public function get_app_key(): string
        {
            return $this->app_key;
        }

        public function get_app_secret(): string
        {
            return $this->app_secret;
        }

        public function get_callback_url(): string
        {
            return $this->callback_url;
        }

        public function get_autocomplete_orders(): bool
        {
            return $this->autocomplete_orders;
        }

        /**
         * Get order amount and currency for LeutonPay
         * @throws Exception
         */
        private function get_order_amount_currency(WC_Order $order): array
        {
            $woocommerce_currency = get_woocommerce_currency();
            // Throws an exception when currency is not defined in MYCOOLPAY_CURRENCIES
            if (!isset(self::SUPPORTED_CURRENCIES[$woocommerce_currency]))
                throw new Exception("Currency '$woocommerce_currency' is not currently supported. Please, try using one of the following: " . implode(', ', array_keys(self::SUPPORTED_CURRENCIES)));

            $currency = $this->get_option('currency');
            if (!in_array($currency, self::CURRENCIES))
                $currency = $woocommerce_currency;

            $amount = $order->get_total();
            if ($currency !== $woocommerce_currency)
                $amount *= self::SUPPORTED_CURRENCIES[$woocommerce_currency] / self::SUPPORTED_CURRENCIES[$currency];

            return compact('amount', 'currency');
        }

        /**
         * Checks if billing country is CM and billing phone is a valid Mobile Money phone
         */
        private function is_order_from_cameroon(WC_Order $order): bool
        {
            return $order->get_billing_country() === 'CM'
                && preg_match(
                    '/^((\+|00)?237)?6[5789][0-9]{7}$/',
                    preg_replace('/[^0-9]/', '', $order->get_billing_phone()) // Ignore non numeric
                );
        }

        /**
         * Returns user's locale
         */
        private function get_locale(): string
        {
            return strpos('fr_FR', get_locale()) != false ? 'fr' : 'en';
        }

        /**
         * This function handles the processing of the order, telling WooCommerce
         * what status the order should have and where customers go after it’s used.
         */
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            try {
                $order_desc = implode(
                    ', ',
                    array_map(
                        function (WC_Order_Item $item) {
                            return $item->get_name();
                        },
                        $order->get_items()
                    )
                );

                $amount_currency = $this->get_order_amount_currency($order);
                $body = [
                    "amount" => $amount_currency['amount'],
                    "currency" => $amount_currency['currency'],
                    "description" => substr($order_desc, 0, 255), // peek first 255 characters
                    "invoice" => $order->get_order_key(),
                    "locale" => $this->get_locale(),
                    "paymentMode"=>$this->get_option('payment_methods'),
                    "paymentSource" =>"PLUGIN",
                    "metadata" =>"",
                    "customerName" => $order->get_formatted_billing_full_name(),
                    "customerEmail" => $order->get_billing_email(),
                    "customerPhoneNumber"=>""
                ];

                if ($this->is_order_from_cameroon($order))
                    $body['customerPhoneNumber'] = preg_replace('/[^0-9]/', '', $order->get_billing_phone()); // Ignore non numeric
                
                // Get payment link from LeutonPay
                $request = wp_remote_post(self::BASE_API_URL.$this->app_key.self::ENDPOINT,
                    [
                        "body" => wp_json_encode($body),
                        "sslverify" => false,
                        "headers" => [
                            "Content-Type" => "application/json; charset=utf-8"
                        ]
                   ]
                );

                // Get raw response
                $raw_response = wp_remote_retrieve_body($request);

                // Parse response
                $response = json_decode($raw_response, true);

                if (!(isset($response["code"]) && $response["code"] === 201))
                    throw new Exception($response["message"] ?? "Bad init payment error has occurred. Please try again later");

                $order->set_transaction_id($response['paymentId']);
                $order->add_order_note('LeutonPay payment initiated with reference: ' . $response['paymentId']);

                // Clear cart
                WC()->cart->empty_cart();

                return [
                    'result' => 'success',
                    'redirect' => $response['paymentUrl']
                ];

            } catch (Exception $ex) {
                $order->add_order_note("LeutonPay payment init failed with message: " . $ex->getMessage());
                wc_add_notice(__('Payment error : ', 'woothemes') . $ex->getMessage(), 'error');

                if (isset($request)) {
                    leutonpay_log_data('Request <-----');
                    leutonpay_log_data($request);
                }
                if (isset($raw_response)) {
                    leutonpay_log_data('Raw response <-----');
                    leutonpay_log_data($raw_response);
                }
                if (isset($response)) {
                    leutonpay_log_data('Response <-----');
                    leutonpay_log_data($response);
                }

                return;
            }
        }
    }


    /* ================= INCLUDE OTHER FILE =============================== */

    // including the callback
    include 'include/leutonpay_callback.php';

    // including the order_key in wc admin order list
    include 'include/leutonpay_hooks.php';

    /*======================================================================= */
}

add_action('plugins_loaded', 'leutonpay_gateway_init', 0);
