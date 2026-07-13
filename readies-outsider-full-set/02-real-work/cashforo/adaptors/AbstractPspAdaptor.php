<?php

namespace App\Services\Psp\Adaptors;

use App\Contracts\PspAdaptorInterface;
use RuntimeException;

abstract class AbstractPspAdaptor implements PspAdaptorInterface
{
    public function __construct(protected readonly array $config)
    {
    }

    public function verifyWebhook(string $rawPayload, array $headers): bool
    {
        $secret = (string) ($this->config['webhook_secret'] ?? '');
        $signatureHeader = strtolower((string) ($this->config['signature_header'] ?? 'x-signature'));
        $signature = $this->header($headers, $signatureHeader);

        if ($secret === '' || $signature === '') {
            return false;
        }

        $computed = hash_hmac('sha256', $rawPayload, $secret);

        return hash_equals($computed, $signature)
            || hash_equals('sha256='.$computed, $signature);
    }

    protected function endpoint(string $path): string
    {
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? ''), '/');

        if ($baseUrl === '') {
            throw new RuntimeException(sprintf('%s base_url is not configured.', $this->code()));
        }

        return $baseUrl.'/'.ltrim($path, '/');
    }

    protected function headers(array $extra = []): array
    {
        $apiKey = (string) ($this->config['api_key'] ?? '');

        if ($apiKey === '') {
            throw new RuntimeException(sprintf('%s api_key is not configured.', $this->code()));
        }

        return array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$apiKey,
        ], $extra);
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
}
