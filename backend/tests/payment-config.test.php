<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/config/paypal.php';

$cases = [
    ['development', '1', true],
    ['production', '1', false],
    ['development', '0', false],
    ['test', '1', false],
];
foreach ($cases as [$environment, $enabled, $expected]) {
    putenv('LIQUORSOFT_APP_ENV=' . $environment);
    putenv('LIQUORSOFT_PAYMENT_SIMULATOR=' . $enabled);
    if (paymentSimulatorEnabled() !== $expected) {
        fwrite(STDERR, "Simulator gate failed for {$environment}/{$enabled}\n");
        exit(1);
    }
}
$timezone = new DateTimeZone('America/Bogota');
$today = new DateTimeImmutable('today', $timezone);
$adultDate = $today->modify('-18 years')->format('Y-m-d');
$minorDate = $today->modify('-18 years +1 day')->format('Y-m-d');
if (!isAdultBirthDate($adultDate) || isAdultBirthDate($minorDate)
    || isAdultBirthDate('2999-02-31') || isAdultBirthDate('not-a-date')) {
    fwrite(STDERR, "Birth date age checks failed.\n");
    exit(1);
}
putenv('PAYPAL_CLIENT_ID=sandbox-client');
putenv('PAYPAL_CLIENT_SECRET=sandbox-secret');
putenv('PAYPAL_ENVIRONMENT=sandbox');
putenv('PAYPAL_COP_PER_USD=4000');
$paypal = paypalConfiguration();
if (!$paypal['enabled'] || $paypal['apiUrl'] !== 'https://api-m.sandbox.paypal.com'
    || $paypal['copPerUsd'] !== 4000.0
    || paypalAmountUsd(400000, 4000.0) !== '1.00'
    || paypalAmountUsd(1250000, 4000.0) !== '3.13') {
    fwrite(STDERR, "PayPal Sandbox configuration or currency conversion checks failed.\n");
    exit(1);
}
$oauthCases = [
    [200, '{"access_token":"mock-token","expires_in":3600}', '', 'authenticated'],
    [401, '{"error":"invalid_client"}', '', 'invalid_credentials'],
    [503, '{"error":"service_unavailable"}', '', 'service_error'],
    [200, '{"access_token":', '', 'invalid_response'],
    [0, '', 'timeout', 'network_error'],
];
foreach ($oauthCases as [$httpStatus, $body, $networkError, $expectedStatus]) {
    $result = paypalClassifyOAuthResponse($httpStatus, $body, $networkError);
    if ($result['status'] !== $expectedStatus) {
        fwrite(STDERR, "PayPal OAuth response classification failed for {$expectedStatus}.\n");
        exit(1);
    }
}
$approvedOrder = [
    'id' => 'ORDER-1',
    'status' => 'APPROVED',
    'purchase_units' => [[
        'custom_id' => 'purchase-key',
        'reference_id' => 'purchase-key',
        'amount' => ['currency_code' => 'USD', 'value' => '3.13'],
    ]],
];
if (!paypalOrderIsApproved($approvedOrder, 'ORDER-1', 'purchase-key', '3.13')) {
    fwrite(STDERR, "A matching approved PayPal order was rejected.\n");
    exit(1);
}
$tamperedOrder = $approvedOrder;
$tamperedOrder['purchase_units'][0]['amount']['value'] = '0.01';
if (paypalOrderIsApproved($tamperedOrder, 'ORDER-1', 'purchase-key', '3.13')
    || paypalOrderIsApproved($approvedOrder, 'OTHER-ORDER', 'purchase-key', '3.13')
    || paypalOrderIsApproved(array_replace($approvedOrder, ['status' => 'CREATED']), 'ORDER-1', 'purchase-key', '3.13')) {
    fwrite(STDERR, "PayPal order amount, ID, or status tampering was accepted.\n");
    exit(1);
}
$capturedOrder = array_replace($approvedOrder, [
    'status' => 'COMPLETED',
    'purchase_units' => [[
        'custom_id' => 'purchase-key',
        'reference_id' => 'purchase-key',
        'payments' => ['captures' => [[
            'id' => 'CAPTURE-1',
            'status' => 'COMPLETED',
            'amount' => ['currency_code' => 'USD', 'value' => '3.13'],
        ]]],
    ]],
]);
if (paypalCompletedCaptureId($capturedOrder, 'ORDER-1', 'purchase-key', '3.13') !== 'CAPTURE-1') {
    fwrite(STDERR, "A matching completed PayPal capture was rejected.\n");
    exit(1);
}
$capturedOrder['purchase_units'][0]['payments']['captures'][0]['status'] = 'PENDING';
if (paypalCompletedCaptureId($capturedOrder, 'ORDER-1', 'purchase-key', '3.13') !== null) {
    fwrite(STDERR, "A non-completed PayPal capture was accepted.\n");
    exit(1);
}
putenv('PAYPAL_COP_PER_USD=0');
if (paypalConfiguration()['enabled']) {
    fwrite(STDERR, "PayPal must remain disabled without a positive COP/USD rate.\n");
    exit(1);
}
putenv('PAYPAL_COP_PER_USD=4000');
putenv('PAYPAL_ENVIRONMENT=live');
if (paypalConfiguration()['enabled']) {
    fwrite(STDERR, "Live PayPal must remain disabled until explicitly implemented.\n");
    exit(1);
}
putenv('PAYPAL_CLIENT_ID=');
if (paypalConfiguration()['configurationStatus'] !== 'missing_credentials') {
    fwrite(STDERR, "PayPal must identify missing credentials.\n");
    exit(1);
}
putenv('PAYPAL_CLIENT_ID=sandbox-client');
putenv('PAYPAL_ENVIRONMENT=https://sandbox.paypal.com');
if (paypalConfiguration()['configurationStatus'] !== 'invalid_environment') {
    fwrite(STDERR, "PayPal must reject a URL where the environment name is expected.\n");
    exit(1);
}
if (array_key_exists('access_token', paypalCredentialDiagnostic())) {
    fwrite(STDERR, "PayPal diagnostics must never expose the access token.\n");
    exit(1);
}
echo "4 simulator, 4 age validation, PayPal config/OAuth, order tamper, and capture-state checks passed.\n";
