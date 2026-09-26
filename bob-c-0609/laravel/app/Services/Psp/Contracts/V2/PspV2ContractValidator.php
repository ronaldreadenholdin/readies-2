<?php

namespace App\Services\Psp\Contracts\V2;

final class PspV2ContractValidator implements PspV2ValidatorInterface
{
    public function validatePaymentPageResult(PaymentPageResult $result): array
    {
        $errors = [];

        if ($result->schemaVersion !== PaymentPageResult::SCHEMA_VERSION) {
            $errors[] = 'schema_version';
        }
        if ($result->paymentId === '') {
            $errors[] = 'payment_id';
        }
        if ($result->attemptId === '') {
            $errors[] = 'attempt_id';
        }
        if (! DisplayStatus::isValid($result->displayStatus)) {
            $errors[] = 'display_status';
        }
        if (! NextAction::isValid($result->nextAction)) {
            $errors[] = 'next_action';
        }
        if (! RedirectMethod::isValid($result->redirectMethod)) {
            $errors[] = 'redirect_method';
        }
        if ($result->nextAction === NextAction::REDIRECT && ($result->redirectUrl === null || $result->redirectUrl === '')) {
            $errors[] = 'redirect_url.required';
        }
        if ($result->nextAction === NextAction::REDIRECT && $result->redirectMethod === null) {
            $errors[] = 'redirect_method.required';
        }
        if ($result->redirectMethod === RedirectMethod::POST) {
            foreach ($result->formFields as $key => $value) {
                if (! is_string($key) || ! is_string($value)) {
                    $errors[] = 'form_fields';
                    break;
                }
            }
        }
        if (! $this->messageCodeIsValid($result->messageCode)) {
            $errors[] = 'message_code';
        }

        return array_values(array_unique($errors));
    }

    public function validatePaymentEvent(PaymentEvent $event): array
    {
        $errors = [];

        if ($event->schemaVersion !== PaymentEvent::SCHEMA_VERSION) {
            $errors[] = 'schema_version';
        }
        foreach ([
            'event_id' => $event->eventId,
            'psp_code' => $event->pspCode,
            'merchant_account_id' => $event->merchantAccountId,
            'payment_id' => $event->paymentId,
            'attempt_id' => $event->attemptId,
            'raw_code' => $event->rawCode,
            'normalized_reason' => $event->normalizedReason,
        ] as $field => $value) {
            if ($value === '') {
                $errors[] = $field;
            }
        }
        if ($event->eventId !== $event->expectedEventId()) {
            $errors[] = 'event_id.hash';
        }
        if (! PaymentEventSource::isValid($event->source)) {
            $errors[] = 'source';
        }
        if (! CanonicalStatus::isValid($event->status)) {
            $errors[] = 'status';
        }
        if (! DeclineClass::isValid($event->declineClass)) {
            $errors[] = 'decline_class';
        }
        if (! ErrorReason::isValid($event->errorReason)) {
            $errors[] = 'error_reason';
        }
        if (! $event->signatureVerified) {
            $errors[] = 'signature_verified';
        }
        if (! $this->amountIsValid($event->amount)) {
            $errors[] = 'amount';
        }
        if (! $this->utcTimestamp($event->receivedAt)) {
            $errors[] = 'received_at';
        }
        if ($event->pspTimestamp !== null && ! $this->utcTimestamp($event->pspTimestamp)) {
            $errors[] = 'psp_timestamp';
        }
        if ($event->errorReason === ErrorReason::AMOUNT_MISMATCH && $event->status !== CanonicalStatus::ERROR) {
            $errors[] = 'amount_mismatch.status';
        }
        if ($event->status === CanonicalStatus::ERROR && $event->errorReason === null) {
            $errors[] = 'error_reason.required';
        }
        if ($event->declineClass === DeclineClass::SOFT && $event->status !== CanonicalStatus::DECLINED && $event->source !== PaymentEventSource::REBOOK) {
            $errors[] = 'decline_class.soft_status';
        }
        if ($event->declineClass === DeclineClass::HARD && $event->status !== CanonicalStatus::DECLINED) {
            $errors[] = 'decline_class.hard_status';
        }

        return array_values(array_unique($errors));
    }

    private function amountIsValid(PaymentAmount $amount): bool
    {
        return preg_match('/^-?\d+\.\d{2}$/', $amount->value) === 1
            && preg_match('/^[A-Z]{3}$/', $amount->currency) === 1
            && is_int($amount->minorUnits);
    }

    private function utcTimestamp(string $timestamp): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $timestamp) === 1;
    }

    private function messageCodeIsValid(string $messageCode): bool
    {
        return in_array($messageCode, [
            'card_declined',
            'try_other_card',
            'insufficient_funds',
            'verify_card',
            'retry_later',
            'contact_support',
        ], true) || preg_match('/^fix_details:[a-z0-9_.-]+$/', $messageCode) === 1;
    }
}
