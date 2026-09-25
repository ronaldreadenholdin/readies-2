<?php

namespace App\Services\Psp;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class PspNormalizedContract
{
    public const SCHEMA_VERSION = 'ADP-01:v1';

    public const OPERATIONS = [
        'create_payment',
        'payment_status',
        'refund',
        'webhook',
    ];

    public const STATUSES = [
        'pending',
        'authorized',
        'captured',
        'settled',
        'failed',
        'declined',
        'cancelled',
        'refunded',
        'skipped',
        'unknown',
    ];

    public const DECLINE_CLASSES = [
        'none',
        'soft',
        'hard',
    ];

    public static function blank(
        string $pspCode,
        string $operation,
        string $merchantReference,
        string $paymentId,
        ?string $pspReference,
        string $status,
        string $amountValue,
        string $currency,
        int $minorUnits,
        string $createdAt,
        string $updatedAt,
        array $decline = [],
        ?array $webhookEvent = null,
        array $metadata = [],
    ): array {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'psp_code' => $pspCode,
            'operation' => $operation,
            'merchant_reference' => trim($merchantReference),
            'payment_id' => trim($paymentId),
            'psp_reference' => $pspReference === null ? null : trim($pspReference),
            'status' => $status,
            'amount' => [
                'value' => self::formatAmount($amountValue),
                'currency' => strtoupper(trim($currency)),
                'minor_units' => $minorUnits,
            ],
            'created_at' => self::toUtcTimestamp($createdAt),
            'updated_at' => self::toUtcTimestamp($updatedAt),
            'decline' => [
                'class' => $decline['class'] ?? 'none',
                'code' => $decline['code'] ?? null,
                'message' => $decline['message'] ?? null,
                'cascade_reason' => $decline['cascade_reason'] ?? null,
            ],
            'webhook_event' => $webhookEvent,
            'metadata' => [
                'cascade_eligible' => (bool) ($metadata['cascade_eligible'] ?? true),
            ],
        ];
    }

    public static function softSkip(string $pspCode, string $field, PspAmount $amount, string $merchantReference): array
    {
        $now = '1970-01-01T00:00:00Z';

        return self::blank(
            $pspCode,
            'create_payment',
            $merchantReference,
            'missing-required-field',
            null,
            'skipped',
            $amount->value,
            $amount->currency,
            $amount->minorUnits,
            $now,
            $now,
            [
                'class' => 'soft',
                'code' => 'MISSING_REQUIRED_FIELD',
                'message' => "Missing required field: {$field}",
                'cascade_reason' => "missing_required_field:{$field}",
            ],
            null,
            ['cascade_eligible' => true],
        );
    }

    /**
     * @return list<string>
     */
    public static function validate(array $payload): array
    {
        $errors = [];
        foreach ([
            'schema_version',
            'psp_code',
            'operation',
            'merchant_reference',
            'payment_id',
            'psp_reference',
            'status',
            'amount',
            'created_at',
            'updated_at',
            'decline',
            'webhook_event',
            'metadata',
        ] as $field) {
            if (! array_key_exists($field, $payload)) {
                $errors[] = "missing:{$field}";
            }
        }

        if (($payload['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            $errors[] = 'schema_version';
        }
        if (! in_array($payload['operation'] ?? null, self::OPERATIONS, true)) {
            $errors[] = 'operation';
        }
        if (! in_array($payload['status'] ?? null, self::STATUSES, true)) {
            $errors[] = 'status';
        }
        if (! is_array($payload['amount'] ?? null)) {
            $errors[] = 'amount';
        } else {
            $amount = $payload['amount'];
            if (! is_string($amount['value'] ?? null) || ! preg_match('/^-?\d+\.\d{2}$/', $amount['value'])) {
                $errors[] = 'amount.value';
            }
            if (! is_string($amount['currency'] ?? null) || ! preg_match('/^[A-Z]{3}$/', $amount['currency'])) {
                $errors[] = 'amount.currency';
            }
            if (! is_int($amount['minor_units'] ?? null)) {
                $errors[] = 'amount.minor_units';
            }
        }
        foreach (['created_at', 'updated_at'] as $field) {
            if (! is_string($payload[$field] ?? null) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $payload[$field])) {
                $errors[] = $field;
            }
        }
        if (! is_array($payload['decline'] ?? null)) {
            $errors[] = 'decline';
        } else {
            $decline = $payload['decline'];
            if (! in_array($decline['class'] ?? null, self::DECLINE_CLASSES, true)) {
                $errors[] = 'decline.class';
            }
            if (($decline['class'] ?? 'none') === 'soft' && empty($decline['cascade_reason'])) {
                $errors[] = 'decline.cascade_reason';
            }
        }
        if (! is_array($payload['metadata'] ?? null) || ! is_bool($payload['metadata']['cascade_eligible'] ?? null)) {
            $errors[] = 'metadata.cascade_eligible';
        }
        if (($payload['operation'] ?? null) === 'webhook') {
            if (! is_array($payload['webhook_event'] ?? null)) {
                $errors[] = 'webhook_event';
            } elseif (! is_bool($payload['webhook_event']['signature_verified'] ?? null)) {
                $errors[] = 'webhook_event.signature_verified';
            }
        }

        return array_values(array_unique($errors));
    }

    public static function amountFromMinorUnits(int $minorUnits, string $currency): PspAmount
    {
        return new PspAmount(number_format($minorUnits / 100, 2, '.', ''), strtoupper($currency), $minorUnits);
    }

    public static function amountFromDecimal(string|int|float $value, string $currency): PspAmount
    {
        $formatted = self::formatAmount((string) $value);

        return new PspAmount($formatted, strtoupper(trim($currency)), (int) round(((float) $formatted) * 100));
    }

    public static function formatAmount(string $value): string
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Invalid amount value: {$value}");
        }

        return number_format((float) $value, 2, '.', '');
    }

    public static function toUtcTimestamp(string $timestamp): string
    {
        $date = new DateTimeImmutable($timestamp);

        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}

final class PspAmount
{
    public function __construct(
        public string $value,
        public string $currency,
        public int $minorUnits,
    ) {
    }
}
