<?php

namespace App\Services\Psp;

use App\DTO\PspPaymentRequest;
use App\Services\Psp\Commercial\PspCommercialProfile;
use App\Services\Psp\Contracts\PspConverterInterface;

final class ConversionKillerChecker
{
    public function checkRequiredFields(PspConverterInterface $converter, PspPaymentRequest $request, string $connection): array
    {
        $results = [];
        foreach ($converter->requiredFields() as $field) {
            $value = $request->get($field);
            $results[] = $this->result(
                $converter->pspCode(),
                $connection,
                "required_fields.{$field}",
                'Required field: ' . $field,
                $field,
                $this->pspFieldFor($field),
                'present value',
                $value,
                'missing requiredField',
                ! ($value === null || $value === ''),
                "The converter declares {$field} as required for this PSP but the internal request did not provide it.",
                'App\Services\Psp\AbstractPspAdaptor::createPayment',
                'blocks cascade',
                "Populate {$field} before routing, or soft-skip this PSP with missing_required_field:{$field}.",
            );
        }

        return $results;
    }

    public function checkDeclaredFieldDependencies(PspConverterInterface $converter): array
    {
        $required = array_flip($converter->requiredFields());
        $results = [];
        foreach ($converter->createPayloadFieldMap() as $internalField => $pspField) {
            $results[] = $this->result(
                $converter->pspCode(),
                'create',
                "declared_dependency.{$internalField}",
                'Declared converter dependency',
                $internalField,
                $pspField,
                'field declared in requiredFields',
                isset($required[$internalField]) ? 'declared' : 'undeclared',
                'missing requiredField',
                isset($required[$internalField]),
                "The converter maps {$internalField} into {$pspField} but did not declare it in requiredFields, so checkout pre-collection would miss it.",
                get_class($converter) . '::createPayloadFieldMap',
                'blocks cascade',
                "Add {$internalField} to requiredFields or remove it from the create payload map.",
            );
        }

        return $results;
    }

    public function checkCascadeAudit(string $pspCode, array $audit): array
    {
        $results = [];
        foreach ($audit as $index => $row) {
            $event = (string) ($row['webhook_or_status_received'] ?? '');
            $outcome = (string) ($row['outcome'] ?? '');
            $results[] = $this->result(
                $pspCode,
                'cascade',
                'cascade.confirmed_failure.' . ($index + 1),
                'Confirmed failure before cascade',
                'cascade.audit.' . $index . '.webhook_or_status_received',
                'webhook/status',
                'webhook_failed or timeout_status_failed before next hop',
                $event,
                'cascaded without confirmed failure',
                ! (($outcome === 'confirmed_failure') && ! in_array($event, ['webhook_failed', 'timeout_status_failed'], true)),
                'The cascade advanced without a failed webhook or timeout status query confirming a final failure.',
                'App\Services\Psp\PspWebhookDrivenCascadeOrchestrator::confirmFailureAndMaybeCascade',
                'blocks cascade',
                'Resume cascade only from a final failed webhook or a timeout status query returning failed/declined.',
            );

            $results[] = $this->result(
                $pspCode,
                'cascade',
                'cascade.webhook_timeout.' . ($index + 1),
                'Webhook received within timeout',
                'cascade.audit.' . $index . '.waited_ms',
                'PSP failure webhook',
                'failure webhook before timeout or final failed status after timeout',
                $outcome,
                'missing webhook within timeout',
                $outcome !== 'awaiting_final_status',
                'No final failure webhook arrived and the timeout status query still did not confirm failure.',
                'App\Services\Psp\PspWebhookDrivenCascadeOrchestrator::handleTimeout',
                'blocks cascade',
                'Keep the order in awaiting_final_status and investigate the missing or delayed PSP webhook.',
            );
        }

        return $results;
    }

    public function checkCommercialProfile(PspCommercialProfile $profile): array
    {
        $results = [];
        $missing = array_flip($profile->missingFields());
        foreach (PspCommercialProfile::REQUIRED_FIELDS as $field) {
            $results[] = $this->result(
                $profile->pspCode(),
                'commercial_profile',
                "commercial_profile.{$field}",
                'Commercial profile completeness',
                "profile.{$field}",
                "psp_profile.{$field}",
                'known non-empty value',
                isset($missing[$field]) ? 'missing_or_unknown' : 'present',
                'commercial profile incomplete',
                ! isset($missing[$field]),
                "The PSP commercial/go-live profile is missing {$field}, so this PSP cannot be safely made go-live eligible.",
                'App\Services\Psp\Commercial\PspCommercialProfile::missingFields',
                'blocks go-live',
                $this->commercialQuestionFor($profile, $field),
            );
        }

        return $results;
    }

    public function checkHardcodedSecrets(string $pspCode, array $sourceFiles): array
    {
        $results = [];
        foreach ($sourceFiles as $path => $contents) {
            $matched = preg_match('/(api[_-]?key|secret|password|token)[^\n\r]{0,40}(sk_live|pk_live|[A-Za-z0-9_\-]{20,})/i', $contents) === 1
                || preg_match('/(sk_live|pk_live|xai-|secret_)[A-Za-z0-9_\\-]+/i', $contents) === 1;
            $results[] = $this->result(
                $pspCode,
                'security',
                'security.no_hardcoded_secret.' . basename($path),
                'No hardcoded PSP secrets',
                $path,
                'adapter/converter source',
                'no key-like literals',
                $matched ? 'key-like literal found' : 'clean',
                'hardcoded credential',
                ! $matched,
                'Adapter/converter source appears to contain a key-like literal.',
                'App\Services\Psp\ConversionKillerChecker::checkHardcodedSecrets',
                'blocks go-live',
                'Move the credential to the 0609 vault and access it through PspCredentialProvider.',
            );
        }

        return $results;
    }

    public function checkNormalizedOutput(string $pspCode, string $connection, array $normalized, ?array $golden = null): array
    {
        $errors = PspNormalizedContract::validate($normalized);
        $amount = $normalized['amount'] ?? [];
        $decline = $normalized['decline'] ?? [];

        $results = [
            $this->result($pspCode, $connection, 'contract.schema', 'Normalized schema', 'normalized', 'normalized', 'valid schema', implode(', ', $errors), 'status enum', $errors === [], 'The adapter returned a payload that does not satisfy the canonical normalized contract.', 'App\Services\Psp\PspNormalizedContract::validate', 'blocks cascade', 'Map the PSP response through the converter and return every required normalized key.'),
            $this->result(
                $pspCode,
                $connection,
                'amount.minor_units',
                'Amount minor units',
                'amount.value',
                'amount_cents',
                isset($amount['value']) ? (string) round(((float) $amount['value']) * 100) : 'unavailable',
                $amount['minor_units'] ?? null,
                'unit/cents',
                isset($amount['value'], $amount['minor_units'])
                    && (int) round(((float) $amount['value']) * 100) === $amount['minor_units'],
                'The normalized amount decimal does not equal the normalized PSP minor-unit amount.',
                'provider converter::normalizeCreatePaymentResponse',
                'blocks cascade',
                'Convert PSP cents/minor units exactly once, and compare decimal value against minor_units.',
            ),
            $this->result(
                $pspCode,
                $connection,
                'currency.iso4217',
                'Currency format',
                'amount.currency',
                'currency',
                '3 uppercase ISO 4217 letters',
                $amount['currency'] ?? null,
                'currency',
                is_string($amount['currency'] ?? null) && preg_match('/^[A-Z]{3}$/', $amount['currency']) === 1,
                'The converter did not uppercase or normalize the PSP currency code.',
                'provider converter::normalizeCreatePaymentResponse',
                'blocks cascade',
                'Normalize currency with strtoupper(trim($currency)) before returning the canonical payload.',
            ),
            $this->result(
                $pspCode,
                $connection,
                'amount.rounding',
                'Amount rounding',
                'amount.value',
                'amount_cents',
                'decimal string with exactly two fraction digits',
                $amount['value'] ?? null,
                'rounding',
                is_string($amount['value'] ?? null) && preg_match('/^-?\d+\.\d{2}$/', $amount['value']) === 1,
                'The converter returned an amount that can drift during string/float comparisons.',
                'App\Services\Psp\PspNormalizedContract::formatAmount',
                'blocks cascade',
                'Return amount.value as a string formatted with number_format(..., 2, ".", "").',
            ),
            $this->result(
                $pspCode,
                $connection,
                'status.enum',
                'Status enum',
                'status',
                'status',
                implode('|', PspNormalizedContract::STATUSES),
                $normalized['status'] ?? null,
                'status enum',
                in_array($normalized['status'] ?? null, PspNormalizedContract::STATUSES, true),
                'The PSP status was not mapped into the canonical Readies status enum.',
                'provider converter::mapStatus',
                'blocks cascade',
                'Add the PSP status to the converter status map and classify decline behavior.',
            ),
            $this->result(
                $pspCode,
                $connection,
                'datetime.utc_iso8601',
                'UTC timestamp format',
                'created_at|updated_at',
                'created_at|updated_at',
                'YYYY-MM-DDTHH:MM:SSZ',
                ($normalized['created_at'] ?? 'missing') . ' / ' . ($normalized['updated_at'] ?? 'missing'),
                'date/timezone',
                $this->timestampsAreUtc($normalized),
                'The converter did not convert PSP timestamps to UTC Zulu format.',
                'App\Services\Psp\PspNormalizedContract::toUtcTimestamp',
                'blocks cascade',
                'Parse the PSP timestamp and return UTC with format Y-m-d\TH:i:s\Z.',
            ),
            $this->result(
                $pspCode,
                $connection,
                'ids.trimmed',
                'ID whitespace/case',
                'payment_id|psp_reference|merchant_reference',
                'id|transaction_id|merchant_ref',
                'trimmed identifiers',
                $this->idSnapshot($normalized),
                'ID case/whitespace',
                $this->idsAreTrimmed($normalized),
                'The converter preserved PSP whitespace around IDs or references.',
                'App\Services\Psp\PspNormalizedContract::blank',
                'blocks cascade',
                'Trim identifiers exactly once in the converter or normalized contract.',
            ),
            $this->result(
                $pspCode,
                $connection,
                'nulls.no_empty_nullable',
                'Null versus empty',
                'decline.* nullable fields',
                'decline_code|decline_message',
                'null for absent nullable fields',
                $this->nullableSnapshot($normalized),
                'null vs empty',
                ! $this->hasEmptyStringWhereNullIsExpected($normalized),
                'The converter returned an empty string where the normalized contract requires null.',
                'App\Services\Psp\PspNormalizedContract::blank',
                'warning',
                'Use null for absent optional identifiers, decline fields, and webhook event data.',
            ),
            $this->result(
                $pspCode,
                $connection,
                'decline.classification',
                'Soft/hard decline classification',
                'decline.class',
                'status|decline_code',
                'none|soft|hard with cascade rules',
                $decline['class'] ?? null,
                'soft/hard decline misclass',
                $this->declineClassificationIsCoherent($decline),
                'The converter did not provide coherent cascade behavior for the PSP decline.',
                'provider converter::declineFor',
                'blocks cascade',
                'Map retryable PSP statuses to soft with cascade_reason, and terminal declines to hard.',
            ),
        ];

        if (($normalized['operation'] ?? null) === 'webhook') {
            $results[] = $this->result(
                $pspCode,
                $connection,
                'webhook.signature_verified',
                'Webhook signature verified',
                'webhook_event.signature_verified',
                'provider signature header',
                true,
                $normalized['webhook_event']['signature_verified'] ?? null,
                'signature/encoding',
                (bool) ($normalized['webhook_event']['signature_verified'] ?? false),
                'The webhook was normalized before its HMAC signature was verified.',
                'App\Services\Psp\AbstractPspAdaptor::verifyWebhook',
                'blocks cascade',
                'Verify the PSP signature over the raw body bytes before normalizing the webhook.',
            );
        }

        if ($golden !== null) {
            $results[] = $this->result(
                $pspCode,
                $connection,
                'golden.exact_match',
                'Golden normalized output',
                'normalized',
                'fixture.golden',
                'exact JSON structure and values',
                $normalized === $golden ? 'exact match' : 'differs',
                'status enum',
                $normalized === $golden,
                'The normalized adapter output no longer matches the approved golden fixture.',
                'App\Services\Psp\PspConformanceGate::runConnection',
                'blocks cascade',
                'Review the converter mapping and update the golden fixture only after human approval.',
            );
        }

        return $results;
    }

    public function failures(array $results): array
    {
        return array_values(array_filter($results, static fn (array $row): bool => $row['passed'] === false));
    }

    public function issueRows(array $results): array
    {
        return array_values(array_filter($results, static fn (array $row): bool => $row['passed'] === false));
    }

    private function result(
        string $pspCode,
        string $connection,
        string $checkId,
        string $checkName,
        string $internalField,
        string $pspField,
        mixed $expected,
        mixed $actual,
        string $category,
        bool $passed,
        string $why,
        string $codeLocation,
        string $severity,
        string $suggestedFix,
    ): array
    {
        return [
            'psp_code' => $pspCode,
            'connection' => $connection,
            'preflight_check_id' => $checkId,
            'preflight_check_name' => $checkName,
            'field_path' => [
                'internal' => $internalField,
                'psp' => $pspField,
            ],
            'expected' => $expected,
            'actual' => $actual,
            'killer_category' => $category,
            'passed' => $passed,
            'why' => $passed ? 'ok' : $why,
            'code_location' => $codeLocation,
            'severity' => $severity,
            'suggested_fix' => $suggestedFix,
        ];
    }

    private function commercialQuestionFor(PspCommercialProfile $profile, string $field): string
    {
        foreach ($profile->openQuestions() as $question) {
            if (($question['field'] ?? null) === $field) {
                return $question['question'];
            }
        }

        return "Confirm {$field} for PSP " . $profile->pspCode() . '.';
    }

    private function pspFieldFor(string $internalField): string
    {
        return match ($internalField) {
            'merchant_reference' => 'merchantRef',
            'amount.value' => 'amountCents',
            'amount.currency' => 'currency',
            'billing.postal_code' => 'customer.billingZip',
            default => $internalField,
        };
    }

    private function idSnapshot(array $normalized): array
    {
        return [
            'merchant_reference' => $normalized['merchant_reference'] ?? null,
            'payment_id' => $normalized['payment_id'] ?? null,
            'psp_reference' => $normalized['psp_reference'] ?? null,
        ];
    }

    private function nullableSnapshot(array $normalized): array
    {
        return [
            'psp_reference' => $normalized['psp_reference'] ?? null,
            'decline_code' => $normalized['decline']['code'] ?? null,
            'decline_message' => $normalized['decline']['message'] ?? null,
            'decline_cascade_reason' => $normalized['decline']['cascade_reason'] ?? null,
            'webhook_event' => $normalized['webhook_event'] ?? null,
        ];
    }

    private function timestampsAreUtc(array $normalized): bool
    {
        foreach (['created_at', 'updated_at'] as $field) {
            if (! is_string($normalized[$field] ?? null) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $normalized[$field]) !== 1) {
                return false;
            }
        }
        if (($normalized['operation'] ?? null) === 'webhook') {
            $receivedAt = $normalized['webhook_event']['received_at'] ?? null;
            return is_string($receivedAt) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $receivedAt) === 1;
        }

        return true;
    }

    private function idsAreTrimmed(array $normalized): bool
    {
        foreach (['psp_code', 'merchant_reference', 'payment_id', 'psp_reference'] as $field) {
            $value = $normalized[$field] ?? null;
            if (is_string($value) && $value !== trim($value)) {
                return false;
            }
        }

        return true;
    }

    private function hasEmptyStringWhereNullIsExpected(array $normalized): bool
    {
        foreach ([
            $normalized['psp_reference'] ?? null,
            $normalized['decline']['code'] ?? null,
            $normalized['decline']['message'] ?? null,
            $normalized['decline']['cascade_reason'] ?? null,
            $normalized['webhook_event'] ?? null,
        ] as $value) {
            if ($value === '') {
                return true;
            }
        }

        return false;
    }

    private function declineClassificationIsCoherent(array $decline): bool
    {
        $class = $decline['class'] ?? null;
        if (! in_array($class, PspNormalizedContract::DECLINE_CLASSES, true)) {
            return false;
        }
        if ($class === 'soft') {
            return is_string($decline['cascade_reason'] ?? null) && $decline['cascade_reason'] !== '';
        }
        if ($class === 'none') {
            return ($decline['code'] ?? null) === null && ($decline['message'] ?? null) === null;
        }

        return true;
    }
}
