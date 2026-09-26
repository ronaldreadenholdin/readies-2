<?php

namespace App\Services\Psp\Contracts\V2;

final class PaymentEvent
{
    public const SCHEMA_VERSION = 'ADP-01:v2';

    public function __construct(
        public string $eventId,
        public string $pspCode,
        public string $merchantAccountId,
        public string $paymentId,
        public string $attemptId,
        public ?string $pspReference,
        public string $source,
        public string $status,
        public string $declineClass,
        public ?string $errorReason,
        public PaymentAmount $amount,
        public string $rawCode,
        public ?string $rawMessage,
        public string $normalizedReason,
        public bool $signatureVerified,
        public string $receivedAt,
        public ?string $pspTimestamp,
        public ?string $cascadeReason = null,
        public string $schemaVersion = self::SCHEMA_VERSION,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            (string) ($payload['event_id'] ?? ''),
            (string) ($payload['psp_code'] ?? ''),
            (string) ($payload['merchant_account_id'] ?? ''),
            (string) ($payload['payment_id'] ?? ''),
            (string) ($payload['attempt_id'] ?? ''),
            isset($payload['psp_reference']) ? (string) $payload['psp_reference'] : null,
            (string) ($payload['source'] ?? ''),
            (string) ($payload['status'] ?? ''),
            (string) ($payload['decline_class'] ?? ''),
            isset($payload['error_reason']) ? (string) $payload['error_reason'] : null,
            PaymentAmount::fromArray(is_array($payload['amount'] ?? null) ? $payload['amount'] : []),
            (string) ($payload['raw_code'] ?? ''),
            isset($payload['raw_message']) ? (string) $payload['raw_message'] : null,
            (string) ($payload['normalized_reason'] ?? ''),
            (bool) ($payload['signature_verified'] ?? false),
            (string) ($payload['received_at'] ?? ''),
            isset($payload['psp_timestamp']) ? (string) $payload['psp_timestamp'] : null,
            isset($payload['cascade_reason']) ? (string) $payload['cascade_reason'] : null,
            (string) ($payload['schema_version'] ?? self::SCHEMA_VERSION),
        );
    }

    public static function eventIdFor(string $pspCode, ?string $pspReference, string $status, string $source): string
    {
        return hash('sha256', $pspCode . '|' . ($pspReference ?? '') . '|' . $status . '|' . $source);
    }

    public function expectedEventId(): string
    {
        return self::eventIdFor($this->pspCode, $this->pspReference, $this->status, $this->source);
    }

    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'event_id' => $this->eventId,
            'psp_code' => $this->pspCode,
            'merchant_account_id' => $this->merchantAccountId,
            'payment_id' => $this->paymentId,
            'attempt_id' => $this->attemptId,
            'psp_reference' => $this->pspReference,
            'source' => $this->source,
            'status' => $this->status,
            'decline_class' => $this->declineClass,
            'error_reason' => $this->errorReason,
            'amount' => $this->amount->toArray(),
            'raw_code' => $this->rawCode,
            'raw_message' => $this->rawMessage,
            'normalized_reason' => $this->normalizedReason,
            'signature_verified' => $this->signatureVerified,
            'received_at' => $this->receivedAt,
            'psp_timestamp' => $this->pspTimestamp,
            'cascade_reason' => $this->cascadeReason,
        ];
    }
}
