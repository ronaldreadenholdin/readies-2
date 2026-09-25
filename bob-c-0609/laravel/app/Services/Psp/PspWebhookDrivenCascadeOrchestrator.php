<?php

namespace App\Services\Psp;

use App\DTO\PspPaymentRequest;
use App\Services\Psp\Recovery\PaymentRecoveryService;

final class PspWebhookDrivenCascadeOrchestrator
{
    public function __construct(
        private PspAdapterRegistry $registry,
        private CascadeRequirementsResolver $requirements,
        private PaymentRecoveryService $recovery,
        private array $config = [],
    ) {
    }

    public function start(array $orderedPspCodes, PspPaymentRequest $request, array $recoveryPspCandidates = []): array
    {
        $maxAttempts = (int) ($this->config['max_attempts'] ?? 3);
        $cascade = array_slice(array_values(array_map(static fn ($code): string => strtoupper((string) $code), $orderedPspCodes)), 0, $maxAttempts);
        $preCollect = $this->requirements->validatePreCollect($request, $cascade);
        $state = [
            'merchant_reference' => (string) $request->get('merchant_reference'),
            'request' => $request->toArray(),
            'cascade' => $cascade,
            'max_attempts' => $maxAttempts,
            'recovery_psp_candidates' => array_values(array_map(static fn ($code): string => strtoupper((string) $code), $recoveryPspCandidates)),
            'attempts' => [],
            'failed_psps' => [],
            'audit' => [],
            'flags' => [],
            'status' => 'created',
            'final_outcome' => null,
            'recovery' => null,
        ];

        if (! $preCollect['ok']) {
            $state['status'] = 'blocked_before_first_hop';
            $state['final_outcome'] = 'missing_precollect_fields';
            $state['missing_fields'] = $preCollect['missing_fields'];

            return $state;
        }

        return $this->attemptNext($state);
    }

    public function handleWebhook(array $state, string $pspCode, array $normalizedEvent): array
    {
        $pspCode = strtoupper($pspCode);
        $status = (string) ($normalizedEvent['status'] ?? 'unknown');
        if (in_array($status, ['captured', 'authorized', 'settled'], true)) {
            return $this->handleSuccessWebhook($state, $pspCode, $normalizedEvent);
        }
        if (in_array($status, ['failed', 'declined', 'cancelled'], true)) {
            return $this->handleFailureWebhook($state, $pspCode, $normalizedEvent);
        }

        $state['status'] = 'awaiting_final_status';
        $state['final_outcome'] = 'awaiting_final_status';
        $state['audit'][] = $this->auditRow($pspCode, 'webhook_non_final', null, 'awaiting_final_status', $normalizedEvent);

        return $state;
    }

    public function handleTimeout(array $state, string $pspCode): array
    {
        $pspCode = strtoupper($pspCode);
        $attemptIndex = $this->attemptIndex($state, $pspCode);
        $paymentId = $attemptIndex === null ? $pspCode . '-payment' : (string) ($state['attempts'][$attemptIndex]['payment_id'] ?? ($pspCode . '-payment'));
        $statusResponse = $this->registry->get($pspCode)->getPaymentStatus($paymentId)->normalized();
        $status = (string) ($statusResponse['status'] ?? 'unknown');
        $waitedMs = $this->waitTimeoutMs($pspCode);

        if (in_array($status, ['failed', 'declined', 'cancelled'], true)) {
            return $this->confirmFailureAndMaybeCascade($state, $pspCode, $statusResponse, 'timeout_status_failed', $waitedMs);
        }

        $state['status'] = 'awaiting_final_status';
        $state['final_outcome'] = 'awaiting_final_status';
        if ($attemptIndex !== null) {
            $state['attempts'][$attemptIndex]['status_received'] = $status;
            $state['attempts'][$attemptIndex]['waited_ms'] = $waitedMs;
            $state['attempts'][$attemptIndex]['outcome'] = 'awaiting_final_status';
        }
        $state['audit'][] = $this->auditRow($pspCode, 'timeout_status_' . $status, $waitedMs, 'awaiting_final_status', $statusResponse);

        return $state;
    }

    private function attemptNext(array $state): array
    {
        if (count($state['attempts']) >= $state['max_attempts'] || count($state['attempts']) >= count($state['cascade'])) {
            return $this->createRecovery($state);
        }

        $pspCode = $state['cascade'][count($state['attempts'])];
        $attemptStartedAt = gmdate('Y-m-d\TH:i:s\Z');
        $response = $this->registry->get($pspCode)->createPayment(PspPaymentRequest::fromArray($state['request']))->normalized();
        $declineClass = $response['decline']['class'] ?? 'none';

        $state['attempts'][] = [
            'psp_code' => $pspCode,
            'cascade_index' => count($state['attempts']) + 1,
            'attempted_at' => $attemptStartedAt,
            'payment_id' => $response['payment_id'] ?? null,
            'sync_status' => $response['status'] ?? 'unknown',
            'webhook_received' => null,
            'status_received' => null,
            'waited_ms' => 0,
            'cascade_reason' => null,
            'confirmed_failure' => false,
            'outcome' => 'awaiting_failure_webhook',
        ];

        if ($declineClass === 'hard') {
            $state['status'] = 'stopped';
            $state['final_outcome'] = 'hard_decline';
            $state['attempts'][array_key_last($state['attempts'])]['outcome'] = 'hard_decline';
            $state['audit'][] = $this->auditRow($pspCode, 'hard_decline', 0, 'hard_decline', $response);

            return $state;
        }

        if (($response['status'] ?? null) === 'skipped') {
            return $this->confirmFailureAndMaybeCascade($state, $pspCode, $response, 'missing_required_field', 0);
        }

        $state['status'] = 'awaiting_failure_webhook';
        $state['final_outcome'] = 'awaiting_final_status';
        $state['audit'][] = $this->auditRow($pspCode, 'attempt_created', 0, 'awaiting_failure_webhook', $response);

        return $state;
    }

    private function handleFailureWebhook(array $state, string $pspCode, array $normalizedEvent): array
    {
        $waitedMs = (int) ($normalizedEvent['metadata']['waited_ms'] ?? 0);

        return $this->confirmFailureAndMaybeCascade($state, $pspCode, $normalizedEvent, 'webhook_failed', $waitedMs);
    }

    private function handleSuccessWebhook(array $state, string $pspCode, array $normalizedEvent): array
    {
        $attemptIndex = $this->attemptIndex($state, $pspCode);
        $latestAttempt = count($state['attempts']) - 1;
        if ($attemptIndex !== null && $attemptIndex < $latestAttempt) {
            $state['flags'][] = [
                'type' => 'late_success_possible_double_charge',
                'merchant_reference' => $state['merchant_reference'],
                'psp_code' => $pspCode,
                'idempotency_key' => $state['merchant_reference'],
            ];
            $state['audit'][] = $this->auditRow($pspCode, 'late_success_webhook', null, 'possible_double_charge', $normalizedEvent);

            return $state;
        }

        $state['status'] = 'succeeded';
        $state['final_outcome'] = 'success';
        if ($attemptIndex !== null) {
            $state['attempts'][$attemptIndex]['webhook_received'] = 'success';
            $state['attempts'][$attemptIndex]['outcome'] = 'success';
        }
        $state['audit'][] = $this->auditRow($pspCode, 'success_webhook', null, 'success', $normalizedEvent);

        return $state;
    }

    private function confirmFailureAndMaybeCascade(array $state, string $pspCode, array $normalizedEvent, string $source, int $waitedMs): array
    {
        $attemptIndex = $this->attemptIndex($state, $pspCode);
        $declineClass = $normalizedEvent['decline']['class'] ?? 'soft';
        $cascadeReason = $normalizedEvent['decline']['cascade_reason'] ?? $source;
        if ($attemptIndex !== null) {
            $state['attempts'][$attemptIndex]['webhook_received'] = str_starts_with($source, 'webhook') ? ($normalizedEvent['status'] ?? 'failed') : null;
            $state['attempts'][$attemptIndex]['status_received'] = str_starts_with($source, 'timeout_status') ? ($normalizedEvent['status'] ?? 'failed') : null;
            $state['attempts'][$attemptIndex]['waited_ms'] = $waitedMs;
            $state['attempts'][$attemptIndex]['cascade_reason'] = $cascadeReason;
            $state['attempts'][$attemptIndex]['confirmed_failure'] = true;
            $state['attempts'][$attemptIndex]['outcome'] = $declineClass === 'hard' ? 'hard_decline' : 'confirmed_failure';
        }
        $state['audit'][] = $this->auditRow($pspCode, $source, $waitedMs, $declineClass === 'hard' ? 'hard_decline' : 'confirmed_failure', $normalizedEvent);

        if ($declineClass === 'hard') {
            $state['status'] = 'stopped';
            $state['final_outcome'] = 'hard_decline';

            return $state;
        }

        $state['failed_psps'][] = $pspCode;
        if (count($state['failed_psps']) >= $state['max_attempts']) {
            return $this->createRecovery($state);
        }

        return $this->attemptNext($state);
    }

    private function createRecovery(array $state): array
    {
        $state['status'] = 'recovery';
        $state['final_outcome'] = 'pay_by_link_sent';
        $state['recovery'] = $this->recovery->create(
            PspPaymentRequest::fromArray($state['request']),
            $state['failed_psps'],
            $state['recovery_psp_candidates'],
        );
        if (! $state['recovery']['email_sent']) {
            $state['final_outcome'] = 'failed_3';
        }
        $state['audit'][] = [
            'psp_code' => $state['recovery']['psp_code'],
            'connection' => 'pay_by_link',
            'attempted_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'webhook_or_status_received' => null,
            'waited_ms' => null,
            'cascade_reason' => 'three_confirmed_failures',
            'outcome' => $state['recovery']['email_sent'] ? 'pay_by_link_sent' : 'pay_by_link_created_email_not_sent',
        ];

        return $state;
    }

    private function auditRow(string $pspCode, string $event, ?int $waitedMs, string $outcome, array $payload): array
    {
        return [
            'psp_code' => $pspCode,
            'connection' => 'create',
            'attempted_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'webhook_or_status_received' => $event,
            'waited_ms' => $waitedMs,
            'cascade_reason' => $payload['decline']['cascade_reason'] ?? null,
            'outcome' => $outcome,
        ];
    }

    private function attemptIndex(array $state, string $pspCode): ?int
    {
        foreach ($state['attempts'] as $index => $attempt) {
            if (($attempt['psp_code'] ?? null) === $pspCode) {
                return $index;
            }
        }

        return null;
    }

    private function waitTimeoutMs(string $pspCode): int
    {
        return (int) ($this->config['wait_timeout_ms'][$pspCode] ?? $this->config['default_wait_timeout_ms'] ?? 30000);
    }
}
