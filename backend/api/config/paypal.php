<?php
declare(strict_types=1);
require_once __DIR__ . '/database.php';

function paypalConfiguration(): array
{
    $environment = mb_strtolower(trim(liquorsoftEnv('PAYPAL_ENVIRONMENT', 'sandbox')));
    $rate = filter_var(liquorsoftEnv('PAYPAL_COP_PER_USD', ''), FILTER_VALIDATE_FLOAT);
    $clientId = liquorsoftEnv('PAYPAL_CLIENT_ID', '');
    $clientSecret = liquorsoftEnv('PAYPAL_CLIENT_SECRET', '');
    $configurationStatus = match (true) {
        $clientId === '' || $clientSecret === '' => 'missing_credentials',
        !in_array($environment, ['sandbox', 'live'], true) => 'invalid_environment',
        $rate === false || $rate <= 0 || !is_finite((float) $rate) => 'invalid_exchange_rate',
        !function_exists('curl_init') => 'curl_unavailable',
        default => 'configured',
    };

    return [
        'enabled' => $configurationStatus === 'configured',
        'configurationStatus' => $configurationStatus,
        'clientId' => $clientId,
        'clientSecret' => $clientSecret,
        'environment' => $environment,
        'currency' => 'USD',
        'copPerUsd' => $rate !== false && $rate > 0 ? (float) $rate : null,
        'apiUrl' => $environment === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com',
        'webhookId' => liquorsoftEnv('PAYPAL_WEBHOOK_ID', ''),
    ];
}

function paypalOAuthConfigurationStatus(array $configuration): string
{
    return match (true) {
        $configuration['clientId'] === '' || $configuration['clientSecret'] === '' => 'missing_credentials',
        !in_array($configuration['environment'], ['sandbox', 'live'], true) => 'invalid_environment',
        !function_exists('curl_init') => 'curl_unavailable',
        default => 'configured',
    };
}

function paypalOAuthToken(): array
{
    static $accessToken;
    static $tokenExpiresAt = 0;
    if ($accessToken !== null && $tokenExpiresAt > time() + 30) {
        return ['status' => 'authenticated', 'httpStatus' => 200, 'accessToken' => $accessToken];
    }

    $configuration = paypalConfiguration();
    $oauthConfigurationStatus = paypalOAuthConfigurationStatus($configuration);
    if ($oauthConfigurationStatus !== 'configured') {
        return [
            'status' => $oauthConfigurationStatus,
            'httpStatus' => null,
            'accessToken' => null,
        ];
    }
    $handle = curl_init($configuration['apiUrl'] . '/v1/oauth2/token');
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_USERPWD => $configuration['clientId'] . ':' . $configuration['clientSecret'],
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: en_US',
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($handle);
    $httpStatus = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);

    if ($curlError !== '') {
        return ['status' => 'network_error', 'httpStatus' => null, 'accessToken' => null];
    }
    $result = paypalClassifyOAuthResponse($httpStatus, is_string($body) ? $body : '');
    if ($result['status'] === 'authenticated') {
        $accessToken = $result['accessToken'];
        $tokenExpiresAt = time() + max(60, (int) ($result['expiresIn'] ?? 300));
    }
    unset($result['expiresIn']);
    return $result;
}

function paypalClassifyOAuthResponse(int $httpStatus, string $body, string $networkError = ''): array
{
    if ($networkError !== '') {
        return ['status' => 'network_error', 'httpStatus' => null, 'accessToken' => null];
    }
    if ($httpStatus === 401) {
        return ['status' => 'invalid_credentials', 'httpStatus' => 401, 'accessToken' => null];
    }
    if ($httpStatus >= 500 || $httpStatus === 429) {
        return ['status' => 'service_error', 'httpStatus' => $httpStatus, 'accessToken' => null];
    }
    if ($httpStatus < 200 || $httpStatus >= 300) {
        return ['status' => 'unexpected_http_status', 'httpStatus' => $httpStatus, 'accessToken' => null];
    }
    $response = json_decode($body, true);
    if (!is_array($response) || !is_string($response['access_token'] ?? null) || $response['access_token'] === '') {
        return ['status' => 'invalid_response', 'httpStatus' => $httpStatus, 'accessToken' => null];
    }
    return [
        'status' => 'authenticated',
        'httpStatus' => $httpStatus,
        'accessToken' => $response['access_token'],
        'expiresIn' => $response['expires_in'] ?? 300,
    ];
}

function paypalCredentialDiagnostic(): array
{
    $configuration = paypalConfiguration();
    $oauthConfigurationStatus = paypalOAuthConfigurationStatus($configuration);
    if ($oauthConfigurationStatus !== 'configured') {
        return [
            'status' => $oauthConfigurationStatus,
            'httpStatus' => null,
            'authenticated' => false,
            'environment' => $configuration['environment'],
            'paymentEnabled' => false,
            'paymentConfigurationStatus' => $configuration['configurationStatus'],
        ];
    }
    $oauth = paypalOAuthToken();
    return [
        'status' => $oauth['status'],
        'httpStatus' => $oauth['httpStatus'],
        'authenticated' => $oauth['status'] === 'authenticated',
        'environment' => $configuration['environment'],
        'paymentEnabled' => $configuration['enabled'] && $oauth['status'] === 'authenticated',
        'paymentConfigurationStatus' => $configuration['configurationStatus'],
    ];
}

function paypalAmountUsd(int $copCents, float $copPerUsd): string
{
    return number_format(round(($copCents / 100) / $copPerUsd, 2), 2, '.', '');
}

function paypalOrderIsApproved(array $order, string $orderId, string $key, string $amountUsd): bool
{
    $purchaseUnits = $order['purchase_units'] ?? [];
    if (!is_array($purchaseUnits) || count($purchaseUnits) !== 1) return false;
    $unit = $purchaseUnits[0];
    if (!is_array($unit)) return false;
    $amount = $unit['amount'] ?? [];
    if (!is_array($amount)) return false;
    return ($order['id'] ?? '') === $orderId
        && in_array(($order['status'] ?? ''), ['APPROVED', 'COMPLETED'], true)
        && ($unit['custom_id'] ?? '') === $key
        && ($unit['reference_id'] ?? '') === $key
        && ($amount['currency_code'] ?? '') === 'USD'
        && number_format((float) ($amount['value'] ?? 0), 2, '.', '') === $amountUsd;
}

function paypalCompletedCaptureId(array $order, string $orderId, string $key, string $amountUsd): ?string
{
    $purchaseUnits = $order['purchase_units'] ?? [];
    if (!is_array($purchaseUnits) || count($purchaseUnits) !== 1) return null;
    $unit = $purchaseUnits[0];
    if (!is_array($unit)) return null;
    $payments = $unit['payments'] ?? [];
    $captures = is_array($payments) ? ($payments['captures'] ?? []) : [];
    if (!is_array($captures) || count($captures) !== 1 || !is_array($captures[0])) return null;
    $capture = $captures[0];
    $amount = $capture['amount'] ?? [];
    if (!is_array($amount)) return null;
    $captureId = $capture['id'] ?? '';
    if (($order['id'] ?? '') !== $orderId
        || ($order['status'] ?? '') !== 'COMPLETED'
        || ($unit['custom_id'] ?? '') !== $key
        || ($unit['reference_id'] ?? '') !== $key
        || ($capture['status'] ?? '') !== 'COMPLETED'
        || ($amount['currency_code'] ?? '') !== 'USD'
        || number_format((float) ($amount['value'] ?? 0), 2, '.', '') !== $amountUsd
        || !is_string($captureId) || $captureId === '') {
        return null;
    }
    return $captureId;
}

function paypalApiRequest(string $method, string $path, ?array $body = null, array $headers = []): array
{
    $configuration = paypalConfiguration();
    if (!$configuration['enabled']) {
        throw new RuntimeException('PayPal no está configurado.');
    }
    $oauth = paypalOAuthToken();
    if ($oauth['status'] !== 'authenticated' || !is_string($oauth['accessToken'])) {
        error_log('PayPal OAuth failed (status ' . $oauth['status'] . ').');
        throw new RuntimeException('PayPal no pudo autenticar la solicitud.');
    }

    $requestHeaders = array_merge([
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $oauth['accessToken'],
    ], $headers);
    $handle = curl_init($configuration['apiUrl'] . $path);
    curl_setopt_array($handle, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($body !== null) {
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
    }
    $responseBody = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $requestError = curl_error($handle);
    curl_close($handle);

    $response = is_string($responseBody) ? json_decode($responseBody, true) : null;
    if ($requestError !== '' || $status < 200 || $status >= 300 || !is_array($response)) {
        error_log('PayPal API request failed (HTTP ' . $status . ').');
        throw new RuntimeException('PayPal no pudo completar la operación.');
    }
    return $response;
}


/**
 * Reembolsa una captura completa. Se usa cuando el cobro se completó en PayPal
 * pero la transacción local no pudo finalizar (por ejemplo, cambió el stock).
 */
function paypalRefundCapture(string $captureId, string $idempotencyKey): string
{
    $response = paypalApiRequest(
        'POST',
        '/v2/payments/captures/' . rawurlencode($captureId) . '/refund',
        null,
        ['PayPal-Request-Id: ' . substr(hash('sha256', 'refund-' . $idempotencyKey), 0, 36)]
    );
    $status = (string) ($response['status'] ?? '');
    if (!in_array($status, ['COMPLETED', 'PENDING'], true)) {
        throw new RuntimeException('PayPal no confirmó el reembolso de la captura.');
    }
    return (string) ($response['id'] ?? '');
}
