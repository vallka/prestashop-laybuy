<?php

require_once( _PS_MODULE_DIR_ . 'laybuy/classes/LaybuyConfigurationException.php');

class LaybuyApi
{
    const PAYMENT_STATUS_SUCCESS   = 'SUCCESS';
    const PAYMENT_STATUS_ERROR     = 'ERROR';
    const PAYMENT_STATUS_DECLINED  = 'DECLINED';
    const PAYMENT_STATUS_CANCELLED = 'CANCELLED';

    public function __construct(LaybuyApiConfig $laybuyApiConfig)
    {
        $this->apiConfig = $laybuyApiConfig;
    }

    public function createOrder($data)
    {
        return $this->post_to_api($this->apiConfig->getEndpoint() . 'order/create', $data);
    }

    public function confirmOrder($data)
    {
        return $this->post_to_api($this->apiConfig->getEndpoint() . 'order/confirm', $data);
    }

    public function refund($data)
    {
        return $this->post_to_api($this->apiConfig->getEndpoint() . 'order/refund', $data);
    }

    /**
     * POST to an API endpoint and load the response.
     */
    public function post_to_api($url, $data) {

        //Check if CURL module exists.
        if (!function_exists("curl_init")) {
            throw new LaybuyConfigurationException("Curl module is not available on this system");
        }
        try {
            //Curl Implementation

            $ch = curl_init();
            $curlHeaders = $this->createHeaders();

            //Call CURL URL
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_TIMEOUT,80); // Set timeout to 80s
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders); //Pass CURL HEADERS
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); //Do not output response on screen
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_SLASHES));

            // grab URL and pass it to the browser
            $curlResponse = curl_exec($ch);
            $curlResponse = json_decode($curlResponse);

            if (empty($curlResponse) ) {
                throw new LaybuyConfigurationException("Invalid Response from Laybuy API");
            }

            // close cURL resource, and free up system resources
            curl_close($ch);

            return $curlResponse;
        } catch (Exception $e) {
            throw new LaybuyConfigurationException($e->getMessage());
        }
    }

    /**
     * Build the Laybuy User-Agent header for use with the APIs.
     */
    private function build_user_agent_header() {

        $plugin_version = Module::getInstanceByName('laybuy')->version;
        $php_version = PHP_VERSION;
        $ps_version = _PS_VERSION_;
        $merchant_id = $this->apiConfig->get_merchant_id();

        $extra_detail_1 = '';
        $extra_detail_2 = '';

        $matches = array();
        if (array_key_exists('SERVER_SOFTWARE', $_SERVER) && preg_match('/^[a-zA-Z0-9]+\/\d+(\.\d+)*/', $_SERVER['SERVER_SOFTWARE'], $matches)) {
            $s = $matches[0];
            $extra_detail_1 .= "; {$s}";
        }

        if (array_key_exists('REQUEST_SCHEME', $_SERVER) && array_key_exists('HTTP_HOST', $_SERVER)) {
            $s = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
            $extra_detail_2 .= " {$s}";
        }

        return "Laybuy Gateway for Prestashop/{$ps_version} (PHP/{$php_version}; Prestashop Module/{$plugin_version}; Merchant/{$merchant_id}{$extra_detail_1}){$extra_detail_2}";
    }

    /**
     * Create CURL Headers
     *
     * @return array
     */

    Private function createHeaders($customHeaders = "")
    {
        $headers = array(
            'Content-Type:application/json',
            'Authorization: Basic '. base64_encode($this->apiConfig->get_merchant_id().':'.$this->apiConfig->get_api_key()),
            'User-Agent: '.$this->build_user_agent_header() . ';'.$customHeaders
        );
        return $headers;
    }
}