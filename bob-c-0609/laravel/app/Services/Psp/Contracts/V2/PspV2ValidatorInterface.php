<?php

namespace App\Services\Psp\Contracts\V2;

interface PspV2ValidatorInterface
{
    /**
     * @return list<string> Contract error codes.
     */
    public function validatePaymentPageResult(PaymentPageResult $result): array;

    /**
     * @return list<string> Contract error codes.
     */
    public function validatePaymentEvent(PaymentEvent $event): array;
}
