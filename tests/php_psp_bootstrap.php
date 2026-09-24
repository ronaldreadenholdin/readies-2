<?php

declare(strict_types=1);

$root = dirname(__DIR__) . '/bob-c-0609/laravel';
$requires = [
    '/app/Contracts/PspAdaptorInterface.php',
    '/app/DTO/PspPaymentRequest.php',
    '/app/DTO/PspPaymentResponse.php',
    '/app/DTO/PspRefundRequest.php',
    '/app/DTO/PspRefundResponse.php',
    '/app/DTO/PspWebhookResult.php',
    '/app/Services/Psp/Contracts/PspConverterInterface.php',
    '/app/Services/Psp/PspNormalizedContract.php',
    '/app/Services/Psp/ConversionKillerChecker.php',
    '/app/Services/Psp/CascadeRequirementsResolver.php',
    '/app/Services/Psp/AbstractPspAdaptor.php',
    '/app/Services/Psp/Converters/FblsP003Converter.php',
    '/app/Services/Psp/Fixtures/FblsP003FixtureTransport.php',
    '/app/Services/Psp/Adaptors/FblsP003Adaptor.php',
    '/app/Services/Psp/PspAdapterRegistry.php',
    '/app/Services/Psp/PspAdaptorFactory.php',
    '/app/Services/Psp/PspConformanceReportWriter.php',
    '/app/Services/Psp/PspConformanceGate.php',
    '/app/Services/Psp/PspCascadeRouter.php',
];

foreach ($requires as $file) {
    require_once $root . $file;
}
