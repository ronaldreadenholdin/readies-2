<?php

namespace App\Services\Psp;

use App\Contracts\PspAdaptorInterface;
use App\Services\Psp\Adaptors\FblsP003Adaptor;
use App\Services\Psp\Credentials\FakePspCredentialProvider;
use App\Services\Psp\Fixtures\FblsP003FixtureTransport;

final class PspAdaptorFactory
{
    public function __construct(private PspAdapterRegistry $registry)
    {
    }

    public static function withOfflineFixtures(?string $fixtureRoot = null): self
    {
        $registry = new PspAdapterRegistry();
        $transport = $fixtureRoot === null
            ? FblsP003FixtureTransport::default()
            : new FblsP003FixtureTransport(rtrim($fixtureRoot, '/') . '/p003');
        $credentials = new FakePspCredentialProvider([
            'P003' => [
                'sandbox' => [
                    'status' => 'received',
                    'values' => ['webhook_secret' => 'test_secret'],
                    'vault_entry_link' => 'fixture://p003/sandbox',
                ],
            ],
        ]);

        $registry->register(new FblsP003Adaptor($transport, $credentials));

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
