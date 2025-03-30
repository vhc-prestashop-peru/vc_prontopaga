<?php

require_once __DIR__ . '/../../sdk/ProntoPaga.php';
require_once __DIR__ . '/../../sdk/ProntoPagaLogger.php';

use ProntoPaga\ProntoPaga;
use ProntoPaga\ProntoPagaLogger;

class Vc_prontopagagenurlModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        $this->ajax = true;
    }

    public function postProcess()
    {
        header('Content-Type: application/json');

        $rawBody = file_get_contents('php://input');
        $payload = json_decode($rawBody, true);

        if (!$this->module->active) {
            ProntoPagaLogger::error('Module inactive');
            http_response_code(403);
            echo json_encode(['error' => 'Module inactive']);
            return;
        }

        if (!is_array($payload) || empty($payload['paymentMethod'])) {
            ProntoPagaLogger::error('Invalid or missing paymentMethod', ['raw' => $rawBody]);
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or missing paymentMethod']);
            return;
        }

        $paymentMethod = pSQL($payload['paymentMethod']);

        $liveMode  = (bool) Configuration::get('VC_PRONTOPAGA_LIVE_MODE');
        $token     = Configuration::get('VC_PRONTOPAGA_ACCOUNT_TOKEN');
        $secretKey = Configuration::get('VC_PRONTOPAGA_ACCOUNT_KEY');

        $sdk = new ProntoPaga($liveMode, $token, $secretKey);
        $cart = $this->context->cart;

        $paymentUrl = $sdk->createNewPayment($cart, $paymentMethod);

        if (!$paymentUrl || !is_string($paymentUrl)) {
            ProntoPagaLogger::error('Failed to generate payment URL', [
                'method' => $paymentMethod,
                'cart_id' => $cart->id
            ]);
            http_response_code(500);
            echo json_encode(['error' => 'Unable to create payment']);
            return;
        }

        ProntoPagaLogger::info('Payment URL generated', [
            'method' => $paymentMethod,
            'paymentUrl' => $paymentUrl
        ]);

        echo json_encode(['link_autorized' => $paymentUrl]);
    }
}