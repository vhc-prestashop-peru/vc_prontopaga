<?php

require_once __DIR__ . '/../../sdk/ProntoPagaLogger.php';
require_once __DIR__ . '/../../sdk/ProntoPagaConfig.php';
require_once __DIR__ . '/../../sdk/ProntoPagaValidator.php';

use ProntoPaga\ProntoPagaLogger;
use ProntoPaga\ProntoPagaConfig;
use ProntoPaga\ProntoPagaValidator;

class Vc_prontopagaWebhookModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $rawBody = file_get_contents('php://input');
        $payload = json_decode($rawBody, true);
        $psref = Tools::getValue(ProntoPagaConfig::SECURE_REF_PARAM);

        ProntoPagaLogger::info('Webhook received', [
            'ip' => Tools::getRemoteAddr(),
            'payload' => $payload
        ]);

        if (!$this->module->active) {
            ProntoPagaLogger::error('Module inactive');
            http_response_code(403);
            die('Module inactive');
        }

        if (!ProntoPagaValidator::isAuthorizedIP()) {
            http_response_code(403);
            die('Unauthorized IP');
        }

        if (!is_array($payload)) {
            ProntoPagaLogger::error('Invalid JSON payload', ['raw' => $rawBody]);
            http_response_code(400);
            die('Invalid JSON');
        }

        if (!ProntoPagaValidator::hasRequiredFields($payload, ['order', 'status', 'amount', 'currency', 'sign'])) {
            http_response_code(400);
            die('Missing required fields');
        }

        if (!ProntoPagaValidator::validatePSref($psref)) {
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

        if (!ProntoPagaValidator::isMatchingPayment($expectedAmount, $amount, $currency->iso_code, $currencyCode)) {
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
                ProntoPagaLogger::info($message);
                break;
        
            case 'rejected':
                $payment_status = Configuration::get('PS_OS_ERROR');
                $message = 'Payment rejected via ProntoPaga';
                ProntoPagaLogger::info($message);
                break;
        
            default:
                ProntoPagaLogger::info('Ignored webhook due to unsupported status', ['status' => $status]);
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
            ProntoPagaLogger::info('Order validated', ['cart_id' => $cart->id, 'status' => $status]);
        } else {
            ProntoPagaLogger::info('Order already validated', ['cart_id' => $cart->id]);
        }

        http_response_code(200);
        die('OK');
    }
}
