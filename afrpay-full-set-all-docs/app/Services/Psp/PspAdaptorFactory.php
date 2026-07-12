<?php

namespace App\Services\Psp;

use App\Contracts\PspAdaptorInterface;
use InvalidArgumentException;

class PspAdaptorFactory
{
    public function make(string $code): PspAdaptorInterface
    {
        $config = config("psp_adaptors.connections.{$code}");

        if (! is_array($config) || empty($config['class'])) {
            throw new InvalidArgumentException("PSP adaptor [{$code}] is not configured.");
        }

        $class = $config['class'];

        return new $class($config);
    }
}
