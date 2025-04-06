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
    
    /**
     * Fetches available currencies from ProntoPaga and returns only those matching the store currencies.
     *
     * @param array $prestashopCurrencies List of PrestaShop currencies (array of arrays with 'iso_code' and 'id_currency' keys).
     *
     * @return array List of matched currencies, each with structure:
     *               [
     *                   'id_option' => (int) currency id,
     *                   'name'      => string 'Currency name (ISO)',
     *                   'val'       => (int) currency id
     *               ]
     */
    public function getMatchedAvailableCurrencies(array $prestashopCurrencies)
    {
        $apiManager = $this->getApiManager();
        $response = $apiManager->request('GET', 'api/balance');
    
        if (empty($response) || !isset($response['available']) || !is_array($response['available'])) {
            return [];
        }
    
        $availableCurrencyCodes = array_map('strtoupper', array_keys($response['available']));
    
        $matchedCurrencies = [];
    
        foreach ($prestashopCurrencies as $currency) {
            if (isset($currency['iso_code'], $currency['id_currency'], $currency['name'])) {
                if (in_array(strtoupper($currency['iso_code']), $availableCurrencyCodes)) {
                    $matchedCurrencies[] = [
                        'id_option' => (int) $currency['id_currency'],
                        'name'      => $currency['name'] . ' (' . $currency['iso_code'] . ')',
                        'val'       => (int) $currency['id_currency'],
                    ];
                }
            }
        }
    
        return $matchedCurrencies;
    }
    
    /**
     * Synchronizes ProntoPaga payment methods with the database.
     *
     * - Fetches the available payment methods from the ProntoPaga API.
     * - Truncates the `vc_prontopaga_methods` table.
     * - Inserts the new payment methods into the database.
     * - Logs the result of the operation (success or failure).
     *
     * @return bool True on success, False on failure.
     */
    public function syncPaymentMethodsToDB()
    {
        $methods = $this->apiGetPaymentMethods();

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

    /**
     * Creates a new payment request in ProntoPaga for a given cart and payment method.
     *
     * - Builds the payment payload with customer, cart, and order details.
     * - Generates secure URLs for confirmation, rejection, and final return.
     * - Signs the payment request for security.
     * - Sends the payment creation request to ProntoPaga API.
     * - Logs success or failure and saves the payment response in the database.
     *
     * @param Cart $cart The current customer's cart.
     * @param string $paymentMethod The payment method identifier (e.g., 'pe_card_payment').
     *
     * @return string|false The URL for the payment page if successful, false otherwise.
     */
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

    private function getApiManager()
    {
        return new ProntoPagaApiManager($this->liveMode, $this->token);
    }
    
    private function apiGetPaymentMethods()
    {
        $apiManager = $this->getApiManager();
        return $apiManager->request('GET', 'api/payment/methods');
    }
    
    public function apiGetPaymentData(string $uid)
    {
        $apiManager = $this->getApiManager();
        $response = $apiManager->request('GET', 'api/payment/data/' . $uid);
    
        if (!$response || !is_array($response)) {
            ProntoPagaLogger::error('apiGetPaymentData: Respuesta inválida o vacía', [
                'uid' => $uid,
                'response' => $response,
            ]);
            return false;
        }
    
        if (isset($response['error']) || isset($response['message'])) {
            ProntoPagaLogger::error('apiGetPaymentData: Error recibido desde el servicio', [
                'uid' => $uid,
                'response' => $response,
            ]);
            return false;
        }
    
        ProntoPagaLogger::info('apiGetPaymentData: Respuesta exitosa', [
            'uid' => $uid,
            'response' => $response,
        ]);
    
        return $response;
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

        $values = [
            'id_cart'         => (int) $cart->id,
            'id_customer'     => (int) $customer->id,
            'status'          => 'new',
            'payment_method'  => pSQL($paymentMethod),
            'country'         => pSQL($country->iso_code),
            'currency'        => pSQL($currency->iso_code),
            'amount'          => (float) $data['amount'],
            'order' => pSQL($data['order']),
            'url_pay'         => pSQL($response['urlPay']),
            'uid'             => pSQL($response['uid'] ?? ''),
            'reference'       => pSQL($response['reference'] ?? ''),
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
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