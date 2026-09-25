<?php

namespace App\Services\Psp;

use App\Contracts\PspAdaptorInterface;
use App\Services\Psp\Commercial\PspCommercialProfile;
use App\Services\Psp\Credentials\FakePspCredentialProvider;
use App\Services\Psp\Credentials\PspCredentialProviderInterface;
use App\Services\Psp\Fixtures\FblsP003FixtureTransport;
use RuntimeException;

final class PspConformanceGate
{
    private ConversionKillerChecker $checker;

    public function __construct(
        private PspAdapterRegistry $registry,
        private string $fixtureRoot,
        private ?PspCredentialProviderInterface $credentials = null,
        ?ConversionKillerChecker $checker = null,
    ) {
        $this->credentials ??= new FakePspCredentialProvider();
        $this->checker = $checker ?? new ConversionKillerChecker();
    }

    public function run(string $pspCode, ?string $timestamp = null, ?string $outputRoot = null): array
    {
        $pspCode = strtoupper(trim($pspCode));
        $adaptor = $this->registry->get($pspCode);
        $fixtures = $this->fixturesFor($pspCode);

        $allChecks = [];
        $issues = [];

        foreach ($this->runPreflightChecks($pspCode, $fixtures) as $row) {
            $allChecks[] = $row;
            if (! $row['passed']) {
                $issues[] = $row;
            }
        }

        $request = $fixtures->paymentRequest();
        $converter = $this->converterFrom($adaptor);
        foreach ($this->checker->checkRequiredFields($converter, $request, 'create') as $row) {
            $allChecks[] = $row;
            if (! $row['passed']) {
                $issues[] = $row;
            }
        }

        foreach ($this->checker->checkDeclaredFieldDependencies($converter) as $row) {
            $allChecks[] = $row;
            if (! $row['passed']) {
                $issues[] = $row;
            }
        }

        $profileData = $fixtures->commercialProfile();
        $liveCredentialStatus = $this->credentials->status($pspCode, 'live');
        $profileData['live_keys_status'] = $this->credentials->get($pspCode, 'live') === null ? 'missing' : 'received';
        $profile = new PspCommercialProfile($profileData);
        foreach ($this->checker->checkCommercialProfile($profile) as $row) {
            $allChecks[] = $row;
            if (! $row['passed']) {
                $issues[] = $row;
            }
        }

        foreach ($this->checker->checkHardcodedSecrets($pspCode, $this->adapterSourceFiles($pspCode)) as $row) {
            $allChecks[] = $row;
            if (! $row['passed']) {
                $issues[] = $row;
            }
        }

        foreach ($this->runConnections($pspCode, $adaptor, $fixtures) as $connectionRows) {
            foreach ($connectionRows as $row) {
                $allChecks[] = $row;
                if (! $row['passed']) {
                    $issues[] = $row;
                }
            }
        }

        $total = count($allChecks);
        $passed = count(array_filter($allChecks, static fn (array $row): bool => $row['passed'] === true));
        $failed = $total - $passed;
        $score = $total === 0 ? 0.0 : round(($passed / $total) * 100, 2);

        $report = [
            'run' => [
                'timestamp' => $timestamp ?? gmdate('Ymd-His'),
                'mode' => 'offline-fixtures',
                'neckermann_target_ready' => true,
                'notes' => 'Designed to swap fixture transport for the Neckermann test merchant later; this run made no real PSP calls.',
            ],
            'credential_status' => [
                $pspCode => [
                    'live' => $liveCredentialStatus,
                ],
            ],
            'summary' => [
                $pspCode => [
                    'total_checks' => $total,
                    'passed' => $passed,
                    'failed' => $failed,
                    'score_percent' => $score,
                    'eligible_for_cascade' => $score === 100.0,
                ],
            ],
            'conversion_killers' => $issues,
            'open_questions' => [
                $pspCode => $profile->openQuestions(),
            ],
            'checks' => $allChecks,
        ];

        if ($outputRoot !== null) {
            $report['artifacts'] = (new PspConformanceReportWriter($outputRoot))->write($report);
        }

        return $report;
    }

    private function runConnections(string $pspCode, PspAdaptorInterface $adaptor, FblsP003FixtureTransport $fixtures): array
    {
        $request = $fixtures->paymentRequest();
        $refundRequest = $fixtures->refundRequest();

        $create = $adaptor->createPayment($request)->normalized();
        $status = $adaptor->getPaymentStatus((string) $create['payment_id'])->normalized();
        $refund = $adaptor->refund($refundRequest)->normalized();
        $webhook = $adaptor->handleWebhook($fixtures->webhookHeaders(), $fixtures->webhookPayload())->normalized();

        return [
            $this->checker->checkNormalizedOutput($pspCode, 'create', $create, $fixtures->golden('create')),
            $this->checker->checkNormalizedOutput($pspCode, 'status', $status, $fixtures->golden('status')),
            $this->checker->checkNormalizedOutput($pspCode, 'refund', $refund, $fixtures->golden('refund')),
            $this->checker->checkNormalizedOutput($pspCode, 'webhook', $webhook, $fixtures->golden('webhook')),
        ];
    }

    private function runPreflightChecks(string $pspCode, FblsP003FixtureTransport $fixtures): array
    {
        $rows = [];
        foreach ($fixtures->preflightChecks() as $check) {
            $passed = ($check['status'] ?? null) === 'green';
            $rows[] = [
                'psp_code' => $pspCode,
                'connection' => $check['connection'] ?? 'preflight',
                'preflight_check_id' => $check['id'] ?? 'preflight.unknown',
                'preflight_check_name' => $check['name'] ?? 'Pre-flight check',
                'field_path' => [
                    'internal' => $check['field_path']['internal'] ?? 'preflight',
                    'psp' => $check['field_path']['psp'] ?? 'preflight',
                ],
                'expected' => $check['expected'] ?? 'green',
                'actual' => $check['actual'] ?? ($check['status'] ?? 'unknown'),
                'killer_category' => $check['killer_category'] ?? 'status enum',
                'passed' => $passed,
                'why' => $passed ? 'ok' : ($check['why'] ?? 'The recorded pre-flight fixture is not green.'),
                'code_location' => $check['code_location'] ?? 'pre-flight-test.html::runFullTest',
                'severity' => $check['severity'] ?? 'blocks cascade',
                'suggested_fix' => $check['suggested_fix'] ?? 'Resolve the pre-flight failure and record a green fixture before cascade eligibility.',
            ];
        }

        return $rows;
    }

    private function fixturesFor(string $pspCode): FblsP003FixtureTransport
    {
        if ($pspCode !== 'P003') {
            throw new RuntimeException("No offline conformance fixture is registered for {$pspCode}.");
        }

        return new FblsP003FixtureTransport(rtrim($this->fixtureRoot, '/') . '/p003');
    }

    private function converterFrom(PspAdaptorInterface $adaptor): Contracts\PspConverterInterface
    {
        $ref = new \ReflectionClass($adaptor);
        do {
            if ($ref->hasProperty('converter')) {
                $property = $ref->getProperty('converter');
                $property->setAccessible(true);
                $converter = $property->getValue($adaptor);
                if ($converter instanceof Contracts\PspConverterInterface) {
                    return $converter;
                }
            }
            $ref = $ref->getParentClass();
        } while ($ref !== false);

        throw new RuntimeException('Unable to inspect PSP converter for conformance checks.');
    }

    private function adapterSourceFiles(string $pspCode): array
    {
        if ($pspCode !== 'P003') {
            return [];
        }

        $root = dirname(__DIR__, 2);
        $paths = [
            $root . '/Services/Psp/Adaptors/FblsP003Adaptor.php',
            $root . '/Services/Psp/Converters/FblsP003Converter.php',
        ];

        $files = [];
        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[$path] = (string) file_get_contents($path);
            }
        }

        return $files;
    }
}
