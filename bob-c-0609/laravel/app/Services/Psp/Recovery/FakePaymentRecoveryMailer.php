<?php

namespace App\Services\Psp\Recovery;

final class FakePaymentRecoveryMailer implements PaymentRecoveryMailerInterface
{
    private array $sent = [];

    public function send(array $email): void
    {
        $this->sent[] = $email;
    }

    public function sent(): array
    {
        return $this->sent;
    }
}
