<?php

declare(strict_types=1);

use App\Services\Psp\Contracts\V2\CanonicalStatus;
use App\Services\Psp\Contracts\V2\DeclineClass;
use App\Services\Psp\Contracts\V2\DisplayStatus;
use App\Services\Psp\Contracts\V2\ErrorReason;
use App\Services\Psp\Contracts\V2\NextAction;
use App\Services\Psp\Contracts\V2\PaymentAmount;
use App\Services\Psp\Contracts\V2\PaymentEvent;
use App\Services\Psp\Contracts\V2\PaymentEventSource;
use App\Services\Psp\Contracts\V2\PaymentPageResult;
use App\Services\Psp\Contracts\V2\PspV2ContractValidator;
use App\Services\Psp\Contracts\V2\RedirectMethod;

require_once __DIR__ . '/php_psp_bootstrap.php';

$validator = new PspV2ContractValidator();

$page = new PaymentPageResult(
    'payment-test-1',
    'attempt-test-1',
    DisplayStatus::THREE_DS_REDIRECT,
    NextAction::REDIRECT,
    'redirect-placeholder',
    RedirectMethod::GET,
    [],
    true,
    'verify_card',
);

$invalidPage = new PaymentPageResult(
    'payment-test-1',
    'attempt-test-1',
    DisplayStatus::THREE_DS_REDIRECT,
    NextAction::REDIRECT,
    null,
    null,
    [],
    true,
    'verify_card',
);

$eventId = PaymentEvent::eventIdFor('P999', 'reference-test', CanonicalStatus::CAPTURED, PaymentEventSource::WEBHOOK);
$event = new PaymentEvent(
    $eventId,
    'P999',
    'merchant-account-test',
    'payment-test-1',
    'attempt-test-1',
    'reference-test',
    PaymentEventSource::WEBHOOK,
    CanonicalStatus::CAPTURED,
    DeclineClass::NONE,
    null,
    new PaymentAmount('10.00', 'EUR', 1000),
    'APPROVED',
    null,
    'approved',
    true,
    '2026-09-26T00:00:00Z',
    null,
);

$unverifiedEvent = new PaymentEvent(
    $eventId,
    'P999',
    'reference-test',
    'payment-test-1',
    'attempt-test-1',
    'reference-test',
    PaymentEventSource::WEBHOOK,
    CanonicalStatus::CAPTURED,
    DeclineClass::NONE,
    null,
    new PaymentAmount('10.00', 'EUR', 1000),
    'APPROVED',
    null,
    'approved',
    false,
    '2026-09-26T00:00:00Z',
    null,
);

$mismatchEvent = new PaymentEvent(
    PaymentEvent::eventIdFor('P999', 'reference-test', CanonicalStatus::DECLINED, PaymentEventSource::POLL),
    'P999',
    'merchant-account-test',
    'payment-test-1',
    'attempt-test-1',
    'reference-test',
    PaymentEventSource::POLL,
    CanonicalStatus::DECLINED,
    DeclineClass::HARD,
    ErrorReason::AMOUNT_MISMATCH,
    new PaymentAmount('10.00', 'EUR', 1000),
    'MISMATCH',
    null,
    'amount_mismatch',
    true,
    '2026-09-26T00:00:00+01:00',
    null,
);

echo json_encode([
    'valid_page_errors' => $validator->validatePaymentPageResult($page),
    'invalid_page_errors' => $validator->validatePaymentPageResult($invalidPage),
    'valid_event_errors' => $validator->validatePaymentEvent($event),
    'unverified_event_errors' => $validator->validatePaymentEvent($unverifiedEvent),
    'mismatch_event_errors' => $validator->validatePaymentEvent($mismatchEvent),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
