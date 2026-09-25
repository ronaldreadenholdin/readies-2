<?php

namespace App\Services\Psp;

use App\Contracts\PspAdaptorInterface;
use App\DTO\PspPaymentRequest;
use App\DTO\PspPaymentResponse;
use App\DTO\PspRefundRequest;
use App\DTO\PspRefundResponse;
use App\DTO\PspWebhookResult;
use App\Services\Psp\Contracts\PspConverterInterface;
use App\Services\Psp\Credentials\FakePspCredentialProvider;
use App\Services\Psp\Credentials\PspCredentialProviderInterface;
use RuntimeException;

abstract class AbstractPspAdaptor implements PspAdaptorInterface
{
    public function __construct(
        protected PspConverterInterface $converter,
        private mixed $transport,
        protected ?PspCredentialProviderInterface $credentials = null,
    ) {
        $this->credentials ??= new FakePspCredentialProvider();
    }

    public function code(): string
    {
        return $this->converter->pspCode();
    }

    public function createPayment(PspPaymentRequest $request): PspPaymentResponse
    {
        $missing = $this->firstMissingRequiredField($request);
        if ($missing !== null) {
            $amount = PspNormalizedContract::amountFromDecimal(
                (string) ($request->get('amount.value', '0.00')),
                (string) ($request->get('amount.currency', 'EUR')),
            );

            return PspPaymentResponse::fromNormalized(PspNormalizedContract::softSkip(
                $this->code(),
                $missing,
                $amount,
                (string) $request->get('merchant_reference', 'missing-merchant-reference'),
            ));
        }

        $payload = $this->converter->toCreatePaymentPayload($request);
        $response = $this->send('create_payment', $payload);

        return PspPaymentResponse::fromNormalized($this->converter->normalizeCreatePaymentResponse($response, $request));
    }

    public function getPaymentStatus(string $paymentId): PspPaymentResponse
    {
        $response = $this->send('payment_status', ['payment_id' => trim($paymentId)]);

        return PspPaymentResponse::fromNormalized($this->converter->normalizeStatusResponse($response));
    }

    public function refund(PspRefundRequest $request): PspRefundResponse
    {
        $payload = $this->converter->toRefundPayload($request);
        $response = $this->send('refund', $payload);

        return PspRefundResponse::fromNormalized($this->converter->normalizeRefundResponse($response, $request));
    }

    public function verifyWebhook(array $headers, string $payload): bool
    {
        $secret = (string) (($this->credentials->get($this->code(), 'sandbox')['webhook_secret'] ?? null) ?: '');
        if ($secret === '') {
            return false;
        }
        $signature = (string) ($headers['X-FBLS-Signature'] ?? $headers['x-fbls-signature'] ?? '');
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    public function credentialStatus(string $environment): array
    {
        return $this->credentials->status($this->code(), $environment);
    }

    public function handleWebhook(array $headers, string $payload): PspWebhookResult
    {
        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Webhook payload must be valid JSON.');
        }

        return PspWebhookResult::fromNormalized($this->converter->normalizeWebhookEvent(
            $decoded,
            $this->verifyWebhook($headers, $payload),
        ));
    }

    private function firstMissingRequiredField(PspPaymentRequest $request): ?string
    {
        foreach ($this->converter->requiredFields() as $field) {
            $value = $request->get($field);
            if ($value === null || $value === '') {
                return $field;
            }
        }

        return null;
    }

    private function send(string $operation, array $payload): array
    {
        $transport = $this->transport;
        $response = $transport($operation, $payload);
        if (! is_array($response)) {
            throw new RuntimeException("PSP transport for {$this->code()} returned a non-array response.");
        }

        return $response;
    }
}
