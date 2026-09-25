<?php

namespace App\Services\Psp\Trusted;

interface PersonalDataEncryptionInterface
{
    public function encrypt(string $value): string;

    public function decrypt(string $value): string;
}
