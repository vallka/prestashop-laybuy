<?php

class LaybuyConfig implements ArrayAccess
{
    protected $config = [];

    public function __construct()
    {
        $config = Configuration::getMultiple(array(

            'LAYBUY_ENABLED',
            'LAYBUY_DEBUG',
            'LAYBUY_API_ENVIRONMENT',

            'LAYBUY_GLOBAL',
            'LAYBUY_CURRENCY',

            // NZD
            'LAYBUY_SANDBOX_NZD_MERCHANT_ID',
            'LAYBUY_SANDBOX_NZD_API_KEY',
            'LAYBUY_PRODUCTION_NZD_MERCHANT_ID',
            'LAYBUY_PRODUCTION_NZD_API_KEY',

            // AUD
            'LAYBUY_SANDBOX_AUD_MERCHANT_ID',
            'LAYBUY_SANDBOX_AUD_API_KEY',
            'LAYBUY_PRODUCTION_AUD_MERCHANT_ID',
            'LAYBUY_PRODUCTION_AUD_API_KEY',

            // GBP
            'LAYBUY_SANDBOX_GBP_MERCHANT_ID',
            'LAYBUY_SANDBOX_GBP_API_KEY',
            'LAYBUY_PRODUCTION_GBP_MERCHANT_ID',
            'LAYBUY_PRODUCTION_GBP_API_KEY',

            // GLOBAL
            'LAYBUY_SANDBOX_GLOBAL_MERCHANT_ID',
            'LAYBUY_SANDBOX_GLOBAL_API_KEY',
            'LAYBUY_PRODUCTION_GLOBAL_MERCHANT_ID',
            'LAYBUY_PRODUCTION_GLOBAL_API_KEY',
        ));

        $config['CART_CURRENCY'] = Context::getContext()->currency->iso_code;

        $config['LAYBUY_CURRENCY'] = json_decode($config['LAYBUY_CURRENCY'], true);

        $this->config = $config;
    }

    public function offsetExists($offset)
    {
        return isset($this->config[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->config[$offset];
    }

    public function offsetSet($offset, $value)
    {
        return $this->config[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->config[$offset]);
    }
}