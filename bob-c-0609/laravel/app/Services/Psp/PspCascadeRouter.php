<?php

namespace App\Services\Psp;

use App\DTO\PspPaymentRequest;

final class PspCascadeRouter
{
    public function __construct(
        private PspAdapterRegistry $registry,
        private CascadeRequirementsResolver $requirements,
    ) {
    }

    public function route(array $orderedPspCodes, PspPaymentRequest $request): array
    {
        $preCollect = $this->requirements->validatePreCollect($request, $orderedPspCodes);
        if (! $preCollect['ok']) {
            return [
                'ok' => false,
                'blocked_before_first_hop' => true,
                'missing_fields' => $preCollect['missing_fields'],
                'attempts' => [],
                'final_response' => null,
            ];
        }

        $attempts = [];
        foreach ($orderedPspCodes as $index => $code) {
            $code = strtoupper((string) $code);
            $response = $this->registry->get($code)->createPayment($request)->normalized();
            $declineClass = $response['decline']['class'] ?? 'none';
            $cascadeReason = $response['decline']['cascade_reason'] ?? null;
            $shouldContinue = in_array($response['status'] ?? 'unknown', ['failed', 'skipped', 'unknown'], true)
                && $declineClass === 'soft';

            $attempts[] = [
                'psp_code' => $code,
                'cascade_index' => $index + 1,
                'status' => $response['status'] ?? 'unknown',
                'decline_class' => $declineClass,
                'cascade_reason' => $cascadeReason,
                'continued' => $shouldContinue,
            ];

            if (! $shouldContinue) {
                return [
                    'ok' => ($response['status'] ?? null) !== 'skipped',
                    'blocked_before_first_hop' => false,
                    'missing_fields' => [],
                    'attempts' => $attempts,
                    'final_response' => $response,
                ];
            }
        }

        return [
            'ok' => false,
            'blocked_before_first_hop' => false,
            'missing_fields' => [],
            'attempts' => $attempts,
            'final_response' => null,
        ];
    }
}
