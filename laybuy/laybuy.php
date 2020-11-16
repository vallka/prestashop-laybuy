<?php
/*
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*/

require_once( _PS_MODULE_DIR_ . 'laybuy/classes/LaybuyRefund.php');
require_once( _PS_MODULE_DIR_ . 'laybuy/classes/LaybuyConfig.php');
require_once( _PS_MODULE_DIR_ . 'laybuy/classes/LaybuyApiConfig.php');
require_once( _PS_MODULE_DIR_ . 'laybuy/classes/LaybuyApi.php');
require_once( _PS_MODULE_DIR_ . 'laybuy/classes/LaybuyHelper.php');

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}


/**
 * Class Laybuy
 *
 * Base Class for the entire Laybuy PrestaShop Module
 */
class Laybuy extends PaymentModule
{
    public static $debug = null;
    protected $enabled = null;

    const PAYMENTS_COUNT = 6;

    protected $pay_over_time_limit_min = 0.06;
    protected $pay_over_time_limit_max = [
        'NZD' => 1500,
        'AUD' => 1200,
        'GBP' => 720
    ];

    protected $supported_currencies = ['AUD', 'NZD', 'GBP'];

    /**
     * Constructor function
     * Set up the Module details and initial configurations
     * since 1.0.0
     */
    public function __construct()
    {
        $this->name = 'laybuy';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.1';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'Laybuy';
        $this->controllers = array('validation', 'return');

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->_init_configurations();

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Laybuy Payment Gateway');
        $this->description = $this->l('This is a payment gateway module for Laybuy');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    /**
     * Install function
     * Set up the Module Hooks
     * since 1.0.0
     */
    public function install() {

        if  (!parent::install()
                || !$this->registerHook('paymentOptions')
                || !$this->registerHook('paymentReturn')
                || !$this->registerHook('actionOrderStatusUpdate')
                || !$this->registerHook('actionProductCancel')
                || !$this->registerHook('actionOrderSlipAdd')
                || !$this->registerHook('displayProductPriceBlock')
                || !$this->registerHook('displayHeader')
                || !$this->registerHook('displayBackOfficeHeader')
                || !$this->registerHook('displayExpressCheckout')
            ) {
            return false;
        }
        return true;
    }

    /**
     * Main Hook for Payment Module
     * Set up the Module Hooks
     * @param array $params
     * @return array
     * since 1.0.0
     */
    public function hookPaymentOptions($params) {

        if (!$this->isEnabledOrSupported()) {
            return;
        }

        $total = $this->context->cart->getOrderTotal();

        if ($total <= $this->getPayOverTimeLimitMin() || !$this->checkCurrency($params['cart'])) {
            return;
        }

        $payment_options = [
            $this->getExternalPaymentOption(),
        ];

        return $payment_options;
    }

    /**
     * Main Function to output Laybuy in the checkout
     * Set up the Module Hooks
     * @return PaymentOption
     * since 1.0.0
     */
    public function getExternalPaymentOption() {
        $externalOption = new PaymentOption();

        $cart = $this->context->cart;
        $amount = $cart->getOrderTotal();

        if ($this->_isPriceWithinLimits($amount)) {
            $tplVars['workflow'] = 'standard';
            $tplVars['amount'] = $this->_formatPrice($amount / self::PAYMENTS_COUNT);
        } else {

            if ($amount < $this->getPayOverTimeLimitMin()) {
                return;
            } else {

                $tplVars['amount'] = $this->_formatPrice($this->getPayOverTimeLimitMax() / 5);
                $tplVars['pay_today'] = $this->_formatPrice($amount - $this->getPayOverTimeLimitMax());

                $tplVars['workflow'] = 'pay_today';
            }
        }

        $this->context->smarty->assign( $tplVars );

        $externalOption->setCallToActionText($this->l('Pay with '))
                       ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                       ->setAdditionalInformation($this->context->smarty->fetch('module:laybuy/views/templates/front/payment_infos.tpl'))
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/images/payment_checkout.png'));

        return $externalOption;
    }

    /**
     * Initialise the configuration values
     * since 1.0.0
     */
    private function _init_configurations() {

        $config = Configuration::getMultiple(array(
            'LAYBUY_ENABLED',
            'LAYBUY_DEBUG',
        ));

        $this->enabled = (bool) $config['LAYBUY_ENABLED'];

        self::$debug = (bool) $config['LAYBUY_DEBUG'];
    }

    /**
    * getContent() is required to show the "Configuration Page" option on Module Page
    * @return string
    * since 1.0.0
    */
    public function getContent() {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name)) {
            $output = $this->_validate_configuration();
        }

        return $output . $this->displayForm();
    }


    /**
    * Validating the Configuration Form and append the output
    * @return string
    * since 1.0.0
    */
    private function _validate_configuration() {

        $laybuy_enabled         = strval(Tools::getValue('LAYBUY_ENABLED'));
        $laybuy_api_environment = strval(Tools::getValue('LAYBUY_API_ENVIRONMENT'));
        $laybuy_global          = strval(Tools::getValue('LAYBUY_GLOBAL'));
        $laybuy_currency        = json_encode(Tools::getValue('LAYBUY_CURRENCY'));

        $laybuy_sandbox_nzd_merchant_id    = strval(Tools::getValue('LAYBUY_SANDBOX_NZD_MERCHANT_ID'));
        $laybuy_sandbox_nzd_api_key        = strval(Tools::getValue('LAYBUY_SANDBOX_NZD_API_KEY'));
        $laybuy_production_nzd_merchant_id = strval(Tools::getValue('LAYBUY_PRODUCTION_NZD_MERCHANT_ID'));
        $laybuy_production_nzd_api_key     = strval(Tools::getValue('LAYBUY_PRODUCTION_NZD_API_KEY'));

        $laybuy_sandbox_aud_merchant_id    = strval(Tools::getValue('LAYBUY_SANDBOX_AUD_MERCHANT_ID'));
        $laybuy_sandbox_aud_api_key        = strval(Tools::getValue('LAYBUY_SANDBOX_AUD_API_KEY'));
        $laybuy_production_aud_merchant_id = strval(Tools::getValue('LAYBUY_PRODUCTION_AUD_MERCHANT_ID'));
        $laybuy_production_aud_api_key     = strval(Tools::getValue('LAYBUY_PRODUCTION_AUD_API_KEY'));

        $laybuy_sandbox_gbp_merchant_id    = strval(Tools::getValue('LAYBUY_SANDBOX_GBP_MERCHANT_ID'));
        $laybuy_sandbox_gbp_api_key        = strval(Tools::getValue('LAYBUY_SANDBOX_GBP_API_KEY'));
        $laybuy_production_gbp_merchant_id = strval(Tools::getValue('LAYBUY_PRODUCTION_GBP_MERCHANT_ID'));
        $laybuy_production_gbp_api_key     = strval(Tools::getValue('LAYBUY_PRODUCTION_GBP_API_KEY'));

        $laybuy_sandbox_global_merchant_id    = strval(Tools::getValue('LAYBUY_SANDBOX_GLOBAL_MERCHANT_ID'));
        $laybuy_sandbox_global_api_key        = strval(Tools::getValue('LAYBUY_SANDBOX_GLOBAL_API_KEY'));
        $laybuy_production_global_merchant_id = strval(Tools::getValue('LAYBUY_PRODUCTION_GLOBAL_MERCHANT_ID'));
        $laybuy_production_global_api_key     = strval(Tools::getValue('LAYBUY_PRODUCTION_GLOBAL_API_KEY'));

        $laybuy_debug = strval(Tools::getValue('LAYBUY_DEBUG'));

        $output = "";

        Configuration::updateValue('LAYBUY_ENABLED', $laybuy_enabled);
        Configuration::updateValue('LAYBUY_API_ENVIRONMENT', $laybuy_api_environment);
        Configuration::updateValue('LAYBUY_GLOBAL', $laybuy_global);
        Configuration::updateValue('LAYBUY_CURRENCY', $laybuy_currency);

        Configuration::updateValue('LAYBUY_SANDBOX_NZD_MERCHANT_ID', $laybuy_sandbox_nzd_merchant_id);
        Configuration::updateValue('LAYBUY_SANDBOX_NZD_API_KEY', $laybuy_sandbox_nzd_api_key);
        Configuration::updateValue('LAYBUY_PRODUCTION_NZD_MERCHANT_ID', $laybuy_production_nzd_merchant_id);
        Configuration::updateValue('LAYBUY_PRODUCTION_NZD_API_KEY', $laybuy_production_nzd_api_key);

        Configuration::updateValue('LAYBUY_SANDBOX_AUD_MERCHANT_ID', $laybuy_sandbox_aud_merchant_id);
        Configuration::updateValue('LAYBUY_SANDBOX_AUD_API_KEY', $laybuy_sandbox_aud_api_key);
        Configuration::updateValue('LAYBUY_PRODUCTION_AUD_MERCHANT_ID', $laybuy_production_aud_merchant_id);
        Configuration::updateValue('LAYBUY_PRODUCTION_AUD_API_KEY', $laybuy_production_aud_api_key);

        Configuration::updateValue('LAYBUY_SANDBOX_GBP_MERCHANT_ID', $laybuy_sandbox_gbp_merchant_id);
        Configuration::updateValue('LAYBUY_SANDBOX_GBP_API_KEY', $laybuy_sandbox_gbp_api_key);
        Configuration::updateValue('LAYBUY_PRODUCTION_GBP_MERCHANT_ID', $laybuy_production_gbp_merchant_id);
        Configuration::updateValue('LAYBUY_PRODUCTION_GBP_API_KEY', $laybuy_production_gbp_api_key);

        Configuration::updateValue('LAYBUY_SANDBOX_GLOBAL_MERCHANT_ID', $laybuy_sandbox_global_merchant_id);
        Configuration::updateValue('LAYBUY_SANDBOX_GLOBAL_API_KEY', $laybuy_sandbox_global_api_key);
        Configuration::updateValue('LAYBUY_PRODUCTION_GLOBAL_MERCHANT_ID', $laybuy_production_global_merchant_id);
        Configuration::updateValue('LAYBUY_PRODUCTION_GLOBAL_API_KEY', $laybuy_production_global_api_key);

        Configuration::updateValue('LAYBUY_DEBUG', $laybuy_debug);

        $output .= $this->displayConfirmation($this->l('Settings updated'));

        return $output;
    }

    /**
    * DisplayFrom() is required to show the "Configuration Form Page"
    * @return string
    * since 1.0.0
    */
    public function displayForm() {

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend'    => array(
                'title' => $this->l('Settings'),
            ),
            'input' => array(
                // Enabled / Disabled?
                array(
                    'type'      =>  'select',
                    'label'     =>  $this->l('Enabled'),
                    'name'      =>  'LAYBUY_ENABLED',
                    'options'   =>  array(
                        'query' =>  array(
                            array(
                                'enabled'       =>  0,
                                'enabled_name'  =>  'No'
                            ),
                            array(
                                'enabled'       =>  1,
                                'enabled_name'  =>  'Yes'
                            )
                        ),
                        'id'    => 'enabled',
                        'name'  => 'enabled_name'
                    ),
                    'required'  =>  false
                ),
                array(
                    'type'      =>  'select',
                    'label'     =>  $this->l('Laybuy Global'),
                    'name'      =>  'LAYBUY_GLOBAL',
                    'required'  =>  false,
                    'options'   =>  array(
                        'query' =>  array(
                            array(
                                'enabled'       =>  false,
                                'laybuy_global' =>  'No'
                            ),
                            array(
                                'enabled'       =>  true,
                                'laybuy_global' =>  'Yes'
                            )
                        ),
                        'id'    => 'laybuy_global',
                        'name'  => 'laybuy_global'
                    ),
                ),

                array(
                    'type'    => 'select',
                    'multiple' => true,
                    'name'      =>  'LAYBUY_CURRENCY',
                    'id' => 'LAYBUY_CURRENCY',
                    'options'   =>  array(
                        'query' =>  array(
                            array(
                                'laybuy_currency' =>  'NZD',
                            ),
                            array(
                                'laybuy_currency' =>  'AUD'
                            ),
                            array(
                                'laybuy_currency' => 'GBP'
                            )
                        ),
                        'id'    => 'laybuy_currency',
                        'name'  => 'laybuy_currency'
                    ),
                    'label'     =>  $this->l('Supported Currencies'),
                ),

                // Global Sandbox
                array(
                    'type'      =>  'text',
                    'label'     =>  $this->l('Global Merchant ID (sandbox)'),
                    'name'      =>  'LAYBUY_SANDBOX_GLOBAL_MERCHANT_ID',
                    'size'      =>  5,
                    'required'  =>  false
                ),
                array(
                    'type'      =>  'text',
                    'name'      =>  'LAYBUY_SANDBOX_GLOBAL_API_KEY',
                    'label'     =>  $this->l('Global API Key (sandbox)'),
                    'size'      =>  5,
                    'required'  =>  false
                ),

                // Global Production
                array(
                    'type'      =>  'text',
                    'label'     =>  $this->l('Global Merchant ID (production)'),
                    'name'      =>  'LAYBUY_PRODUCTION_GLOBAL_MERCHANT_ID',
                    'size'      =>  5,
                    'required'  =>  false
                ),
                array(
                    'type'      =>  'text',
                    'name'      =>  'LAYBUY_PRODUCTION_GLOBAL_API_KEY',
                    'label'     =>  $this->l('Global API Key (production)'),
                    'size'      =>  5,
                    'required'  =>  false
                ),
                
                // NZD Sandbox
                array(
                    'type'      =>  'text',
                    'label'     =>  $this->l('NZD Merchant ID (sandbox)'),
                    'name'      =>  'LAYBUY_SANDBOX_NZD_MERCHANT_ID',
                    'size'      =>  5,
                    'required'  =>  false
                ),
                array(
                    'type'      =>  'text',
                    'name'      =>  'LAYBUY_SANDBOX_NZD_API_KEY',
                    'label'     =>  $this->l('NZD API Key (sandbox)'),
                    'size'      =>  5,
                    'required'  =>  false
                ),

                // NZD Production
                array(
                    'type'      =>  'text',
                    'label'     =>  $this->l('NZD Merchant ID (production)'),
                    'name'      =>  'LAYBUY_PRODUCTION_NZD_MERCHANT_ID',
                    'size'      =>  5,
                    'required'  =>  false
                ),
                array(
                    'type'      =>  'text',
                    'name'      =>  'LAYBUY_PRODUCTION_NZD_API_KEY',
                    'label'     =>  $this->l('NZD API Key (production)'),
                    'size'      =>  5,
                    'required'  =>  false
                ),

                // AUD Sandbox
                array(
                    'type'      =>  'text',
                    'label'     =>  $this->l('AUD Merchant ID (sandbox)'),
                    'name'      =>  'LAYBUY_SANDBOX_AUD_MERCHANT_ID',
                    'size'      =>  5,
                    'required'  =>  false
                ),
                array(
                    'type'      =>  'text',
                    'name'      =>  'LAYBUY_SANDBOX_AUD_API_KEY',
                    'label'     =>  $this->l('AUD API Key (sandbox)'),
                    'size'      =>  5,
                    'required'  =>  false
                ),

                // AUD Production
                array(
                    'type'      =>  'text',
                    'label'     =>  $this->l('AUD Merchant ID (production)'),
                    'name'      =>  'LAYBUY_PRODUCTION_AUD_MERCHANT_ID',
                    'size'      =>  5,
                    'required'  =>  false
                ),
                array(
                    'type'      =>  'text',
                    'name'      =>  'LAYBUY_PRODUCTION_AUD_API_KEY',
                    'label'     =>  $this->l('AUD API Key (production)'),
                    'size'      =>  5,
                    'required'  =>  false
                ),

                // GBP Sandbox
                array(
                    'type'      =>  'text',
                    'label'     =>  $this->l('GBP Merchant ID (sandbox)'),
                    'name'      =>  'LAYBUY_SANDBOX_GBP_MERCHANT_ID',
                    'size'      =>  5,
                    'required'  =>  false
                ),
                array(
                    'type'      =>  'text',
                    'name'      =>  'LAYBUY_SANDBOX_GBP_API_KEY',
                    'label'     =>  $this->l('GBP API Key (sandbox)'),
                    'size'      =>  5,
                    'required'  =>  false
                ),

                // GBP Production
                array(
                    'type'      =>  'text',
                    'label'     =>  $this->l('GBP Merchant ID (production)'),
                    'name'      =>  'LAYBUY_PRODUCTION_GBP_MERCHANT_ID',
                    'size'      =>  5,
                    'required'  =>  false
                ),
                array(
                    'type'      =>  'text',
                    'name'      =>  'LAYBUY_PRODUCTION_GBP_API_KEY',
                    'label'     =>  $this->l('GBP API Key (production)'),
                    'size'      =>  5,
                    'required'  =>  false
                ),

                array(
                    'type'      =>  'select',
                    'label'     =>  $this->l('API Environment'),
                    'name'      =>  'LAYBUY_API_ENVIRONMENT',
                    'options'   =>  array(
                        'query' =>  array(
                            array(
                                'api_mode'  =>  'sandbox',
                                'api_name'  =>  'Sandbox'
                            ),
                            array(
                                'api_mode'  =>  'production',
                                'api_name'  =>  'Production'
                            )
                        ),
                        'id'    => 'api_mode',
                        'name'  => 'api_name'
                    ),
                    'required' => false
                ),

                // Debug
                array(
                    'type'      =>  'select',
                    'label'     =>  $this->l('Debug'),
                    'name'      =>  'LAYBUY_DEBUG',
                    'options'   =>  array(
                        'query' =>  array(
                            array(
                                'enabled'       =>  0,
                                'enabled_name'  =>  'No'
                            ),
                            array(
                                'enabled'       =>  1,
                                'enabled_name'  =>  'Yes'
                            )
                        ),
                        'id'    => 'enabled',
                        'name'  => 'enabled_name'
                    ),
                    'required'  =>  false
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;


        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' =>
            array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                '&token='.Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        $helper->fields_value['LAYBUY_ENABLED']            = Configuration::get('LAYBUY_ENABLED');
        $helper->fields_value['LAYBUY_API_ENVIRONMENT']    = Configuration::get('LAYBUY_API_ENVIRONMENT');
        $helper->fields_value['LAYBUY_GLOBAL']             = Configuration::get('LAYBUY_GLOBAL');
        $helper->fields_value['LAYBUY_CURRENCY[]']         = json_decode(Configuration::get('LAYBUY_CURRENCY'), true);

        $helper->fields_value['LAYBUY_SANDBOX_NZD_MERCHANT_ID']    = Configuration::get('LAYBUY_SANDBOX_NZD_MERCHANT_ID');
        $helper->fields_value['LAYBUY_SANDBOX_NZD_API_KEY']        = Configuration::get('LAYBUY_SANDBOX_NZD_API_KEY');
        $helper->fields_value['LAYBUY_PRODUCTION_NZD_MERCHANT_ID'] = Configuration::get('LAYBUY_PRODUCTION_NZD_MERCHANT_ID');
        $helper->fields_value['LAYBUY_PRODUCTION_NZD_API_KEY']     = Configuration::get('LAYBUY_PRODUCTION_NZD_API_KEY');

        $helper->fields_value['LAYBUY_SANDBOX_AUD_MERCHANT_ID']    = Configuration::get('LAYBUY_SANDBOX_AUD_MERCHANT_ID');
        $helper->fields_value['LAYBUY_SANDBOX_AUD_API_KEY']        = Configuration::get('LAYBUY_SANDBOX_AUD_API_KEY');
        $helper->fields_value['LAYBUY_PRODUCTION_AUD_MERCHANT_ID'] = Configuration::get('LAYBUY_PRODUCTION_AUD_MERCHANT_ID');
        $helper->fields_value['LAYBUY_PRODUCTION_AUD_API_KEY']     = Configuration::get('LAYBUY_PRODUCTION_AUD_API_KEY');

        $helper->fields_value['LAYBUY_SANDBOX_GBP_MERCHANT_ID']     = Configuration::get('LAYBUY_SANDBOX_GBP_MERCHANT_ID');
        $helper->fields_value['LAYBUY_SANDBOX_GBP_API_KEY']         = Configuration::get('LAYBUY_SANDBOX_GBP_API_KEY');
        $helper->fields_value['LAYBUY_PRODUCTION_GBP_MERCHANT_ID']  = Configuration::get('LAYBUY_PRODUCTION_GBP_MERCHANT_ID');
        $helper->fields_value['LAYBUY_PRODUCTION_GBP_API_KEY']      = Configuration::get('LAYBUY_PRODUCTION_GBP_API_KEY');

        $helper->fields_value['LAYBUY_SANDBOX_GLOBAL_MERCHANT_ID']    = Configuration::get('LAYBUY_SANDBOX_GLOBAL_MERCHANT_ID');
        $helper->fields_value['LAYBUY_SANDBOX_GLOBAL_API_KEY']        = Configuration::get('LAYBUY_SANDBOX_GLOBAL_API_KEY');
        $helper->fields_value['LAYBUY_PRODUCTION_GLOBAL_MERCHANT_ID'] = Configuration::get('LAYBUY_PRODUCTION_GLOBAL_MERCHANT_ID');
        $helper->fields_value['LAYBUY_PRODUCTION_GLOBAL_API_KEY']     = Configuration::get('LAYBUY_PRODUCTION_GLOBAL_API_KEY');

        $helper->fields_value['LAYBUY_DEBUG']            = Configuration::get('LAYBUY_DEBUG');

        return $helper->generateForm($fields_form);
    }

    /*-----------------------------------------------------------------------------------------------------------------------
                                                    Start of Refund Codes
    -----------------------------------------------------------------------------------------------------------------------*/

    /**
    * Hook Action for Order Status Update (handles Refunds)
    * @param array $params
    * @return bool
    * since 1.0.0
    */
    public function hookActionOrderStatusUpdate($params) {

        if (!$this->isEnabledOrSupported()) {
            return;
        }

        if (
            empty($params) ||
            empty($params['id_order']) ||
            empty($params['newOrderStatus'])
        ) {
            return;
        }

        $order = new Order((int)$params['id_order']);

        if (strtolower($order->module) !== 'laybuy') {
            return;
        }

        $new_order_status = $params['newOrderStatus'];

        if ($new_order_status->id != _PS_OS_REFUND_) {
            return;
        }

        $laybuyConfig    = new LaybuyConfig();
        $laybuyApiConfig = new LaybuyApiConfig($laybuyConfig);
        $laybuyApi       = new LaybuyApi($laybuyApiConfig);
        $laybuyRefund    = new LaybuyRefund($laybuyApi);

        $cart = new Cart($order->id_cart);
        $refundAmount = $cart->getOrderTotal();

        $payments = $order->getOrderPayments();
        $laybuyOrderId = $payments[0]->transaction_id;

        $result = $laybuyRefund->refund($laybuyOrderId, $refundAmount);

        if ($result->result !== 'SUCCESS') {

            $return_url = $_SERVER['HTTP_REFERER'];

            echo $result->error;
            echo "<br/><a href='" . $return_url . "'>Return to Order Details</a>";

            if (Laybuy::$debug) {
                $message = "Laybuy Full Refund Error: " . $result->error;
                PrestaShopLogger::addLog($message, 2, NULL, "Laybuy", 1);
            }

            die();
        }
    }

    /**
    * Hook Action for Partial Refunds
    * @param array $params
    * since 1.0.0
    */
    public function hookActionOrderSlipAdd($params) {

        if (!$this->isEnabledOrSupported()) {
            return;
        }

        if (
            empty($params) ||
            empty($params["order"])
        ) {
            return;
        }

        $order = new Order((int)$params["order"]->id);

        $payments = $order->getOrderPayments();
        $laybuyOrderId = $payments[0]->transaction_id;

        $refund_products_list   =   $params["productList"];
        $refund_total_amount    =   0;

        foreach( $refund_products_list as $key => $item ) {
            $refund_total_amount += $item["amount"];
        }

        $laybuyConfig    = new LaybuyConfig();
        $laybuyApiConfig = new LaybuyApiConfig($laybuyConfig);
        $laybuyApi       = new LaybuyApi($laybuyApiConfig);
        $laybuyRefund    = new LaybuyRefund($laybuyApi);

        $result = $laybuyRefund->refund($laybuyOrderId, $refund_total_amount);

        if ($result->result !== 'SUCCESS') {

            $return_url = $_SERVER['HTTP_REFERER'];

            echo $result->error;
            echo "<br/><a href='" . $return_url . "'>Return to Order Details</a>";

            if (Laybuy::$debug) {
                $message = "Laybuy Partial Refund Error: " . $result->error;
                PrestaShopLogger::addLog($message, 2, NULL, "Laybuy", 1);
            }

            die();
        }
    }

    /**
    * Function to display the Laybuy Product Price Payment Breakdown
    * @param array $params
    * @return TPL
    * since 1.0.0
    */
    public function hookDisplayProductPriceBlock($params) {

        if (!$this->isEnabledOrSupported()) {
            return;
        }

        $current_controller = Tools::getValue('controller');

        if (
            $current_controller !== "product" ||
            $params["type"] !== "after_price" ||
            !$params["product"]["price_amount"]
        ) {
            return;
        }

        $tplVars = [
            'logo_url' => 'https://integration-assets.laybuy.com/woocommerce_laybuy_icons/laybuy_logo_small.svg',
            'attrs' => [
                'width' => '80px',
                'link' => '',
                'style' => 'float:none; display:inline-block; vertical-align:middle; height:1.2em; top: -2px; position: relative;'
            ]
        ];

        if ($this->_isPriceWithinLimits($params["product"]["price_amount"])) {
            $tplVars['workflow'] = 'standard';
            $tplVars['amount'] = $this->_formatPrice($params["product"]["price_amount"] / self::PAYMENTS_COUNT);
        } else {

            if ($params["product"]["price_amount"] < $this->pay_over_time_limit_min) {
                return;
            } else {

                $tplVars['amount']    = $this->_formatPrice($this->getPayOverTimeLimitMax() / 5);
                $tplVars['pay_today'] = $this->_formatPrice($params["product"]["price_amount"] - $this->getPayOverTimeLimitMax());

                $tplVars['workflow'] = 'pay_today';
            }
        }

        $this->context->smarty->assign($tplVars);
        return $this->context->smarty->fetch("module:laybuy/views/templates/front/product_page.tpl");
    }

    /**
    * Function to append Laybuy JS, CSS and Variables to Site Header
    * @param array $params
    * since 1.0.0
    */
    public function hookDisplayHeader() {

        if (!$this->isEnabledOrSupported()) {
            return;
        }

        $this->context->controller->addCSS($this->_path."css/laybuy.css", "all");
        $this->context->controller->addJS($this->_path."js/laybuy.js");
    }

    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addJquery();
        $this->context->controller->addJS($this->_path.'js/laybuy-admin.js', 'all');
    }


    /**
    * Function to display the Laybuy Cart Price Assets
    * @param array $params
    * @return TPL
    * since 1.0.0
    */
    public function hookDisplayExpressCheckout($params) {

        if (!$this->isEnabledOrSupported()) {
            return;
        }

        $total = $this->context->cart->getOrderTotal();

        $tplVars = [
            'logo_url' => 'https://integration-assets.laybuy.com/woocommerce_laybuy_icons/laybuy_logo_small.svg',
            'attrs' => [
                'width' => '80px',
                'link' => '',
                'style' => 'float:none; display:inline-block; vertical-align:middle; height:1.2em; top: -2px; position: relative;'
            ]
        ];

        if ($this->_isPriceWithinLimits($total)) {
            $tplVars['workflow'] = 'standard';
            $tplVars['amount'] = $this->_formatPrice($total / self::PAYMENTS_COUNT);
        } else {

            if ($total < $this->pay_over_time_limit_min) {
                return;
            } else {

                $tplVars['amount'] = $this->_formatPrice($this->getPayOverTimeLimitMax() / 5);
                $tplVars['pay_today'] = $this->_formatPrice($total - $this->getPayOverTimeLimitMax());

                $tplVars['workflow'] = 'pay_today';
            }
        }

        $this->context->smarty->assign($tplVars);
        return $this->context->smarty->fetch("module:laybuy/views/templates/front/cart_page.tpl");
    }

    private function _isPriceWithinLimits(float $price) {
        return $price >= $this->getPayOverTimeLimitMin() && $price <= $this->getPayOverTimeLimitMax();
    }

    /**
    * Function to check the Supported Currency
    * @param Cart $cart
    * @return bool
    * since 1.0.0
    */
    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);

        return in_array($currency_order->iso_code, $this->supported_currencies);
    }

    public function getPayOverTimeLimitMin()
    {
        return $this->pay_over_time_limit_min;
    }

    public function getPayOverTimeLimitMax()
    {
        if (isset($this->pay_over_time_limit_max[$this->context->currency->iso_code])) {
            return $this->pay_over_time_limit_max[$this->context->currency->iso_code];
        }
        return 0;
    }

    protected function _formatPrice($price)
    {
        return $this->context->currency->sign . number_format($price, 2, '.', ',') . ' ' . $this->context->currency->iso_code;
    }

    protected function isEnabledOrSupported()
    {
        if (false === $this->enabled) {
            return false;
        }

        if (!in_array($this->context->currency->iso_code, $this->supported_currencies)) {
            return false;
        }

        return true;
    }
}