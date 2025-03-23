<?php
/**
 * ProntoPagoHelper
 *
 * Esta clase sirve como capa de alto nivel para interactuar con los servicios de ProntoPago
 * a través de ProntoPagoApiManager (que realiza las peticiones HTTP con cURL).
 * Aquí puedes centralizar la lógica de negocio (crear pagos, obtener métodos de pago, etc.)
 * y la generación de firmas (signature).
 *
 * Requisitos:
 *  - PHP >= 7.2.5
 *  - ProntoPagoApiManager (sin Composer) ya implementado en la misma carpeta o ruta adecuada.
 *
 */

namespace ProntoPago;

use Cart;
use Customer;
use Address;
use Currency;
use Country;
use Order;
use Context;
use Db;
use Configuration;

require_once __DIR__ . '/ProntoPagoConfig.php';
require_once __DIR__ . '/ProntoPagoLogger.php';
require_once __DIR__ . '/ProntoPagoApiManager.php';

class ProntoPagoHelper
{
    private $liveMode;
    private $token;
    private $secretKey;

    public function __construct($liveMode, $token, $secretKey)
    {
        $this->liveMode  = $liveMode;
        $this->token     = $token;
        $this->secretKey = $secretKey;
    }

    private function getApiManager()
    {
        return new ProntoPagoApiManager($this->liveMode, $this->token);
    }

    public function getBalance()
    {
        $apiManager = $this->getApiManager();
        return $apiManager->request('GET', 'api/balance');
    }

    public function getPaymentMethods()
    {
        $apiManager = $this->getApiManager();
        return $apiManager->request('GET', 'api/payment/methods');
    }

    public function syncPaymentMethodsToDB()
    {
        $methods = $this->getPaymentMethods();

        if (!$methods || !is_array($methods)) {
            ProntoPagoLogger::error('Failed to fetch payment methods', ['response' => $methods]);
            return false;
        }

        Db::getInstance()->execute('TRUNCATE TABLE `' . _DB_PREFIX_ . 'vc_prontopaga_methods`');

        foreach ($methods as $method) {
            Db::getInstance()->insert('vc_prontopaga_methods', [
                'method_id' => (int) $method['id'],
                'name' => pSQL($method['name']),
                'method' => pSQL($method['method']),
                'currency' => pSQL($method['currency']),
                'logo' => pSQL($method['logo']),
                'active' => 1,
            ]);
        }

        ProntoPagoLogger::info('Payment methods synchronized', ['count' => count($methods)]);
        return true;
    }

    public function createNewPayment(Cart $cart, string $paymentMethod)
    {
        if (empty($paymentMethod)) {
            ProntoPagoLogger::error('Empty payment method provided');
            return false;
        }

        $context = Context::getContext();
        $customer = new Customer($cart->id_customer);
        $address = new Address($cart->id_address_invoice);
        $currency = new Currency($cart->id_currency);
        $country = new Country($address->id_country);

        $order_reference = Order::generateReference();
        $amount = (float) $cart->getOrderTotal();

        $link = $context->link;
        $token = Configuration::get('VC_PRONTOPAGA_ACCOUNT_TOKEN');
        $raw = $order_reference . '|' . (int) $cart->id . '|' . $token;
        $psref = base64_encode($raw);

        $confirmation_url = $link->getModuleLink('vc_prontopaga', 'webhook', [ProntoPagoConfig::SECURE_REF_PARAM => $psref], true);
        $rejected_url = $link->getModuleLink('vc_prontopaga', 'return', [ProntoPagoConfig::SECURE_REF_PARAM => $psref], true);
        // $rejected_url = $link->getPageLink('order', true, null, 'step=3');
        $final_url = $link->getModuleLink('vc_prontopaga', 'return', [ProntoPagoConfig::SECURE_REF_PARAM => $psref], true);

        $data = [
            'currency'        => $currency->iso_code,
            'country'         => $country->iso_code,
            'amount'          => number_format($amount, 2, '.', ''),
            'clientName'      => $customer->firstname . ' ' . $customer->lastname,
            'clientEmail'     => $customer->email,
            'clientPhone'     => $address->phone_mobile ?: $address->phone,
            'clientDocument'  => empty($address->dni) ? ProntoPagoConfig::CLIENT_DOCUMENT_DEFAULT : $address->dni,
            'paymentMethod'   => $paymentMethod,
            'urlConfirmation' => $confirmation_url,
            'urlFinal'        => $final_url,
            'urlRejected'     => $rejected_url,
            'order'           => $cart->id . '-' . $order_reference,
        ];

        $data['sign'] = $this->generateSignature($data);

        $response = $this->getApiManager()->request('POST', 'api/payment/new', [
            'json' => $data,
        ]);

        if (!$response || !is_array($response) || !isset($response['urlPay'])) {
            ProntoPagoLogger::error('createNewPayment failed', ['data' => $data, 'response' => $response]);
            return false;
        }

        // ProntoPagoLogger::info('createNewPayment success', ['response' => $response]);
        return $response['urlPay'];
    }

    public function generateSignature(array $data, $concatString = '')
    {
        if (isset($data['sign'])) {
            unset($data['sign']);
        }

        $keys = array_keys($data);
        sort($keys);

        foreach ($keys as $key) {
            $concatString .= $key . $data[$key];
        }

        return hash_hmac(ProntoPagoConfig::SIGN_ALGORITHM, $concatString, $this->secretKey);
    }
}