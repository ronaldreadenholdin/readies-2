<?php

declare(strict_types=1);

use App\Services\Psp\ConversionKillerChecker;

require_once __DIR__ . '/php_psp_bootstrap.php';

$golden = json_decode((string) file_get_contents(__DIR__ . '/../bob-c-0609/laravel/resources/psp-conformance/p003/golden/create.json'), true);
$golden['amount']['minor_units'] = 4994;

$checker = new ConversionKillerChecker();
$issues = $checker->issueRows($checker->checkNormalizedOutput('P003', 'create', $golden));

echo json_encode($issues, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
