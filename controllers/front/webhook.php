<?php

require_once __DIR__ . '/../../sdk/ProntoPagoLogger.php';
require_once __DIR__ . '/../../sdk/ProntoPagoConfig.php';
require_once __DIR__ . '/../../sdk/ProntoPagoValidator.php';

use ProntoPago\ProntoPagoLogger;
use ProntoPago\ProntoPagoConfig;
use ProntoPago\ProntoPagoValidator;

class Vc_prontopagaWebhookModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $rawBody = file_get_contents('php://input');
        $payload = json_decode($rawBody, true);
        $psref = Tools::getValue(ProntoPagoConfig::SECURE_REF_PARAM);

        ProntoPagoLogger::info('Webhook received', [
            'ip' => Tools::getRemoteAddr(),
            'payload' => $payload
        ]);

        if (!$this->module->active) {
            ProntoPagoLogger::error('Module inactive');
            http_response_code(403);
            die('Module inactive');
        }

        if (!ProntoPagoValidator::isAuthorizedIP()) {
            http_response_code(403);
            die('Unauthorized IP');
        }

        if (!is_array($payload)) {
            ProntoPagoLogger::error('Invalid JSON payload', ['raw' => $rawBody]);
            http_response_code(400);
            die('Invalid JSON');
        }

        if (!ProntoPagoValidator::hasRequiredFields($payload, ['order', 'status', 'amount', 'currency', 'sign'])) {
            http_response_code(400);
            die('Missing required fields');
        }

        if (!ProntoPagoValidator::validatePSref($psref)) {
            http_response_code(400);
            die('Invalid secure_psref');
        }
        
        $decoded = base64_decode($psref);
        list($_, $cartId, $_) = explode('|', $decoded);

        $cart = new Cart((int)$cartId);
        $customer = new Customer($cart->id_customer);
        $currency = new Currency($cart->id_currency);
        $expectedAmount = (float)$cart->getOrderTotal(true, Cart::BOTH);

        $status = $payload['status'];
        $amount = (float)$payload['amount'];
        $currencyCode = $payload['currency'];

        if (!ProntoPagoValidator::isMatchingPayment($expectedAmount, $amount, $currency->iso_code, $currencyCode)) {
            http_response_code(400);
            die('Amount or currency mismatch');
        }

        Context::getContext()->cart = $cart;
        Context::getContext()->customer = $customer;
        Context::getContext()->currency = $currency;
        Context::getContext()->language = new Language((int)$customer->id_lang);

        $secure_key = $customer->secure_key;
        $module_name = $this->module->displayName;

        switch ($status) {
            case 'success':
                $payment_status = Configuration::get('PS_OS_PAYMENT');
                $message = 'Payment successful via ProntoPaga';
                ProntoPagoLogger::info($message);
                break;
        
            case 'rejected':
                $payment_status = Configuration::get('PS_OS_ERROR');
                $message = 'Payment rejected via ProntoPaga';
                ProntoPagoLogger::info($message);
                break;
        
            default:
                ProntoPagoLogger::info('Ignored webhook due to unsupported status', ['status' => $status]);
                http_response_code(200);
                die('Status ignored');
        }

        if (!Order::getOrderByCartId((int)$cart->id)) {
            $this->module->validateOrder(
                $cart->id,
                $payment_status,
                $amount,
                $module_name,
                $message,
                [],
                (int)$currency->id,
                false,
                $secure_key
            );
            ProntoPagoLogger::info('Order validated', ['cart_id' => $cart->id, 'status' => $status]);
        } else {
            ProntoPagoLogger::info('Order already validated', ['cart_id' => $cart->id]);
        }

        http_response_code(200);
        die('OK');
    }
}
