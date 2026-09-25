<?php

namespace App\Services\Psp\Providers\P003;

use App\Services\Psp\AbstractPspAdaptor;
use App\Services\Psp\Credentials\PspCredentialProviderInterface;

final class FblsP003Adaptor extends AbstractPspAdaptor
{
    public const CONTRACT_VERSION = 'ADP-01:v1';

    public function __construct(?callable $transport = null, ?PspCredentialProviderInterface $credentials = null)
    {
        parent::__construct(new FblsP003Converter(), $transport ?? FblsP003FixtureTransport::default(), $credentials);
    }

    public function connectionType(): string
    {
        return 'card_psp';
    }

    public function verifyWebhook(array $headers, string $payload): bool
    {
        $secret = (string) (($this->credentials->get($this->code(), 'sandbox')['webhook_secret'] ?? null) ?: '');
        if ($secret === '') {
            return false;
        }

        $signature = (string) ($headers['X-FBLS-Signature'] ?? $headers['x-fbls-signature'] ?? '');

        return hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }
}
