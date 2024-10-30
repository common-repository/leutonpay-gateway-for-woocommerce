<?php

/**
 * Try to prevent direct access data leaks
 */
if (!defined('ABSPATH')) exit;

/**
 * Used to define the callback url route and to call the appropriate function
 */
function leutonpay_register_callback_endpoint()
{
    $leutonpay = new LeutonpayWoocommerceGateway();

    // defines the route
    register_rest_route(
        'callback',
        explode('callback/', $leutonpay->get_callback_url())[1], // Get route from full callback url
        [
            'methods' => 'PUT',
            'callback' => 'leutonpay_handle_callback',
            'permission_callback' => '__return_true'
        ]
    );
}

add_action('rest_api_init', 'leutonpay_register_callback_endpoint');


/**
 * Change a order status after the payment has been performed
 */
function leutonpay_handle_callback(WP_REST_Request $request): WP_REST_Response
{
    // Check the client ip address
    if (LeutonpayWoocommerceGateway::SERVER_IP!==$_SERVER['REMOTE_ADDR'])
        return new WP_REST_Response("Unknown IP address", 403);

    $request_body = $request->get_body();
    // Get data according to content type
    if ($request->get_content_type()['value'] === 'application/json')
        $data = json_decode($request_body, true);
    else
        parse_str($request_body, $data);


    $leutonpay = new LeutonpayWoocommerceGateway();

    // Check app key
    // if ($data['application'] !== $leutonpay->get_app_key())
    //     return new WP_REST_Response("Unknown application", 403);

    // Check Leutonpay signature
    if (!leutonpay_check_signature($leutonpay, $data))
        return new WP_REST_Response("Bad signature", 403);

    // gets a order by the order key
    $order = wc_get_order(wc_get_order_id_by_order_key($data["app_transaction_ref"]));
    if (!$order)
        return new WP_REST_Response("Order not found", 404);

    $transaction_message = $data["transaction_message"];
    $transaction_status = $data["transaction_status"];

    if ($transaction_status === 'SUCCESSFUL') {

        $order->payment_complete();
        $order->add_order_note($transaction_message);

        return new WP_REST_Response("Order completed");

    } else if ($transaction_status === 'CANCELED') {

        $order->update_status('cancelled', $transaction_message);

        return new WP_REST_Response("Order cancelled");

    } else if ($transaction_status === 'FAILED') {

        $order->update_status('failed', $transaction_message);

        return new WP_REST_Response("Order failed");
    }

    return new WP_REST_Response("Unknown transaction_status '$transaction_status'", 400);
}

/**
 * Checks the signature of the request
 */
function leutonpay_check_signature(LeutonpayWoocommerceGateway $gateway, array $data): bool
{
    $md5_key = md5(
            $data["transaction_ref"] . $data["app_transaction_ref"] . $data["transaction_type"]
             . $data["transaction_amount"]
            . $data["transaction_currency"]. $gateway->get_app_secret());
   // leutonpay_log_data("signature : ".$md5_key);
    return $data["signature"] === $md5_key;
}


/**
 * Print received data to the log file
 */
function leutonpay_log_data($data)
{
    // add data in the wordpress log file 
    if (!function_exists('write_log')) {
        function leutonpay_write_log($log)
        {
            if (WP_DEBUG)
                error_log(is_array($log) || is_object($log) ? print_r($log, true) : $log);
        }
    }
    leutonpay_write_log($data);
}
