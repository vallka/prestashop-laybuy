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
require_once( _PS_MODULE_DIR_ . 'laybuy/classes/LaybuyCapture.php' );

class LaybuyReturnModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $params = $_REQUEST;

        $this->context->smarty->assign([
            "params" => $params,
        ]);

        $validate_error = $this->_validateRedirectParams($params);

        if( count($validate_error) ) {
            $error["message"] = $this->module->l("Invalid Response: Missing Laybuy params " . implode($validate_error, ", ") , "validation");
            $this->_checkoutErrorRedirect($error);
        }

        $laybuyConfig    = new LaybuyConfig();
        $laybuyApiConfig = new LaybuyApiConfig($laybuyConfig);
        $laybuyApi       = new LaybuyApi($laybuyApiConfig);
        $laybuyCapture   = new LaybuyCapture($laybuyApi);

        switch ($params['status']) {
            case LaybuyApi::PAYMENT_STATUS_SUCCESS:
                $apiResponse = $laybuyCapture->createCapturePayment($params);

                if ($apiResponse->result !== LaybuyApi::PAYMENT_STATUS_SUCCESS) {

                    if (Laybuy::$debug) {
                        PrestaShopLogger::addLog("Failed confirming the payment", 1, null, 'Cart', (int) $this->context->cart->id, true);
                        PrestaShopLogger::addLog("Response data: " . print_r($apiResponse, true), 1, null, 'Cart', (int) $this->context->cart->id, true);
                    }

                    $this->_checkoutErrorRedirect(['message' => 'Failed confirming the payment']);
                    return;
                }

                $laybuyCapture->onCaptureSuccess($apiResponse->orderId, $params['token']);
                $customer = new Customer($this->context->cart->id_customer);

                Tools::redirect('index.php?controller=order-confirmation&id_cart='.$this->context->cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);

                break;
            case LaybuyApi::PAYMENT_STATUS_DECLINED:
                $this->_checkoutErrorRedirect(['message' => 'Your payment was declined.']);
                break;
            case LaybuyApi::PAYMENT_STATUS_ERROR:
                $this->_checkoutErrorRedirect(['message' => 'Your payment could not be processed. Please try again.']);
                break;
            default:
                Tools::redirect($this->context->link->getPageLink( 'order', null, null, 'step=4'));
                break;
        }
    }

    private function _validateRedirectParams($params) {
        $error = [];

        if( empty($params["token"]) ) {
            $error[] = "token";
        }

        if( empty($params["status"]) ) {
            $error[] = "status";
        }

        return $error;
    }

    private function _checkoutErrorRedirect($results) {

        if( !empty($results["message"]) ) {
            $this->errors[] = $this->l( $results["message"] );
        }
        $this->redirectWithNotifications('index.php?controller=order&step=1');
    }
}