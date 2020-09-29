<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Laybuy PrestaShop Module API Capture class
 */
class LaybuyCapture
{
    private $api;

    public function __construct($api) {
        $this->api = $api;
    }
    
    /**
    * Laybuy Capture function
    * Perform the API Call to capture a transaction
    * @return array
    * since 1.0.0
    */
    public function createCapturePayment($params) {
        return $this->api->confirmOrder($params);
    }

    /**
    * Create Order function
    * Create the PrestaShop Order after a successful Capture
    * @param string $laybuy_order_id
    * since 1.0.0
    */
    public function onCaptureSuccess($laybuy_order_id, $laybuy_token) {

        $cart = Context::getContext()->cart;

        $order_status = (int)Configuration::get("PS_OS_PAYMENT");
        $order_total = $cart->getOrderTotal(true, Cart::BOTH);

        $module = Module::getInstanceByName("laybuy");

        $extra_vars = [
            "transaction_id" => $laybuy_order_id
        ];

        $module->validateOrder(
            $cart->id,
            $order_status,
            $order_total,
            "Laybuy",
            null,
            $extra_vars,
            null,
            false,
            $cart->secure_key
        );

        if (Laybuy::$debug) {
            $message = "Laybuy Order Captured Successfully - Laybuy Order ID: " . $laybuy_order_id . "; PrestaShop Cart ID: " . $cart->id;
            PrestaShopLogger::addLog($message, 1, NULL, "Laybuy", 1);
        }
    }
}