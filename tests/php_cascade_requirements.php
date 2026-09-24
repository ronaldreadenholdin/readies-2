<?php

declare(strict_types=1);

use App\DTO\PspPaymentRequest;
use App\DTO\PspRefundRequest;
use App\Services\Psp\AbstractPspAdaptor;
use App\Services\Psp\CascadeRequirementsResolver;
use App\Services\Psp\Contracts\PspConverterInterface;
use App\Services\Psp\ConversionKillerChecker;
use App\Services\Psp\PspAdapterRegistry;
use App\Services\Psp\PspCascadeRouter;
use App\Services\Psp\PspNormalizedContract;

require_once __DIR__ . '/php_psp_bootstrap.php';

final class SyntheticCascadeConverter implements PspConverterInterface
{
    public function __construct(
        private string $code,
        private array $requiredFields,
        private array $fieldMap,
    ) {
    }

    public function pspCode(): string
    {
        return $this->code;
    }

    public function requiredFields(): array
    {
        return $this->requiredFields;
    }

    public function createPayloadFieldMap(): array
    {
        return $this->fieldMap;
    }

    public function toCreatePaymentPayload(PspPaymentRequest $request): array
    {
        $payload = [];
        foreach ($this->fieldMap as $internalField => $pspField) {
            $payload[$pspField] = $request->get($internalField);
        }

        return $payload;
    }

    public function toRefundPayload(PspRefundRequest $request): array
    {
        return [];
    }

    public function normalizeCreatePaymentResponse(array $payload, PspPaymentRequest $request): array
    {
        $status = $payload['status'] === 'captured' ? 'captured' : 'failed';
        $decline = $status === 'failed'
            ? ['class' => 'soft', 'code' => 'SOFT_DECLINE', 'message' => 'Synthetic soft decline.', 'cascade_reason' => 'soft_decline']
            : ['class' => 'none'];
        $amount = PspNormalizedContract::amountFromDecimal((string) $request->get('amount.value'), (string) $request->get('amount.currency'));

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
            ['cascade_eligible' => true],
        );
    }

    public function normalizeStatusResponse(array $payload): array
    {
        return $payload;
    }

    public function normalizeRefundResponse(array $payload, PspRefundRequest $request): array
    {
        return $payload;
    }

    public function normalizeWebhookEvent(array $payload, bool $signatureVerified): array
    {
        return $payload;
    }
}

final class SyntheticCascadeAdaptor extends AbstractPspAdaptor
{
    public function __construct(SyntheticCascadeConverter $converter, array &$payloadLog, string $status)
    {
        parent::__construct($converter, function (string $operation, array $payload) use (&$payloadLog, $converter, $status): array {
            $payloadLog[$converter->pspCode()] = $payload;

            return ['status' => $status];
        });
    }

    public function connectionType(): string
    {
        return 'synthetic';
    }
}

$payloadLog = [];
$registry = new PspAdapterRegistry();
$registry->register(new SyntheticCascadeAdaptor(new SyntheticCascadeConverter('P001', [
    'merchant_reference',
    'amount.value',
    'amount.currency',
    'billing.postal_code',
], [
    'merchant_reference' => 'merchantRef',
    'amount.value' => 'amountCents',
    'amount.currency' => 'currency',
    'billing.postal_code' => 'zip',
]), $payloadLog, 'soft_declined'));
$registry->register(new SyntheticCascadeAdaptor(new SyntheticCascadeConverter('P002', [
    'merchant_reference',
    'amount.value',
    'amount.currency',
    'customer.phone',
], [
    'merchant_reference' => 'reference',
    'amount.value' => 'amount',
    'amount.currency' => 'currencyCode',
    'customer.phone' => 'phone',
]), $payloadLog, 'soft_declined'));
$registry->register(new SyntheticCascadeAdaptor(new SyntheticCascadeConverter('P003DOB', [
    'merchant_reference',
    'amount.value',
    'amount.currency',
    'customer.date_of_birth',
], [
    'merchant_reference' => 'orderId',
    'amount.value' => 'amount',
    'amount.currency' => 'currency',
    'customer.date_of_birth' => 'birthDate',
]), $payloadLog, 'captured'));

$resolver = new CascadeRequirementsResolver($registry);
$router = new PspCascadeRouter($registry, $resolver);
$cascade = ['P001', 'P002', 'P003DOB'];
$request = PspPaymentRequest::fromArray([
    'merchant_reference' => 'order-123',
    'amount' => ['value' => '10.00', 'currency' => 'EUR'],
    'billing' => ['postal_code' => '10115'],
    'customer' => ['phone' => '+49123456789', 'date_of_birth' => '1980-01-02'],
]);

$union = $resolver->resolve($cascade);
$route = $router->route($cascade, $request);
$missing = $resolver->validatePreCollect($request->without('customer.date_of_birth'), $cascade);

$badConverter = new SyntheticCascadeConverter('BAD001', ['merchant_reference'], [
    'merchant_reference' => 'merchantRef',
    'customer.ssn' => 'ssn',
]);
$undeclared = (new ConversionKillerChecker())->issueRows((new ConversionKillerChecker())->checkDeclaredFieldDependencies($badConverter));

echo json_encode([
    'union' => $union,
    'route' => $route,
    'payload_log' => $payloadLog,
    'missing' => $missing,
    'undeclared' => $undeclared,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
