<?php
/*
* 2007-2015 PrestaShop
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
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * @since 1.5.0
 */
require_once( _PS_MODULE_DIR_ . 'laybuy/classes/LaybuyApi.php');
require_once( _PS_MODULE_DIR_ . 'laybuy/classes/LaybuyApiConfig.php');
require_once( _PS_MODULE_DIR_ . 'laybuy/classes/LaybuyConfig.php');
require_once( _PS_MODULE_DIR_ . 'laybuy/classes/LaybuyCheckout.php');

class LaybuyValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $cart = $this->context->cart;

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'laybuy') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            $this->_checkoutErrorRedirect($this->module->l('This payment method is not available. Please contact website administrator.', 'validation'));
        }

        $params = $_REQUEST;

        $this->context->smarty->assign(['params' => $params,]);

        $laybuyConfig    = new LaybuyConfig();
        $laybuyApiConfig = new LaybuyApiConfig($laybuyConfig);
        $laybuyApi       = new LaybuyApi($laybuyApiConfig);
        $laybuyCheckout  = new LaybuyCheckout($cart, $laybuyApi);

        $laybuyCheckout->setRedirectConfirmUrl( $this->context->link->getModuleLink( $this->module->name, 'return' ) );

        try {
            $laybuyCheckout->createOrderToken();
        } catch ( Exception $e ) {

            $log_message = $e->getMessage();
            $log_message = "Laybuy Token Generation Failure: " . $log_message . "; Payload: " . preg_replace( "/\r|\n/", "", print_r($laybuyCheckout->getPayload(), true) );

            if (Laybuy::$debug) {
                PrestaShopLogger::addLog($log_message, 3, NULL, "Laybuy", 1);
            }

            if ($e->getCode() == 101) {
                $this->_checkoutErrorRedirect($e->getMessage());
            } else {
                $this->_checkoutErrorRedirect("Laybuy Token Generation Failure. Please contact Website Administrator");
            }
        }

        $this->setTemplate('module:laybuy/views/templates/front/payment_return.tpl');
    }

    private function _checkoutErrorRedirect($message) {

        if( !empty($message) ) {
            $this->errors[] = $this->l( $message );
        }
        $this->redirectWithNotifications('index.php?controller=order&step=1');
    }
}