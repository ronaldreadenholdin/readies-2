<?php

declare(strict_types=1);

namespace AfrPay\Services;

final class AfrPayAdaptor
{
    public function __construct(
        private readonly string $code,
        private readonly array $config
    ) {
    }

    public function createPayment(array $input): array
    {
        GoLiveGate::assertMayCall($this->code, $this->config);

        $payload = [
            'merchant_reference' => $input['merchant_reference'],
            'customer_reference' => $input['customer_reference'],
            'amount' => [
                'value' => (int) $input['amount_minor'],
                'currency' => strtoupper((string) $input['currency']),
            ],
            'success_url' => $input['success_url'],
            'failure_url' => $input['failure_url'],
            'customer' => $input['customer'] ?? [],
            'metadata' => array_merge($input['metadata'] ?? [], [
                'connection_code' => $this->code,
                'readies_flow' => $this->code === 'OR001' ? 'card_to_usdt_or_usdc_to_readies' : 'open_banking',
                'readies_rate_eur' => '0.10',
            ]),
        ];

        if ($this->code === 'OR001') {
            $payload['target_asset'] = $input['metadata']['target_asset'] ?? 'USDT';
            $payload['target_wallet_reference'] = $input['metadata']['exchange_wallet_reference'] ?? null;
        }
        if ($this->code === 'OB003') {
            $payload['bank_id'] = $input['metadata']['bank_id'] ?? null;
        }

        $result = $this->call(
            'POST',
            (string) $this->config['create_path'],
            $payload,
            ['Idempotency-Key' => (string) $input['idempotency_key']]
        );

        return $this->mapPaymentResult($result);
    }

    public function paymentStatus(string $providerReference): array
    {
        GoLiveGate::assertMayCall($this->code, $this->config);

        $path = str_replace('{reference}', rawurlencode($providerReference), (string) $this->config['status_path']);
        $result = $this->call('GET', $path, null);

        return $this->mapPaymentResult($result, $providerReference);
    }

    public function refund(array $input): array
    {
        GoLiveGate::assertMayCall($this->code, $this->config);

        $payload = [
            'provider_reference' => $input['provider_reference'],
            'amount' => (int) $input['amount_minor'],
            'currency' => strtoupper((string) $input['currency']),
            'reason' => $input['metadata']['reason'] ?? 'merchant_refund',
            'metadata' => $input['metadata'] ?? [],
        ];

        $result = $this->call(
            'POST',
            (string) $this->config['refund_path'],
            $payload,
            ['Idempotency-Key' => (string) $input['idempotency_key']]
        );

        $body = $result['json'];
        $status = $this->mapStatus((string) $this->pick($body, ['status', 'state', 'data.status'], $result['ok'] ? 'pending' : 'failed'));

        if (! $result['ok']) {
            return [
                'status' => 'failed',
                'provider_reference' => $input['provider_reference'],
                'failure_code' => (string) $this->pick($body, ['error_code', 'code'], 'HTTP_'.$result['status']),
                'failure_message' => (string) $this->pick($body, ['error_message', 'message'], 'AfrPay refund failed.'),
                'raw' => $result,
            ];
        }

        return [
            'status' => $status,
            'provider_reference' => (string) $this->pick($body, ['refund_id', 'id', 'data.id'], $input['provider_reference']),
            'failure_code' => null,
            'failure_message' => null,
            'raw' => $result,
        ];
    }

    public function handleWebhook(string $raw, array $headers): array
    {
        if (! $this->verifyWebhook($raw, $headers)) {
            return [
                'event' => 'signature_failed',
                'mapped_status' => 'rejected',
                'provider_reference' => null,
                'merchant_reference' => null,
                'raw' => ['payload' => $raw],
            ];
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            $payload = [];
        }

        return [
            'event' => (string) $this->pick($payload, ['event', 'event_type', 'type'], 'unknown'),
            'mapped_status' => $this->mapStatus((string) $this->pick($payload, ['status', 'state', 'payment_status', 'consent_status', 'data.status'], 'unknown')),
            'provider_reference' => $this->pick($payload, ['transaction_id', 'payment_id', 'consent_id', 'id', 'data.transaction_id', 'data.payment_id']),
            'merchant_reference' => $this->pick($payload, ['merchant_reference', 'merchant_ref', 'data.merchant_reference']),
            'raw' => $payload,
        ];
    }

    public function verifyWebhook(string $raw, array $headers): bool
    {
        $secret = (string) ($this->config['webhook_secret'] ?? '');
        $headerName = (string) ($this->config['signature_header'] ?? 'X-AfrPay-Signature');
        $signature = $this->header($headers, $headerName);
        if ($secret === '' || $signature === '') {
            return false;
        }
        $computed = hash_hmac('sha256', $raw, $secret);

        return hash_equals($computed, $signature) || hash_equals('sha256='.$computed, $signature);
    }

    private function call(string $method, string $path, ?array $payload, array $extraHeaders = []): array
    {
        $base = rtrim((string) $this->config['base_url'], '/');
        if ($base === '') {
            throw new \RuntimeException($this->code.' base_url is not configured.');
        }
        $apiKey = (string) $this->config['api_key'];
        if ($apiKey === '') {
            throw new \RuntimeException($this->code.' api_key is not configured.');
        }

        $authScheme = strtolower((string) ($this->config['auth_scheme'] ?? 'bearer'));
        $auth = match ($authScheme) {
            'x-api-key' => ['X-Api-Key' => $apiKey],
            'api-key' => ['Api-Key' => $apiKey],
            default => ['Authorization' => 'Bearer '.$apiKey],
        };

        $headers = array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], $auth, $extraHeaders);

        $url = $base.'/'.ltrim($path, '/');

        return HttpClient::request(
            $method,
            $url,
            $headers,
            $payload,
            (int) ($this->config['timeout_seconds'] ?? 30)
        );
    }

    private function mapPaymentResult(array $result, ?string $fallbackRef = null): array
    {
        $body = $result['json'];
        $providerReference = (string) $this->pick($body, [
            'transaction_id', 'payment_id', 'consent_id', 'id', 'reference',
            'data.transaction_id', 'data.payment_id', 'data.id',
        ], $fallbackRef ?? '');
        $redirectUrl = $this->pick($body, [
            'redirect_url', 'checkout_url', 'payment_url', 'authorization_url', '3ds_url',
            'data.redirect_url', 'data.checkout_url', 'data.authorization_url',
        ]);
        $providerStatus = (string) $this->pick($body, [
            'status', 'state', 'payment_status', 'consent_status', 'data.status',
        ], $result['ok'] ? 'pending' : 'failed');

        if (! $result['ok']) {
            return [
                'status' => 'failed',
                'provider_reference' => $providerReference !== '' ? $providerReference : null,
                'redirect_url' => is_string($redirectUrl) ? $redirectUrl : null,
                'failure_code' => (string) $this->pick($body, ['error_code', 'code', 'error.code'], 'HTTP_'.$result['status']),
                'failure_message' => (string) $this->pick($body, ['error_message', 'message', 'error.message'], 'AfrPay request failed.'),
                'raw' => $result,
            ];
        }

        return [
            'status' => $this->mapStatus($providerStatus),
            'provider_reference' => $providerReference !== '' ? $providerReference : null,
            'redirect_url' => is_string($redirectUrl) ? $redirectUrl : null,
            'failure_code' => null,
            'failure_message' => null,
            'raw' => $result,
        ];
    }

    private function mapStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'created', 'initiated', 'new', 'queued' => 'created',
            'pending', 'processing', 'authorized', 'requires_action', 'requires_3ds', 'awaiting_payment', 'in_progress' => 'pending',
            'paid', 'completed', 'success', 'succeeded', 'settled', 'captured' => 'completed',
            'failed', 'declined', 'rejected', 'cancelled', 'canceled', 'expired', 'error' => 'failed',
            'refunded', 'reversed', 'chargeback', 'disputed' => 'reversed',
            default => 'unknown',
        };
    }

    private function pick(array $data, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                return $data[$key];
            }
            if (! str_contains((string) $key, '.')) {
                continue;
            }
            $cursor = $data;
            $found = true;
            foreach (explode('.', (string) $key) as $segment) {
                if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                    $found = false;
                    break;
                }
                $cursor = $cursor[$segment];
            }
            if ($found && $cursor !== null && $cursor !== '') {
                return $cursor;
            }
        }

        return $default;
    }

    private function header(array $headers, string $name): string
    {
        $name = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $name) {
                return is_array($value) ? (string) reset($value) : (string) $value;
            }
        }

        return '';
    }
}
