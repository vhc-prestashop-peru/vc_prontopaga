<?php
/**
 * ProntoPaga
 *
 * Esta clase sirve como capa de alto nivel para interactuar con los servicios de ProntoPaga
 * a través de ProntoPagaApiManager (que realiza las peticiones HTTP con cURL).
 * Aquí puedes centralizar la lógica de negocio (crear pagos, obtener métodos de pago, etc.)
 * y la generación de firmas (signature).
 *
 * Requisitos:
 *  - PHP >= 7.2.5
 *
 */

namespace ProntoPaga;

use Cart;
use Customer;
use Address;
use Currency;
use Country;
use Order;
use Context;
use Db;
use Configuration;

require_once __DIR__ . '/ProntoPagaConfig.php';
require_once __DIR__ . '/ProntoPagaLogger.php';
require_once __DIR__ . '/ProntoPagaApiManager.php';

class ProntoPaga
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
        return new ProntoPagaApiManager($this->liveMode, $this->token);
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
    
    public function getPaymentData(string $uid)
    {
        $apiManager = $this->getApiManager();
        return $apiManager->request('GET', 'api/payment/data/'.$uid);
    }

    public function syncPaymentMethodsToDB()
    {
        $methods = $this->getPaymentMethods();

        if (!$methods || !is_array($methods)) {
            ProntoPagaLogger::error('Failed to fetch payment methods', ['response' => $methods]);
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

        ProntoPagaLogger::info('Payment methods synchronized', ['count' => count($methods)]);
        return true;
    }

    public function createNewPayment(Cart $cart, string $paymentMethod)
    {
        if (empty($paymentMethod)) {
            ProntoPagaLogger::error('Empty payment method provided');
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

        $confirmation_url = $link->getModuleLink('vc_prontopaga', 'webhook', [ProntoPagaConfig::SECURE_REF_PARAM => $psref], true);
        $rejected_url = $link->getModuleLink('vc_prontopaga', 'return', [ProntoPagaConfig::SECURE_REF_PARAM => $psref], true);
        $final_url = $link->getModuleLink('vc_prontopaga', 'return', [ProntoPagaConfig::SECURE_REF_PARAM => $psref], true);

        $data = [
            'currency'        => $currency->iso_code,
            'country'         => $country->iso_code,
            'amount'          => number_format($amount, 2, '.', ''),
            'clientName'      => $customer->firstname . ' ' . $customer->lastname,
            'clientEmail'     => $customer->email,
            'clientPhone'     => $address->phone_mobile ?: $address->phone,
            'clientDocument'  => empty($address->dni) ? ProntoPagaConfig::CLIENT_DOCUMENT_DEFAULT : $address->dni,
            'paymentMethod'   => $paymentMethod,
            'urlConfirmation' => $confirmation_url,
            'urlFinal'        => $final_url,
            'urlRejected'     => $rejected_url,
            'order'           => (int)$cart->id . '-' . $order_reference,
        ];

        $data['sign'] = $this->generateSignature($data);

        $response = $this->getApiManager()->request('POST', 'api/payment/new', [
            'json' => $data,
        ]);

        if (!$response || !is_array($response) || !isset($response['urlPay'])) {
            ProntoPagaLogger::error('createNewPayment failed', ['data' => $data, 'response' => $response]);
            return false;
        }

        ProntoPagaLogger::info('createNewPayment success', ['response' => $response]);
        $this->savePaymentResponseToDb($data, $cart, $paymentMethod, $response);
        return $response['urlPay'];
    }

    private function generateSignature(array $data, $concatString = '')
    {
        if (isset($data['sign'])) {
            unset($data['sign']);
        }

        $keys = array_keys($data);
        sort($keys);

        foreach ($keys as $key) {
            $concatString .= $key . $data[$key];
        }

        return hash_hmac(ProntoPagaConfig::SIGN_ALGORITHM, $concatString, $this->secretKey);
    }
    
    private function savePaymentResponseToDb(array $data, Cart $cart, string $paymentMethod, array $response)
    {
        $customer = new Customer($cart->id_customer);
        $address = new Address($cart->id_address_invoice);
        $currency = new Currency($cart->id_currency);
        $country = new Country($address->id_country);
        $orderRef = $cart->id . '-' . Order::generateReference();
    
        $values = [
            'id_cart'         => (int) $cart->id,
            'id_customer'     => (int) $customer->id,
            'payment_method'  => pSQL($paymentMethod),
            'country'         => pSQL($country->iso_code),
            'currency'        => pSQL($currency->iso_code),
            'amount'          => (float) $data['amount'],
            'order_reference' => pSQL($orderRef),
            'url_pay'         => pSQL($response['urlPay']),
            'uid'             => pSQL($response['uid'] ?? ''),
            'reference'       => pSQL($response['reference'] ?? ''),
            'created_at'      => date('Y-m-d H:i:s'),
        ];
    
        $success = Db::getInstance()->insert('vc_prontopaga_transactions', $values);
    
        if (!$success) {
            ProntoPagaLogger::error('Failed to save payment to DB', ['values' => $values]);
        } else {
            ProntoPagaLogger::info('Payment saved to DB', ['reference' => $response['reference'] ?? null]);
        }
    
        return $success;
    }
}