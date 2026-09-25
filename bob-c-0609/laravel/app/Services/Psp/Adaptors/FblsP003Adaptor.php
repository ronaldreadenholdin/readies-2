<?php

namespace App\Services\Psp\Adaptors;

use App\Services\Psp\AbstractPspAdaptor;
use App\Services\Psp\Converters\FblsP003Converter;
use App\Services\Psp\Credentials\PspCredentialProviderInterface;
use App\Services\Psp\Fixtures\FblsP003FixtureTransport;

final class FblsP003Adaptor extends AbstractPspAdaptor
{
    public function __construct(?callable $transport = null, ?PspCredentialProviderInterface $credentials = null)
    {
        parent::__construct(new FblsP003Converter(), $transport ?? FblsP003FixtureTransport::default(), $credentials);
    }

    public function connectionType(): string
    {
        return 'card_psp';
    }
}
