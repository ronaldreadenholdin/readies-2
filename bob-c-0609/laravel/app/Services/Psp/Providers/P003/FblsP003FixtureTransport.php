<?php

namespace App\Services\Psp\Providers\P003;

use App\DTO\PspPaymentRequest;
use App\DTO\PspRefundRequest;
use App\Services\Psp\Contracts\PspFixtureSetInterface;
use RuntimeException;

final class FblsP003FixtureTransport implements PspFixtureSetInterface
{
    public function __construct(private string $fixtureRoot)
    {
    }

    public static function default(): self
    {
        return new self(dirname(__DIR__, 5) . '/resources/psp-conformance/p003');
    }

    public function __invoke(string $operation, array $payload): array
    {
        return match ($operation) {
            'create_payment' => $this->readJson('psp/create-payment-response.json'),
            'payment_status' => $this->readJson('psp/status-response.json'),
            'refund' => $this->readJson('psp/refund-response.json'),
            default => throw new RuntimeException("No P003 fixture response for {$operation}."),
        };
    }

    public function paymentRequest(): PspPaymentRequest
    {
        return PspPaymentRequest::fromArray($this->readJson('internal/payment-request.json'));
    }

    public function refundRequest(): PspRefundRequest
    {
        return PspRefundRequest::fromArray($this->readJson('internal/refund-request.json'));
    }

    public function webhookPayload(): string
    {
        return $this->readRaw('psp/webhook.json');
    }

    public function webhookHeaders(): array
    {
        $payload = $this->webhookPayload();
        $secret = $this->webhookSecret();

        return ['X-FBLS-Signature' => hash_hmac('sha256', $payload, $secret)];
    }

    public function webhookSecret(): string
    {
        return 'test_secret';
    }

    public function golden(string $connection): array
    {
        return $this->readJson("golden/{$connection}.json");
    }

    public function preflightChecks(): array
    {
        return $this->readJson('preflight-checks.json');
    }

    public function commercialProfile(): array
    {
        return $this->readJson('commercial-profile.json');
    }

    private function readJson(string $relativePath): array
    {
        $decoded = json_decode($this->readRaw($relativePath), true);
        if (! is_array($decoded)) {
            throw new RuntimeException("Fixture {$relativePath} is not JSON.");
        }

        return $decoded;
    }

    private function readRaw(string $relativePath): string
    {
        $path = rtrim($this->fixtureRoot, '/') . '/' . ltrim($relativePath, '/');
        $raw = is_file($path) ? file_get_contents($path) : false;
        if (! is_string($raw)) {
            throw new RuntimeException("Missing P003 fixture: {$path}");
        }

        return $raw;
    }
}
