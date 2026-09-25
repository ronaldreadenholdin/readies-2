<?php

namespace App\Services\Psp;

use App\Contracts\PspAdaptorInterface;
use App\Services\Psp\Credentials\FakePspCredentialProvider;

final class PspAdaptorFactory
{
    public function __construct(private PspAdapterRegistry $registry)
    {
    }

    public static function withOfflineFixtures(?string $fixtureRoot = null): self
    {
        $registry = new PspAdapterRegistry();
        $root = dirname(__DIR__, 3);
        $connections = json_decode((string) file_get_contents($root . '/resources/psp-adapters/provider-connections.json'), true) ?: [];
        foreach ($connections as $connection) {
            if (($connection['status'] ?? null) !== 'sandbox') {
                continue;
            }
            $adaptorClass = $connection['adaptor_class'] ?? null;
            $fixtureClass = $connection['fixture_class'] ?? null;
            if (! is_string($adaptorClass) || ! is_string($fixtureClass) || ! class_exists($adaptorClass) || ! class_exists($fixtureClass)) {
                continue;
            }
            $providerCode = strtoupper((string) $connection['provider_code']);
            $fixturePath = rtrim($fixtureRoot ?? ($root . '/resources/psp-conformance'), '/') . '/' . strtolower($providerCode);
            $transport = new $fixtureClass($fixturePath);
            $secret = method_exists($transport, 'webhookSecret') ? $transport->webhookSecret() : null;
            $credentials = new FakePspCredentialProvider([
                $providerCode => [
                    'sandbox' => [
                        'status' => $secret === null ? 'missing' : 'received',
                        'values' => $secret === null ? [] : ['webhook_secret' => $secret],
                        'vault_entry_link' => 'fixture://' . strtolower($providerCode) . '/sandbox',
                    ],
                ],
            ]);
            $registry->register(new $adaptorClass($transport, $credentials));
        }

        return new self($registry);
    }

    public function get(string $code): PspAdaptorInterface
    {
        return $this->registry->get($code);
    }

    public function registry(): PspAdapterRegistry
    {
        return $this->registry;
    }
}
