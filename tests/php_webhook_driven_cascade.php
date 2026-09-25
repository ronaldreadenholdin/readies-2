<?php

declare(strict_types=1);

use App\DTO\PspPaymentRequest;
use App\DTO\PspRefundRequest;
use App\Services\Psp\AbstractPspAdaptor;
use App\Services\Psp\CascadeRequirementsResolver;
use App\Services\Psp\Contracts\PspConverterInterface;
use App\Services\Psp\PspAdapterRegistry;
use App\Services\Psp\PspNormalizedContract;
use App\Services\Psp\PspWebhookDrivenCascadeOrchestrator;
use App\Services\Psp\Recovery\FakePaymentRecoveryMailer;
use App\Services\Psp\Recovery\PayByLinkTokenService;
use App\Services\Psp\Recovery\PaymentRecoveryEmailBuilder;
use App\Services\Psp\Recovery\PaymentRecoveryService;

require_once __DIR__ . '/php_psp_bootstrap.php';

final class WebhookRuleConverter implements PspConverterInterface
{
    public function __construct(private string $code)
    {
    }

    public function pspCode(): string
    {
        return $this->code;
    }

    public function requiredFields(): array
    {
        return ['merchant_reference', 'amount.value', 'amount.currency', 'customer.email'];
    }

    public function createPayloadFieldMap(): array
    {
        return [
            'merchant_reference' => 'merchantRef',
            'amount.value' => 'amount',
            'amount.currency' => 'currency',
            'customer.email' => 'email',
        ];
    }

    public function toCreatePaymentPayload(PspPaymentRequest $request): array
    {
        return ['merchantRef' => $request->get('merchant_reference')];
    }

    public function toRefundPayload(PspRefundRequest $request): array
    {
        return [];
    }

    public function normalizeCreatePaymentResponse(array $payload, PspPaymentRequest $request): array
    {
        return $this->normalized((string) ($payload['status'] ?? 'pending'), $request);
    }

    public function normalizeStatusResponse(array $payload): array
    {
        $request = PspPaymentRequest::fromArray($payload['request']);

        return $this->normalized((string) ($payload['status'] ?? 'pending'), $request);
    }

    public function normalizeRefundResponse(array $payload, PspRefundRequest $request): array
    {
        return [];
    }

    public function normalizeWebhookEvent(array $payload, bool $signatureVerified): array
    {
        $request = PspPaymentRequest::fromArray($payload['request']);

        return $this->normalized((string) $payload['status'], $request, (int) ($payload['waited_ms'] ?? 0));
    }

    private function normalized(string $status, PspPaymentRequest $request, int $waitedMs = 0): array
    {
        $amount = PspNormalizedContract::amountFromDecimal((string) $request->get('amount.value'), (string) $request->get('amount.currency'));
        $decline = in_array($status, ['failed', 'declined'], true)
            ? ['class' => $status === 'declined' ? 'hard' : 'soft', 'code' => strtoupper($status), 'message' => 'Synthetic final status.', 'cascade_reason' => $status === 'failed' ? 'soft_decline' : null]
            : ['class' => 'none'];

        return PspNormalizedContract::blank(
            $this->code,
            'create_payment',
            (string) $request->get('merchant_reference'),
            $this->code . '-payment',
            $this->code . '-ref',
            $status,
            $amount->value,
            $amount->currency,
            $amount->minorUnits,
            '2026-09-24T16:00:00Z',
            '2026-09-24T16:00:01Z',
            $decline,
            null,
            ['cascade_eligible' => true, 'waited_ms' => $waitedMs],
        );
    }
}

final class WebhookRuleAdaptor extends AbstractPspAdaptor
{
    public function __construct(WebhookRuleConverter $converter, private string $statusAfterTimeout, private array $request)
    {
        $statusAfterTimeout = $this->statusAfterTimeout;
        $request = $this->request;
        parent::__construct($converter, function (string $operation, array $payload) use ($statusAfterTimeout, $request): array {
            return ['status' => $operation === 'payment_status' ? $statusAfterTimeout : 'pending', 'request' => $request];
        });
    }

    public function connectionType(): string
    {
        return 'synthetic';
    }
}

function webhookState(string $status, array $request, int $waitedMs = 0): array
{
    return [
        'schema_version' => 'readies.psp.normalized.v1',
        'status' => $status,
        'metadata' => ['waited_ms' => $waitedMs],
        'decline' => in_array($status, ['failed', 'declined'], true)
            ? ['class' => $status === 'declined' ? 'hard' : 'soft', 'cascade_reason' => $status === 'failed' ? 'soft_decline' : null]
            : ['class' => 'none'],
    ];
}

$requestArray = [
    'merchant_reference' => 'order-webhook-123',
    'amount' => ['value' => '10.00', 'currency' => 'EUR'],
    'customer' => ['email' => 'customer@example.test'],
];
$request = PspPaymentRequest::fromArray($requestArray);

function makeWebhookRuleOrchestrator(array $requestArray, array $statusMap, ?FakePaymentRecoveryMailer &$mailer = null): PspWebhookDrivenCascadeOrchestrator
{
    $registry = new PspAdapterRegistry();
    foreach (['P001', 'P002', 'P003', 'P004'] as $code) {
        $registry->register(new WebhookRuleAdaptor(new WebhookRuleConverter($code), $statusMap[$code] ?? 'pending', $requestArray));
    }
    $resolver = new CascadeRequirementsResolver($registry);
    $mailer = new FakePaymentRecoveryMailer();
    $recovery = new PaymentRecoveryService(new PayByLinkTokenService(), new PaymentRecoveryEmailBuilder(), $mailer, 'https://0609.readies.biz/pay/recover', false);

    return new PspWebhookDrivenCascadeOrchestrator($registry, $resolver, $recovery, ['max_attempts' => 3, 'default_wait_timeout_ms' => 1000]);
}

$mainMailer = null;
$orchestrator = makeWebhookRuleOrchestrator($requestArray, [], $mainMailer);
$pending = $orchestrator->start(['P001', 'P002', 'P003'], $request, ['P004']);
$failedWebhook = $orchestrator->handleWebhook($pending, 'P001', webhookState('failed', $requestArray, 500));
$timeoutMailer = null;
$timeoutFailed = makeWebhookRuleOrchestrator($requestArray, ['P001' => 'failed'], $timeoutMailer)->handleTimeout($pending, 'P001');
$timeoutPending = makeWebhookRuleOrchestrator($requestArray, ['P001' => 'pending'], $timeoutMailer)->handleTimeout($pending, 'P001');
$threeFailures = $orchestrator->handleWebhook($pending, 'P001', webhookState('failed', $requestArray, 100));
$threeFailures = $orchestrator->handleWebhook($threeFailures, 'P002', webhookState('failed', $requestArray, 100));
$threeFailures = $orchestrator->handleWebhook($threeFailures, 'P003', webhookState('failed', $requestArray, 100));
$lateSuccess = $orchestrator->handleWebhook($failedWebhook, 'P001', webhookState('captured', $requestArray, 700));

echo json_encode([
    'pending' => $pending,
    'failed_webhook' => $failedWebhook,
    'timeout_failed' => $timeoutFailed,
    'timeout_pending' => $timeoutPending,
    'three_failures' => $threeFailures,
    'late_success' => $lateSuccess,
    'mailer_sent' => $mainMailer->sent(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
