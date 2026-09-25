<?php

namespace App\Services\Psp\Recovery;

interface PaymentRecoveryMailerInterface
{
    public function send(array $email): void;
}
