<?php

namespace App\Services\Psp\Commercial;

final class PspCommercialProfile
{
    public const REQUIRED_FIELDS = [
        'psp_code',
        'allowed_geos',
        'blocked_countries',
        'accepted_card_brands',
        'accepted_card_types',
        'three_ds_required',
        'allowed_verticals',
        'blocked_verticals',
        'min_amount',
        'max_amount',
        'processing_currencies',
        'settlement_currencies',
        'mdr_percent',
        'mdr_fixed',
        'other_fees',
        'settlement_days',
        'rolling_reserve_percent',
        'rolling_reserve_days',
        'cap_amount',
        'cap_period',
        'api_docs_received',
        'sandbox_keys_status',
        'live_keys_status',
        'signed_webhook_sample_received',
        'decline_code_map_received',
        'agreement_signed',
    ];

    public function __construct(private array $profile)
    {
    }

    public function data(): array
    {
        return $this->profile;
    }

    public function pspCode(): string
    {
        return strtoupper((string) ($this->profile['psp_code'] ?? ''));
    }

    public function missingFields(): array
    {
        $missing = [];
        foreach (self::REQUIRED_FIELDS as $field) {
            $value = $this->profile[$field] ?? null;
            if ($value === null || $value === '' || $value === [] || $value === 'unknown') {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    public function openQuestions(): array
    {
        return array_map(function (string $field): array {
            return [
                'field' => $field,
                'question' => $this->questionFor($field),
                'severity' => 'blocks go-live',
            ];
        }, $this->missingFields());
    }

    private function questionFor(string $field): string
    {
        return match ($field) {
            'allowed_geos' => 'Which countries or regions may this PSP process for this merchant?',
            'blocked_countries' => 'Which countries are explicitly blocked by this PSP?',
            'accepted_card_brands' => 'Which card brands are accepted by this PSP?',
            'accepted_card_types' => 'Which card types are accepted, and is 3DS required?',
            'allowed_verticals' => 'Which merchant verticals are allowed?',
            'blocked_verticals' => 'Which merchant verticals are blocked?',
            'min_amount' => 'What is the minimum processable amount per currency?',
            'max_amount' => 'What is the maximum processable amount per currency?',
            'processing_currencies' => 'Which processing currencies are supported?',
            'settlement_currencies' => 'Which settlement currencies are supported?',
            'mdr_percent' => 'What percentage MDR applies?',
            'mdr_fixed' => 'What fixed MDR fee applies per transaction?',
            'other_fees' => 'Which other fees apply?',
            'settlement_days' => 'What is the settlement timing, for example T+2?',
            'rolling_reserve_percent' => 'What rolling reserve percentage applies?',
            'rolling_reserve_days' => 'How many days is rolling reserve held?',
            'cap_amount' => 'What cap amount applies?',
            'cap_period' => 'What period does the cap apply to?',
            'api_docs_received' => 'Have complete API documents been received?',
            'sandbox_keys_status' => 'What is the sandbox key status? Do not send secret values.',
            'live_keys_status' => 'What is the live key status? Do not send secret values.',
            'signed_webhook_sample_received' => 'Has a signed webhook sample been received?',
            'decline_code_map_received' => 'Has the PSP decline-code map been received?',
            'agreement_signed' => 'Has the PSP agreement been signed?',
            default => "Please provide {$field} for this PSP profile.",
        };
    }
}
