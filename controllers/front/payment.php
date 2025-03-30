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

require_once __DIR__ . '/../../sdk/ProntoPaga.php';

use ProntoPaga\ProntoPaga;

class vc_prontopagapaymentModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        Tools::redirect(Context::getContext()->link->getPageLink('order', true, null, 'step=3'));
        
        if (Tools::getValue('action') == 'error') {
            return $this->displayError('An error occurred while trying to redirect the customer');
        }
    
        $cart = $this->context->cart;
        $currency = new Currency($cart->id_currency);
        $currency_iso = pSQL($currency->iso_code);
    
        $methods = Db::getInstance()->executeS(
            'SELECT * FROM ' . _DB_PREFIX_ . 'vc_prontopaga_methods 
             WHERE active = 1 
             AND currency = "' . $currency_iso . '"'
        );
    
        $this->context->smarty->assign([
            'cart_id' => $cart->id,
            'secure_key' => $this->context->customer->secure_key,
            'payment_methods' => $methods,
        ]);
    
        return $this->setTemplate('module:vc_prontopaga/views/templates/front/payment.tpl');
    }
}
