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

require_once __DIR__ . '/../../sdk/ProntoPagoLogger.php';
require_once __DIR__ . '/../../sdk/ProntoPagoConfig.php';
require_once __DIR__ . '/../../sdk/ProntoPagoValidator.php';

use ProntoPago\ProntoPagoLogger;
use ProntoPago\ProntoPagoConfig;
use ProntoPago\ProntoPagoValidator;

class Vc_prontopagaReturnModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $securePsref = Tools::getValue(ProntoPagoConfig::SECURE_REF_PARAM);

        if (!ProntoPagoValidator::validatePSref($securePsref)) {
            ProntoPagoLogger::error('Return: Invalid or expired secure_psref', ['secure_psref' => $securePsref]);
            $this->errors[] = $this->module->l('El enlace de retorno no es válido o ha expirado.');
            return $this->setTemplate('module:vc_prontopaga/views/templates/front/error.tpl');
        }

        list($_, $cartId, $_) = explode('|', base64_decode($securePsref));
        $cart = new Cart((int) $cartId);

        if (!Validate::isLoadedObject($cart)) {
            ProntoPagoLogger::error('Return: Cart not found after decoding', ['cart_id' => $cartId]);
            $this->errors[] = $this->module->l('El carrito ya no está disponible.');
            return $this->setTemplate('module:vc_prontopaga/views/templates/front/error.tpl');
        }

        $customer = new Customer((int) $cart->id_customer);

        // Si no hay pedido relacionado, redirigir al paso 3 del checkout
        $orderId = Order::getOrderByCartId((int) $cart->id);
        if (!$orderId) {
            ProntoPagoLogger::error('Return: No order found, redirecting to step=3', ['cart_id' => $cart->id]);
            $link = Context::getContext()->link;
            Tools::redirect($link->getPageLink('order', true, null, 'step=3'));
        }

        // Validar clave segura del cliente
        if ($this->context->customer->secure_key !== $customer->secure_key) {
            ProntoPagoLogger::error('Return: Secure key mismatch', [
                'cart_id' => $cart->id,
                'expected_key' => $customer->secure_key,
                'provided_key' => $this->context->customer->secure_key
            ]);
            $this->errors[] = $this->module->l('No tienes permisos para ver esta confirmación de pedido.');
            return $this->setTemplate('module:vc_prontopaga/views/templates/front/error.tpl');
        }

        ProntoPagoLogger::info('Return: Redirecting to order-confirmation', [
            'cart_id' => $cart->id,
            'order_id' => $orderId,
            'customer_id' => $customer->id
        ]);

        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int)$cart->id .
            '&id_module=' . (int)$this->module->id .
            '&id_order=' . (int)$orderId .
            '&key=' . $customer->secure_key);
    }
}