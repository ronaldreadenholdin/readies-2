<?php

namespace App\Services\Psp\Contracts\V2;

final class PaymentPageResult
{
    public const SCHEMA_VERSION = 'ADP-01:v2';

    /**
     * @param array<string, string> $formFields
     */
    public function __construct(
        public string $paymentId,
        public string $attemptId,
        public string $displayStatus,
        public string $nextAction,
        public ?string $redirectUrl,
        public ?string $redirectMethod,
        public array $formFields,
        public bool $threeDs,
        public string $messageCode,
        public string $schemaVersion = self::SCHEMA_VERSION,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            (string) ($payload['payment_id'] ?? ''),
            (string) ($payload['attempt_id'] ?? ''),
            (string) ($payload['display_status'] ?? ''),
            (string) ($payload['next_action'] ?? ''),
            isset($payload['redirect_url']) ? (string) $payload['redirect_url'] : null,
            isset($payload['redirect_method']) ? (string) $payload['redirect_method'] : null,
            is_array($payload['form_fields'] ?? null) ? $payload['form_fields'] : [],
            (bool) ($payload['three_ds'] ?? false),
            (string) ($payload['message_code'] ?? ''),
            (string) ($payload['schema_version'] ?? self::SCHEMA_VERSION),
        );
    }

    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'payment_id' => $this->paymentId,
            'attempt_id' => $this->attemptId,
            'display_status' => $this->displayStatus,
            'next_action' => $this->nextAction,
            'redirect_url' => $this->redirectUrl,
            'redirect_method' => $this->redirectMethod,
            'form_fields' => $this->formFields,
            'three_ds' => $this->threeDs,
            'message_code' => $this->messageCode,
        ];
    }
}
