# CashForo Onramp adaptor - OR001

## Business flow

OR001 is the onramper connection.

1. Customer pays the licensed onramper by card.
2. Customer officially buys USDT or USDC from the onramper.
3. USDT/USDC is sent to a customer wallet created on the Readies/Finexeble exchange.
4. Finexeble/Readies swaps the USDT/USDC into Readies.
5. Current business rule: `1 Readies = EUR 0.10`; example: `EUR 100 = 1000 Readies`.
6. Customer pays the merchant/casino in Readies.
7. Merchant sells Readies back to Finexeble/Readies.
8. Commission is deducted for handling the payment.

## Required adaptor responsibilities

- Create card-funded stablecoin purchase.
- Track onramper transaction reference.
- Confirm USDT/USDC receipt into customer exchange wallet.
- Trigger or record USDT/USDC-to-Readies swap.
- Record Readies credit to customer balance.
- Record merchant Readies payment.
- Record merchant buyback/redemption.
- Deduct commission.
- Reconcile fiat, stablecoin, Readies, merchant payout, fees, and commission.
- Block or freeze the flow when chargeback, fraud, KYC, AML, or merchant compliance fails.

## Required CashForo API details

These are still needed from CashForo before live mapping:

- Sandbox base URL.
- Production base URL.
- Authentication method.
- API version.
- Create payment / purchase endpoint.
- Required request fields.
- Response fields.
- Provider transaction reference.
- Stablecoin asset selection field (`USDT` / `USDC`).
- Wallet destination field.
- Quote expiry behavior.
- 3DS redirect/challenge flow.
- Card authorization/capture status.
- Webhook event list and samples.
- Signature header and HMAC canonical string.
- Refund/chargeback/reversal endpoints and events.
- Settlement/reconciliation report fields.

## Compliance gate

Do not approve go-live until legal/compliance confirms:

- Onramper licensing.
- Readies/Finexeble exchange licensing coverage.
- Stablecoin purchase and custody model.
- Customer consent for USDT/USDC-to-Readies swap.
- Merchant/casino category and jurisdiction.
- Merchant/casino licensing status.
- Merchant buyback/redemption contract.
- Chargeback/reserve/recourse policy.
