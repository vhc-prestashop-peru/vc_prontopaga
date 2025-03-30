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
require_once __DIR__ . '/../../sdk/ProntoPagaLogger.php';
require_once __DIR__ . '/../../sdk/ProntoPagaConfig.php';
require_once __DIR__ . '/../../sdk/ProntoPagaValidator.php';

use ProntoPaga\ProntoPaga;
use ProntoPaga\ProntoPagaLogger;
use ProntoPaga\ProntoPagaConfig;
use ProntoPaga\ProntoPagaValidator;

class Vc_prontopagaReturnModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $securePsref = Tools::getValue(ProntoPagaConfig::SECURE_REF_PARAM);

        if (!ProntoPagaValidator::validatePSref($securePsref)) {
            ProntoPagaLogger::error('Return: Invalid or expired secure_psref', ['secure_psref' => $securePsref]);
            $this->context->cookie->__set('prontopaga_error', 'El enlace de retorno no es válido o ha expirado.');
            Tools::redirect(Context::getContext()->link->getPageLink('order', true, null, 'step=3'));
        }

        $status = $this->verifyTransactionAndStatus($securePsref);

        if ($status !== 'paid') {
            ProntoPagaLogger::info('Return: Estado no exitoso, redirigiendo a paso 3', ['status' => $status]);
            $this->context->cookie->__set('prontopaga_error', 'El pago fue rechazado o está pendiente. Intenta con otro método.');
            Tools::redirect(Context::getContext()->link->getPageLink('order', true, null, 'step=3'));
        }

        list(, $cartId,) = explode('|', base64_decode($securePsref));
        $cart = new Cart((int) $cartId);
        $customer = new Customer((int) $cart->id_customer);
        $orderId = Order::getOrderByCartId((int) $cart->id);

        ProntoPagaLogger::info('Return: Redirecting to order-confirmation', [
            'cart_id' => $cart->id,
            'order_id' => $orderId,
            'customer_id' => $customer->id
        ]);

        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int)$cart->id .
            '&id_module=' . (int)$this->module->id .
            '&id_order=' . (int)$orderId .
            '&key=' . $customer->secure_key);
    }

    /**
     * Verifica una transacción ProntoPaga en base al PSRef y compara con la BD.
     * Devuelve el estado de la transacción si todo es coherente.
     *
     * @param string $securePsref
     * @return string|null Estado de la transacción ('paid', 'rejected', etc.) o null si falla la validación
     */
    private function verifyTransactionAndStatus(string $securePsref): ?string
    {
        $decoded = base64_decode($securePsref);
        if (!$decoded || substr_count($decoded, '|') !== 2) {
            ProntoPagaLogger::error('verifyTransactionAndStatus: PSRef malformado', [
                'psref' => $securePsref,
                'decoded' => $decoded,
            ]);
            return null;
        }

        list($orderReference, $cartId, $token) = explode('|', $decoded);
        $orderRef = (int)$cartId . '-' . pSQL($orderReference);

        $transaction = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'vc_prontopaga_transactions`
             WHERE `id_cart` = ' . (int)$cartId . '
             AND `order_reference` = "' . pSQL($orderRef) . '"'
        );

        if (!$transaction || empty($transaction['uid'])) {
            ProntoPagaLogger::error('verifyTransactionAndStatus: Transacción no encontrada en la BD', [
                'cart_id' => $cartId,
                'order_ref' => $orderRef,
            ]);
            return null;
        }

        $liveMode = (bool) Configuration::get('VC_PRONTOPAGA_LIVE_MODE');
        $token = Configuration::get('VC_PRONTOPAGA_ACCOUNT_TOKEN');
        $secretKey = Configuration::get('VC_PRONTOPAGA_ACCOUNT_KEY');
        $sdk = new ProntoPaga($liveMode, $token, $secretKey);

        $apiData = $sdk->getPaymentData($transaction['uid']);

        if (!$apiData || !is_array($apiData)) {
            ProntoPagaLogger::error('verifyTransactionAndStatus: No se pudo obtener data del servicio', [
                'uid' => $transaction['uid'],
                'response' => $apiData,
            ]);
            return null;
        }

        $fieldsToCheck = ['amount', 'currency', 'country', 'order'];
        foreach ($fieldsToCheck as $field) {
            if (!isset($apiData[$field]) || (string)$apiData[$field] !== (string)$transaction[$field]) {
                ProntoPagaLogger::error("verifyTransactionAndStatus: Mismatch en campo [$field]", [
                    'uid' => $transaction['uid'],
                    'expected' => $transaction[$field] ?? null,
                    'received' => $apiData[$field] ?? null,
                ]);
                return null;
            }
        }

        ProntoPagaLogger::info('verifyTransactionAndStatus: Validación exitosa', [
            'uid' => $transaction['uid'],
            'status' => $apiData['status'],
        ]);

        return $apiData['status'];
    }
}