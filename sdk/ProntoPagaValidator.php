<?php

namespace ProntoPaga;

use Cart;
use Configuration;
use Validate;
use Tools;

require_once __DIR__ . '/ProntoPagaLogger.php';
require_once __DIR__ . '/ProntoPagaConfig.php';

class ProntoPagaValidator
{
    /**
     * Valida si el IP actual está autorizado para enviar webhooks.
     */
    public static function isAuthorizedIP(): bool
    {
        $clientIp = Tools::getRemoteAddr();

        if (!in_array($clientIp, ProntoPagaConfig::ALLOWED_IPS, true)) {
            ProntoPagaLogger::error('Unauthorized IP attempt', ['ip' => $clientIp]);
            return false;
        }

        return true;
    }
    
    /**
     * Valida el parámetro secure_psref (estructura, token y existencia del carrito).
     *
     * @param string|null $psref
     * @return bool
     */
    public static function validatePSref(?string $psref): bool
    {
        if (empty($psref)) {
            ProntoPagaLogger::error('Missing psref');
            return false;
        }

        $decoded = base64_decode($psref, true);

        if (!$decoded || substr_count($decoded, '|') !== 2) {
            ProntoPagaLogger::error('Malformed psref', ['value' => $psref, 'decoded' => $decoded]);
            return false;
        }

        list($_, $cartId, $token) = explode('|', $decoded);

        if (!ctype_digit($cartId)) {
            ProntoPagaLogger::error('Invalid cart ID in psref', ['cartId' => $cartId]);
            return false;
        }

        $expectedToken = Configuration::get('VC_PRONTOPAGA_ACCOUNT_TOKEN');

        if ($token !== $expectedToken) {
            ProntoPagaLogger::error('Token mismatch in psref', [
                'expected' => $expectedToken,
                'received' => $token
            ]);
            return false;
        }

        $cart = new Cart((int) $cartId);
        if (!Validate::isLoadedObject($cart)) {
            ProntoPagaLogger::error('Cart object not found', ['cartId' => $cartId]);
            return false;
        }

        return true;
    }
    
    /**
     * Valida que el monto y moneda del pago coincidan con lo esperado.
     */
    public static function isMatchingPayment(float $expectedAmount, float $receivedAmount, string $expectedCurrency, string $receivedCurrency): bool
    {
        $isMatching = abs($expectedAmount - $receivedAmount) <= 0.01 && $expectedCurrency === $receivedCurrency;

        if (!$isMatching) {
            ProntoPagaLogger::error('Amount or currency mismatch', [
                'expected_amount' => $expectedAmount,
                'received_amount' => $receivedAmount,
                'expected_currency' => $expectedCurrency,
                'received_currency' => $receivedCurrency
            ]);
        }

        return $isMatching;
    }
    
    /**
     * Verifica que todos los campos requeridos estén presentes y no vacíos en el payload.
     *
     * @param array $payload
     * @param array $fieldsRequired
     * @return bool
     */
    public static function hasRequiredFields(array $payload, array $fieldsRequired): bool
    {
        foreach ($fieldsRequired as $field) {
            if (!isset($payload[$field]) || $payload[$field] === '') {
                ProntoPagaLogger::error("Missing required field: {$field}", ['payload' => $payload]);
                return false;
            }
        }
        return true;
    }
}