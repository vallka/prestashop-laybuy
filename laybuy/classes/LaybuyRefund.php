<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class LaybuyRefund
{
    protected $api;

    public function __construct(LaybuyApi $api) {
        $this->api = $api;
    }

    public function refund($orderId, $amount)
    {
        return $this->api->refund([
            'orderId' => $orderId,
            'amount' => $amount
        ]);
    }
}