<?php

namespace App\Services\Psp\Adaptors;

use App\Contracts\PspAdaptorInterface;
use App\Services\Psp\AfrPayGoLiveGate;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class AbstractPspAdaptor implements PspAdaptorInterface
{
    public function __construct(protected readonly array $config)
    {
    }

    public function verifyWebhook(string $rawPayload, array $headers): bool
    {
        $secret = (string) ($this->config['webhook_secret'] ?? '');
        $signatureHeader = strtolower((string) ($this->config['signature_header'] ?? 'x-afrpay-signature'));
        $signature = $this->header($headers, $signatureHeader);

        if ($secret === '' || $signature === '') {
            return false;
        }

        $computed = hash_hmac('sha256', $rawPayload, $secret);

        return hash_equals($computed, $signature)
            || hash_equals('sha256='.$computed, $signature);
    }

    protected function assertLiveAllowed(): void
    {
        AfrPayGoLiveGate::assertConnectionMayCallProvider($this->code(), $this->config);
    }

    protected function endpoint(string $path, array $replacements = []): string
    {
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? ''), '/');

        if ($baseUrl === '') {
            throw new RuntimeException(sprintf('%s base_url is not configured.', $this->code()));
        }

        foreach ($replacements as $key => $value) {
            $path = str_replace('{'.$key.'}', rawurlencode((string) $value), $path);
        }

        return $baseUrl.'/'.ltrim($path, '/');
    }

    protected function headers(array $extra = []): array
    {
        $apiKey = (string) ($this->config['api_key'] ?? '');

        if ($apiKey === '') {
            throw new RuntimeException(sprintf('%s api_key is not configured.', $this->code()));
        }

        $authScheme = strtolower((string) ($this->config['auth_scheme'] ?? 'bearer'));

        $authHeader = match ($authScheme) {
            'x-api-key' => ['X-Api-Key' => $apiKey],
            'api-key' => ['Api-Key' => $apiKey],
            default => ['Authorization' => 'Bearer '.$apiKey],
        };

        return array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], $authHeader, $extra);
    }

    protected function header(array $headers, string $name): string
    {
        $name = strtolower($name);

        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $name) {
                return is_array($value) ? (string) reset($value) : (string) $value;
            }
        }

        return '';
    }

    /**
     * Execute a real HTTP call against AfrPay.
     *
     * @return array{ok:bool,status:int,json:array,raw:string,url:string,method:string}
     */
    protected function request(string $method, string $path, array $payload = [], array $headerExtra = [], array $pathVars = []): array
    {
        $this->assertLiveAllowed();

        $url = $this->endpoint($path, $pathVars);
        $headers = $this->headers($headerExtra);
        $timeout = (int) ($this->config['timeout_seconds'] ?? 30);

        $pending = Http::withHeaders($headers)->timeout($timeout)->acceptJson();

        $response = match (strtoupper($method)) {
            'GET' => $pending->get($url, $payload),
            'DELETE' => $pending->delete($url, $payload),
            'PUT' => $pending->put($url, $payload),
            'PATCH' => $pending->patch($url, $payload),
            default => $pending->post($url, $payload),
        };

        $json = $response->json();
        if (! is_array($json)) {
            $json = [];
        }

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'json' => $json,
            'raw' => $response->body(),
            'url' => $url,
            'method' => strtoupper($method),
        ];
    }

    protected function pick(array $data, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                return $data[$key];
            }

            if (str_contains((string) $key, '.')) {
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
        }

        return $default;
    }

    protected function mapProviderStatus(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        $map = (array) ($this->config['status_map'] ?? []);
        if (isset($map[$status])) {
            return (string) $map[$status];
        }

        return match ($status) {
            'created', 'initiated', 'new', 'queued' => 'created',
            'pending', 'processing', 'authorized', 'requires_action', 'requires_3ds', 'awaiting_payment', 'in_progress' => 'pending',
            'paid', 'completed', 'success', 'succeeded', 'settled', 'captured' => 'completed',
            'failed', 'declined', 'rejected', 'cancelled', 'canceled', 'expired', 'error' => 'failed',
            'refunded', 'reversed', 'chargeback', 'disputed' => 'reversed',
            default => $status === '' ? 'unknown' : 'unknown',
        };
    }
}
