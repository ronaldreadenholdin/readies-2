<?php

namespace App\Services\Psp\Preflight;

final class PspV2PreflightDefinition implements PspV2PreflightInterface
{
    public const FIXTURE_CATEGORIES = [
        'success',
        'soft_decline',
        'hard_decline',
        'three_ds',
        'pending',
        'unknown',
        'bad_signature',
        'amount_mismatch',
        'timeout',
        'malformed',
        'duplicate_webhook',
        'refund',
    ];

    public const FAIL_CLOSED_RULES = [
        'ADP-01:v2-R1',
        'ADP-01:v2-R2',
        'ADP-01:v2-R3',
        'ADP-01:v2-R4',
        'ADP-01:v2-R5',
        'ADP-01:v2-R6',
        'ADP-01:v2-R7',
        'ADP-01:v2-R8',
    ];

    public function evaluate(array $evidence): array
    {
        $missing = [];

        foreach (self::FIXTURE_CATEGORIES as $category) {
            if (empty($evidence['fixtures'][$category])) {
                $missing[] = 'fixtures.' . $category;
            }
        }

        foreach (self::FAIL_CLOSED_RULES as $ruleId) {
            if (empty($evidence['rule_tests'][$ruleId])) {
                $missing[] = 'rule_tests.' . $ruleId;
            }
        }

        if (empty($evidence['sandbox_url_configured']) || empty($evidence['sandbox_differs_from_production'])) {
            $missing[] = 'sandbox.production_separation';
        }
        if (empty($evidence['converter_committed'])) {
            $missing[] = 'converter_committed';
        }
        if (empty($evidence['masker_log_leak_tests_pass'])) {
            $missing[] = 'masker_log_leak_tests_pass';
        }
        if (empty($evidence['owner_go'])) {
            $missing[] = 'owner_go';
        }

        return [
            'score_percent' => $missing === [] ? 100 : 0,
            'green' => $missing === [],
            'missing' => $missing,
        ];
    }
}
