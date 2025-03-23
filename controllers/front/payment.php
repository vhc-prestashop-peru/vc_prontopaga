<?php
/**
* 2007-2025 PrestaShop
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
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2025 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class vc_prontopagapaymentModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if (Tools::getValue('action') == 'error') {
            return $this->displayError('An error occurred while trying to redirect the customer');
        }
    
        $currency = new Currency($this->context->cart->id_currency);
        $currency_iso = pSQL($currency->iso_code);
    
        $methods = Db::getInstance()->executeS(
            'SELECT * FROM ' . _DB_PREFIX_ . 'vc_prontopaga_methods 
             WHERE active = 1 
             AND currency = "' . $currency_iso . '"'
        );
    
        $enrichedMethods = [];
        $cart = $this->context->cart;
    
        $helper = new \ProntoPago\ProntoPagoHelper(
            Configuration::get('VC_PRONTOPAGA_LIVE_MODE'),
            Configuration::get('VC_PRONTOPAGA_ACCOUNT_TOKEN'),
            Configuration::get('VC_PRONTOPAGA_ACCOUNT_KEY')
        );
    
        foreach ($methods as $method) {
            $paymentUrl = $helper->createNewPayment($cart, $method['method']);
            if ($paymentUrl) {
                $method['paymentUrl'] = $paymentUrl;
                $enrichedMethods[] = $method;
            }
        }
    
        $this->context->smarty->assign([
            'cart_id' => $cart->id,
            'secure_key' => $this->context->customer->secure_key,
            'payment_methods' => $enrichedMethods,
        ]);
    
        return $this->setTemplate('module:vc_prontopaga/views/templates/front/payment.tpl');
    }
}
