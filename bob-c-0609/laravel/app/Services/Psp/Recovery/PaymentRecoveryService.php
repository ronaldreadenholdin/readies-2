<?php

namespace App\Services\Psp\Recovery;

use App\DTO\PspPaymentRequest;

final class PaymentRecoveryService
{
    public function __construct(
        private PayByLinkTokenService $tokens,
        private PaymentRecoveryEmailBuilder $emailBuilder,
        private PaymentRecoveryMailerInterface $mailer,
        private string $baseUrl = 'https://0609.readies.biz/pay/recover',
        private bool $sendEmail = false,
        private int $tokenTtlSeconds = 3600,
    ) {
    }

    public function create(PspPaymentRequest $request, array $failedPspCodes, array $candidatePspCodes): array
    {
        $recoveryPsp = $this->chooseRecoveryPsp($failedPspCodes, $candidatePspCodes);
        $token = $this->tokens->create((string) $request->get('merchant_reference'), $recoveryPsp, $this->tokenTtlSeconds);
        $link = rtrim($this->baseUrl, '/') . '/' . $token['token'];
        $email = $this->emailBuilder->build($request, $link);

        if ($this->sendEmail) {
            $this->mailer->send($email);
        }

        return [
            'psp_code' => $recoveryPsp,
            'payment_link' => $link,
            'token' => $token,
            'email' => $email,
            'email_sent' => $this->sendEmail,
        ];
    }

    private function chooseRecoveryPsp(array $failedPspCodes, array $candidatePspCodes): string
    {
        $failed = array_flip(array_map(static fn ($code): string => strtoupper((string) $code), $failedPspCodes));
        foreach ($candidatePspCodes as $code) {
            $code = strtoupper((string) $code);
            if (! isset($failed[$code])) {
                return $code;
            }
        }

        return 'ALTERNATIVE_METHOD';
    }
}
