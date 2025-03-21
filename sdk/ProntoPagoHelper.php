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

require_once __DIR__ . '/ProntoPagoApiManager.php';

use Db;

class ProntoPagoHelper
{
    private $liveMode;
    private $token;
    private $secretKey;

    /**
     * Constructor.
     *
     * @param bool   $liveMode  Modo producción (true) o sandbox (false).
     * @param string $token     Token de autenticación (Bearer).
     * @param string $secretKey Clave secreta para HMAC.
     */
    public function __construct($liveMode, $token, $secretKey)
    {
        $this->liveMode  = $liveMode;
        $this->token     = $token;
        $this->secretKey = $secretKey;
    }

    /**
     * Crea una nueva instancia de ProntoPagoApiManager cada vez que se llama.
     *
     * @return ProntoPagoApiManager
     */
    private function getApiManager()
    {
        return new ProntoPagoApiManager($this->liveMode, $this->token);
    }

    /**
     * Llama a GET /api/balance para obtener el balance de la cuenta.
     *
     * @return array|false Array asociativo o false si ocurre un error.
     */
    public function getBalance()
    {
        $apiManager = $this->getApiManager();
        return $apiManager->request('GET', 'api/balance');
    }

    /**
     * Llama a GET /api/payment/methods para obtener métodos de pago disponibles.
     *
     * @return array|false Array asociativo o false si ocurre un error.
     */
    public function getPaymentMethods()
    {
        $apiManager = $this->getApiManager();
        return $apiManager->request('GET', 'api/payment/methods');
    }
    
    /**
     * Obtiene los métodos de pago desde la API y los guarda en la BD.
     *
     * @return bool true si la actualización fue exitosa, false en caso de error.
     */
    public function syncPaymentMethodsToDB()
    {
        $methods = $this->getPaymentMethods();

        if (!$methods || !is_array($methods)) {
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

        return true;
    }

    /**
     * Llama a POST /api/payment/new para crear un nuevo pago.
     *
     * @param array $paymentData Datos necesarios (currency, country, amount, etc.).
     * @return array|false Respuesta de la API o false si ocurre un error.
     */
    public function createNewPayment(array $paymentData)
    {
        $apiManager = $this->getApiManager();
        return $apiManager->request('POST', 'api/payment/new', [
            'json' => $paymentData,
        ]);
    }

    /**
     * Genera una firma HMAC-SHA256 a partir de un array de datos.
     * Ordena las claves, concatena clave+valor y firma con la secretKey.
     *
     * @param array  $data         Datos a firmar (clave => valor).
     * @param string $concatString Cadena inicial para concatenación (opcional).
     * @return string Firma generada (hexadecimal).
     */
    public function generateSignature(array $data, $concatString = '')
    {
        $keys = array_keys($data);
        sort($keys);

        foreach ($keys as $key) {
            $concatString .= $key . $data[$key];
        }

        return hash_hmac('sha256', $concatString, $this->secretKey);
    }
}