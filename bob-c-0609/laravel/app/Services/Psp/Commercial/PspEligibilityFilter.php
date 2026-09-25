<?php

namespace App\Services\Psp\Commercial;

final class PspEligibilityFilter
{
    public function filter(array $orderedPspCodes, array $profilesByCode, array $context): array
    {
        $eligible = [];
        $audit = [];

        foreach ($orderedPspCodes as $code) {
            $code = strtoupper((string) $code);
            $profile = $profilesByCode[$code] ?? [];
            $reason = $this->firstIneligibleReason($profile, $context);
            if ($reason === null) {
                $eligible[] = $code;
                continue;
            }

            $audit[] = [
                'psp_code' => $code,
                'connection' => 'eligibility',
                'attempted_at' => gmdate('Y-m-d\TH:i:s\Z'),
                'webhook_or_status_received' => null,
                'waited_ms' => 0,
                'cascade_reason' => 'ineligible:' . $reason,
                'outcome' => 'skipped_not_attempted',
            ];
        }

        return [
            'eligible_psps' => $eligible,
            'audit' => $audit,
        ];
    }

    private function firstIneligibleReason(array $profile, array $context): ?string
    {
        foreach (['billing_country', 'bin_country'] as $field) {
            $country = strtoupper((string) ($context[$field] ?? ''));
            if ($country !== '' && in_array($country, array_map('strtoupper', $profile['blocked_countries'] ?? []), true)) {
                return $field;
            }
            $allowedGeos = array_map('strtoupper', $profile['allowed_geos'] ?? []);
            if ($country !== '' && $allowedGeos !== [] && ! in_array($country, $allowedGeos, true) && ! in_array('GLOBAL', $allowedGeos, true)) {
                return $field;
            }
        }

        if (! $this->allows($profile['accepted_card_brands'] ?? [], $context['card_brand'] ?? null)) {
            return 'card_brand';
        }
        if (! $this->allows($profile['accepted_card_types'] ?? [], $context['card_type'] ?? null)) {
            return 'card_type';
        }
        $vertical = (string) ($context['merchant_vertical'] ?? '');
        if ($vertical !== '' && in_array($vertical, $profile['blocked_verticals'] ?? [], true)) {
            return 'merchant_vertical';
        }
        if (($profile['allowed_verticals'] ?? []) !== [] && ! in_array($vertical, $profile['allowed_verticals'], true)) {
            return 'merchant_vertical';
        }

        $currency = strtoupper((string) ($context['currency'] ?? ''));
        if (! $this->allows($profile['processing_currencies'] ?? [], $currency)) {
            return 'currency';
        }

        $amount = (float) ($context['amount'] ?? 0);
        $min = $profile['min_amount'][$currency] ?? null;
        $max = $profile['max_amount'][$currency] ?? null;
        if ($min !== null && $amount < (float) $min) {
            return 'amount';
        }
        if ($max !== null && $amount > (float) $max) {
            return 'amount';
        }

        return null;
    }

    private function allows(array $allowed, mixed $value): bool
    {
        if ($allowed === [] || $value === null || $value === '') {
            return true;
        }

        return in_array(strtoupper((string) $value), array_map('strtoupper', $allowed), true);
    }
}
