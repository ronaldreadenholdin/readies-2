<?php

namespace App\Services\Psp\Trusted;

final class InMemoryPersonalDataEncryption implements PersonalDataEncryptionInterface
{
    public function encrypt(string $value): string
    {
        return 'enc:' . base64_encode($value);
    }

    public function decrypt(string $value): string
    {
        if (! str_starts_with($value, 'enc:')) {
            return $value;
        }

        return (string) base64_decode(substr($value, 4), true);
    }
}
