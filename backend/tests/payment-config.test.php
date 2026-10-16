<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/config/database.php';

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
echo "4 payment simulator and 4 age validation checks passed.\n";
