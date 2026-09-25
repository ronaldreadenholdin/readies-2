<?php

namespace App\Services\Psp\Recovery;

use App\DTO\PspPaymentRequest;

final class PaymentRecoveryEmailBuilder
{
    public function __construct(private string $template = '')
    {
        if ($this->template === '') {
            $this->template = <<<'TEXT'
Hello,

We could not complete the payment for order {{merchant_reference}} for {{amount}} {{currency}}.

You can finish the payment securely here:
{{payment_link}}

If this method is not convenient, you can also try another available payment method on the payment page.

Thank you.
TEXT;
        }
    }

    public function build(PspPaymentRequest $request, string $paymentLink): array
    {
        $body = strtr($this->template, [
            '{{merchant_reference}}' => (string) $request->get('merchant_reference'),
            '{{amount}}' => (string) $request->get('amount.value'),
            '{{currency}}' => (string) $request->get('amount.currency'),
            '{{payment_link}}' => $paymentLink,
        ]);

        return [
            'to' => (string) $request->get('customer.email', ''),
            'subject' => 'Complete your payment',
            'body' => $body,
        ];
    }
}
