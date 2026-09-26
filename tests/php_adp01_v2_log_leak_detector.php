<?php

declare(strict_types=1);

use App\Services\Psp\Security\CardDataLeakDetector;

require_once __DIR__ . '/php_psp_bootstrap.php';

$detector = new CardDataLeakDetector();

$cleanSinks = [
    'laravel_single' => 'payment accepted pan=411111******1111 cvv=[REDACTED_CVV]',
    'stderr' => 'exception context card=[REDACTED_TRACK]',
    'audit' => ['raw_message' => 'declined after masking', 'cvv' => '[REDACTED_CVV]'],
];

$canary = [
    'stdout' => 'fixture leak pan=4111 1111 1111 1111',
    'audit' => ['cvv' => '123'],
];

echo json_encode([
    'clean_findings' => $detector->findings($cleanSinks),
    'canary_findings' => $detector->findings($canary),
    'negative_self_test_catches_canary' => $detector->containsLeak($canary),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
