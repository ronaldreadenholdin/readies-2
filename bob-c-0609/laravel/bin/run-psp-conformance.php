<?php

declare(strict_types=1);

use App\Services\Psp\PspAdaptorFactory;
use App\Services\Psp\PspConformanceGate;

$root = dirname(__DIR__);
$requires = [
    '/app/Contracts/PspAdaptorInterface.php',
    '/app/DTO/PspPaymentRequest.php',
    '/app/DTO/PspPaymentResponse.php',
    '/app/DTO/PspRefundRequest.php',
    '/app/DTO/PspRefundResponse.php',
    '/app/DTO/PspWebhookResult.php',
    '/app/Services/Psp/Contracts/PspConverterInterface.php',
    '/app/Services/Psp/PspNormalizedContract.php',
    '/app/Services/Psp/Credentials/PspCredentialProviderInterface.php',
    '/app/Services/Psp/Credentials/FakePspCredentialProvider.php',
    '/app/Services/Psp/Credentials/VaultPspCredentialProvider.php',
    '/app/Services/Psp/Commercial/PspCommercialProfile.php',
    '/app/Services/Psp/Commercial/PspEligibilityFilter.php',
    '/app/Services/Psp/Trusted/PersonalDataEncryptionInterface.php',
    '/app/Services/Psp/Trusted/InMemoryPersonalDataEncryption.php',
    '/app/Services/Psp/Trusted/CardTokenVaultInterface.php',
    '/app/Services/Psp/Trusted/TrustedCustomerRepositoryInterface.php',
    '/app/Services/Psp/Trusted/InMemoryTrustedCustomerRepository.php',
    '/app/Services/Psp/Trusted/TrustedCustomerPrefillService.php',
    '/app/Services/Psp/Media/WaitingMediaRepositoryInterface.php',
    '/app/Services/Psp/Media/InMemoryWaitingMediaRepository.php',
    '/app/Services/Psp/Media/WaitingMediaResolver.php',
    '/app/Services/Psp/Media/MediaImpressionRepositoryInterface.php',
    '/app/Services/Psp/Media/InMemoryMediaImpressionRepository.php',
    '/app/Services/Psp/Media/MediaImpressionService.php',
    '/app/Services/Psp/Media/MediaFeeStatementService.php',
    '/app/Services/Psp/Media/MediaConversionReportService.php',
    '/app/Services/Psp/Media/MediaVariantSelector.php',
    '/app/Services/Psp/ConversionKillerChecker.php',
    '/app/Services/Psp/CascadeRequirementsResolver.php',
    '/app/Services/Psp/Recovery/PaymentRecoveryMailerInterface.php',
    '/app/Services/Psp/Recovery/FakePaymentRecoveryMailer.php',
    '/app/Services/Psp/Recovery/PayByLinkTokenService.php',
    '/app/Services/Psp/Recovery/PaymentRecoveryEmailBuilder.php',
    '/app/Services/Psp/Recovery/PaymentRecoveryService.php',
    '/app/Services/Psp/AbstractPspAdaptor.php',
    '/app/Services/Psp/Converters/FblsP003Converter.php',
    '/app/Services/Psp/Fixtures/FblsP003FixtureTransport.php',
    '/app/Services/Psp/Adaptors/FblsP003Adaptor.php',
    '/app/Services/Psp/PspAdapterRegistry.php',
    '/app/Services/Psp/PspAdaptorFactory.php',
    '/app/Services/Psp/PspConformanceReportWriter.php',
    '/app/Services/Psp/PspConformanceGate.php',
    '/app/Services/Psp/PspCascadeRouter.php',
    '/app/Services/Psp/PspWebhookDrivenCascadeOrchestrator.php',
];

foreach ($requires as $file) {
    require_once $root . $file;
}

$pspCode = strtoupper($argv[1] ?? 'P003');
$outputRoot = $argv[2] ?? ($root . '/storage/psp-conformance-runs');
$fixtureRoot = $root . '/resources/psp-conformance';

$factory = PspAdaptorFactory::withOfflineFixtures($fixtureRoot);
$gate = new PspConformanceGate($factory->registry(), $fixtureRoot);
$report = $gate->run($pspCode, null, $outputRoot);

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
