<?php
/**
 * ProntoPagaApiManager
 *
 * Esta clase gestiona la comunicación con la API de ProntoPaga utilizando cURL de forma nativa,
 * sin depender de librerías instaladas con Composer. En caso de error, devuelve false.
 *
 * Requisitos:
 *   - PHP >= 7.2.5
 *
 */

namespace ProntoPaga;

use ProntoPaga\ProntoPagaConfig;

class ProntoPagaApiManager
{
    private $baseUri;
    private $token;

    /**
     * Constructor principal.
     *
     * @param bool   $liveMode Indica si está en modo producción (true) o sandbox (false).
     * @param string $token    Token de autenticación (Bearer).
     */
    public function __construct($liveMode, $token)
    {
        $this->baseUri = $liveMode ? ProntoPagaConfig::API_URL_PRODUCTION : ProntoPagaConfig::API_URL_SANDBOX;
        $this->token   = $token;
    }

    /**
     * Método genérico para hacer peticiones HTTP a la API de ProntoPaga usando cURL.
     *
     * @param string $method   Verbo HTTP (GET, POST, PUT, etc.).
     * @param string $endpoint Ruta de la API (por ejemplo, 'api/balance').
     * @param array  $options  Opciones adicionales (headers, json, query, etc.).
     *
     * @return array|false Respuesta decodificada en caso de éxito, o false si ocurre un error.
     */
    public function request($method, $endpoint, array $options = [])
    {
        $url = rtrim($this->baseUri, '/') . '/' . ltrim($endpoint, '/');

        $defaultHeaders = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->token,
        ];

        if (isset($options['headers']) && is_array($options['headers'])) {
            foreach ($options['headers'] as $headerName => $headerValue) {
                $defaultHeaders[] = $headerName . ': ' . $headerValue;
            }
        }

        $ch = curl_init();

        if (strtoupper($method) === 'GET' && isset($options['query']) && is_array($options['query'])) {
            $queryString = http_build_query($options['query']);
            $url .= '?' . $queryString;
        }

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if (isset($options['json'])) {
                    $jsonData = json_encode($options['json'], JSON_UNESCAPED_SLASHES);
                    $defaultHeaders[] = 'Content-Type: application/json';
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                }
                break;

            case 'PUT':
            case 'DELETE':
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
                if (isset($options['json'])) {
                    $jsonData = json_encode($options['json'], JSON_UNESCAPED_SLASHES);
                    $defaultHeaders[] = 'Content-Type: application/json';
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                }
                break;

            default:
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                break;
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $defaultHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $this->logError(
                "cURL Error, {$errorMessage}",
                $url,
                $method,
                $defaultHeaders,
                $jsonData ?? null
            );
            curl_close($ch);
            return false;
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($statusCode >= 200 && $statusCode < 300) {
            return is_array($decoded) ? $decoded : [];
        }

        $this->logError(
            "HTTP status code $statusCode",
            $url,
            $method,
            $defaultHeaders,
            $jsonData ?? null,
            $response
        );
        return false;
    }
    
    private function logError($context, $url, $method, $headers, $bodyRequest = null, $rawResponse = null)
    {
        error_log("[ProntoPaga Error] ===== START ERROR LOG =====");
        error_log("[ProntoPaga Error] Context: $context");
        error_log("[ProntoPaga Error] URL: $url");
        error_log("[ProntoPaga Error] Method: $method");
        error_log("[ProntoPaga Error] Headers: " . print_r($headers, true));
        if ($bodyRequest) {
            error_log("[ProntoPaga Error] BodyRequest: $bodyRequest");
        }
        if ($rawResponse) {
            error_log("[ProntoPaga Error] RawResponse: $rawResponse");
        }
        error_log("[ProntoPaga Error] ===== END ERROR LOG =====");
        error_log("");
    }
}