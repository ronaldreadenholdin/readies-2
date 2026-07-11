# PSP adaptor interface

This is the production integration layer, not the test board.

Every PSP connection must implement:

- `createPayment(PspPaymentRequest $request): PspPaymentResponse`
- `getPaymentStatus(string $providerReference): PspPaymentResponse`
- `refund(PspRefundRequest $request): PspRefundResponse`
- `verifyWebhook(string $rawPayload, array $headers): bool`
- `handleWebhook(string $rawPayload, array $headers): PspWebhookResult`

Implemented connection codes:

- `OR001` - CashForo Onramp
- `OB003` - CashForo Open Banking

## Files

```text
app/Contracts/PspAdaptorInterface.php
app/DTO/PspPaymentRequest.php
app/DTO/PspPaymentResponse.php
app/DTO/PspRefundRequest.php
app/DTO/PspRefundResponse.php
app/DTO/PspWebhookResult.php
app/Services/Psp/PspAdaptorFactory.php
app/Services/Psp/Adaptors/AbstractPspAdaptor.php
app/Services/Psp/Adaptors/CashForoOnrampAdaptor.php
app/Services/Psp/Adaptors/CashForoOpenBankingAdaptor.php
config/psp_adaptors.php
```

## Laravel usage

```php
use App\DTO\PspPaymentRequest;
use App\Services\Psp\PspAdaptorFactory;

$adaptor = app(PspAdaptorFactory::class)->make('OR001');

$response = $adaptor->createPayment(new PspPaymentRequest(
    merchantReference: 'ORDER-10001',
    customerReference: 'CUSTOMER-500',
    amountMinor: 10000,
    currency: 'EUR',
    successUrl: 'https://readies.example/pay/success',
    failureUrl: 'https://readies.example/pay/failure',
    idempotencyKey: 'ORDER-10001-OR001',
    customer: [
        'email' => 'customer@example.com',
    ],
    metadata: [
        'target_asset' => 'USDT',
        'exchange_wallet_reference' => 'wallet_123',
    ],
));
```

## Important

The current adaptor code is ready as the interface boundary, request contract, response contract, and webhook verification structure.

The exact CashForo endpoint paths, auth requirements, response fields, webhook event names, and signature canonical string still require CashForo's API documentation before live mapping can be completed.
