<?php

class LaybuyApiConfig
{
    const PRODUCTION_API_ENDPOINT = 'https://api.laybuy.com/';
    const SANDBOX_API_ENDPOINT = 'https://sandbox-api.laybuy.com/';

    protected $config;

    public function __construct(LaybuyConfig $config)
    {
        $this->config = $config;

        if (!isset($config['LAYBUY_API_ENVIRONMENT'])) {
            return;
        }

        if ($config['LAYBUY_API_ENVIRONMENT'] == 'sandbox') {
            $this->api_endpoint = self::SANDBOX_API_ENDPOINT;
        } else {
            $this->api_endpoint = self::PRODUCTION_API_ENDPOINT;
        }
    }

    public function getEndpoint()
    {
        return $this->api_endpoint;
    }

    /**
     * Get the Merchant ID from our user settings.
     *
     * @since	2.0.0
     * @return	string
     */
    public function get_merchant_id() {
        
        if ($this->isGlobal()) {
            return $this->config[strtoupper("LAYBUY_{$this->config['LAYBUY_API_ENVIRONMENT']}_global_merchant_id")];
        }

        $currency = $this->config['CART_CURRENCY'];

        if (in_array($currency, $this->config['LAYBUY_CURRENCY'])) {
            return $this->config[strtoupper("LAYBUY_{$this->config['LAYBUY_API_ENVIRONMENT']}_{$currency}_merchant_id")];
        }

        return false;
    }

    /**
     * Get the Secret Key from our user settings.
     *
     * @since	2.0.0
     * @return	string
     */
    public function get_api_key() {

        if ($this->isGlobal()) {
            return $this->config[strtoupper("LAYBUY_{$this->config['LAYBUY_API_ENVIRONMENT']}_global_api_key")];
        }

        $currency = $this->config['CART_CURRENCY'];

        if (in_array($currency, $this->config['LAYBUY_CURRENCY'])) {
            return $this->config[strtoupper("LAYBUY_{$this->config['LAYBUY_API_ENVIRONMENT']}_{$currency}_api_key")];
        }

        return false;
    }

    protected function isGlobal()
    {
        return $this->config['LAYBUY_GLOBAL'] === 'Yes';
    }
}