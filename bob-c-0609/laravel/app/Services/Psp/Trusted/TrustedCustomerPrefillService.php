<?php

namespace App\Services\Psp\Trusted;

final class TrustedCustomerPrefillService
{
    public const FORBIDDEN_FIELDS = [
        'card.pan',
        'card.cvv',
        'card.full_number',
        'card.number',
        'card.expiry',
        'card.expiry_month',
        'card.expiry_year',
    ];

    public function __construct(
        private TrustedCustomerRepositoryInterface $customers,
        private PersonalDataEncryptionInterface $encryption,
    ) {
    }

    public function stableKeyForEmail(string $merchantId, string $email): string
    {
        return hash('sha256', strtolower(trim($merchantId)) . '|' . strtolower(trim($email)));
    }

    public function prefill(string $stableCustomerKey, array $requiredFields): array
    {
        $customer = $this->customers->findByStableKey($stableCustomerKey);
        if ($customer === null || ($customer['consent_recorded'] ?? false) !== true) {
            return [
                'values' => [],
                'audit' => [
                    'prefilled_fields' => [],
                    'prefill_source' => null,
                ],
            ];
        }

        $values = [];
        foreach ($requiredFields as $field) {
            if (in_array($field, self::FORBIDDEN_FIELDS, true)) {
                continue;
            }
            if (! array_key_exists($field, $customer['encrypted_fields'] ?? [])) {
                continue;
            }
            $values[$field] = $this->encryption->decrypt((string) $customer['encrypted_fields'][$field]);
        }

        return [
            'values' => $values,
            'audit' => [
                'prefilled_fields' => array_keys($values),
                'prefill_source' => 'trusted_customer',
                'trusted_since' => $customer['trusted_since'] ?? null,
                'last_seen' => $customer['last_seen'] ?? null,
                'consent_recorded' => true,
                'editable_by_customer' => true,
            ],
        ];
    }
}
