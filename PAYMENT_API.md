# Payment API (ERPEPaymentController)

This document describes how to call the endpoints implemented in `ERPEPaymentController`:

- Base path: `/payment`
- Controller: `src/main/java/com/magnamedia/controller/ERPEPaymentController.java`

## Base URL

All examples below use:

- `{{BASE_URL}}`: your server base URL (for example `http://localhost:8080` or your deployed domain)

So the full endpoint becomes: `{{BASE_URL}}/payment/...`

## Authentication / permissions

`ERPEPaymentController` is annotated with `@NoPermission`, meaning **these endpoints are not protected by the app’s permission system**.

- In practice, your deployment may still restrict access via networking (reverse proxy rules, IP allowlists, firewall rules, etc.).
- The webhook endpoint can optionally validate a provider signature (see below).

---

## 1) Check a transaction status

### Endpoint

`GET /payment/check-status`

### Query parameters

- **paymentID** (required, `string`): The transaction UUID.
  - Important: despite being named `paymentID`, the controller looks up the transaction using `findByUuid(paymentID)`, so you must pass the **UUID** (not the numeric database ID).

### Response

- HTTP status: **200 OK** (the controller always returns `ResponseEntity.ok(...)`).
- Body: a JSON object with the fields defined by `TransactionStatusProjection` (or `null` if the UUID is not found, depending on how `BaseController.project(...)` handles `null` entities).

#### Response fields (TransactionStatusProjection)

These getters are projected from the `EPaymentTransaction` entity:

- **transactionStatus** (`ETransactionStatus`): Current transaction status (enum value).
- **uuid** (`string`): Transaction UUID.
- **customRedirectURL** (`string`): Optional redirect URL configured for the transaction.
- **providerPageLink** (`string`): Provider-hosted payment page link (if applicable).
- **hasError** (`string`): Error flag as stored on the transaction (string in projection).
- **errorCode** (`string`): Provider/system error code (if any).
- **provider** (`EPaymentProvider`): Payment provider (enum value).
- **originLink** (`string`): “Origin” link (comment in code says “not used”; in PayTabs it may redirect to create page on retry).

#### Possible enum values

Based on `magnamedia-core` **6.1.0** (the dependency used by this project), the possible values are:

- `ETransactionStatus`: `CREATED`, `AUTHORIZED`, `SUCCESS`, `FAILED`, `PENDING`, `DECLINED`, `REFUNDED`, `VOIDED`, `EXPIRED`
- `EPaymentProvider`: `PAYTABS`, `CHECKOUT`

### Example: curl

```bash
curl -X GET "{{BASE_URL}}/payment/check-status?paymentID=7b6d3a2a-9b0f-4c3a-8a6c-1b2c3d4e5f60" ^
  -H "Accept: application/json"
```

### Example response (shape)

```json
{
  "transactionStatus": "AUTHORIZED",
  "uuid": "7b6d3a2a-9b0f-4c3a-8a6c-1b2c3d4e5f60",
  "customRedirectURL": "https://example.com/payment/success",
  "providerPageLink": "https://provider.example.com/pay/abc123",
  "hasError": "false",
  "errorCode": null,
  "provider": "PAYTABS",
  "originLink": null
}
```

### Common behaviors / edge cases

- **Unknown UUID**: the controller still returns **200 OK**; the body may be `null` or an empty object depending on the implementation of `BaseController.project(...)`.
- **Server errors**: any unexpected exception will surface as a 5xx response, unless a global exception handler changes this behavior.

---

## 2) Provider webhook (JSON payload)

This endpoint is intended to be called by the payment provider to notify your system about a payment event.

### Endpoint

`/payment/webhook-json`

Implementation note: the controller uses `@RequestMapping("/webhook-json")` without an explicit HTTP method. In practice, configure the provider to call it using **POST** with a JSON body.

### Request headers

- **Content-Type**: `application/json`
- **Signature headers (optional)**: If signature validation is enabled, the server will validate a provider signature based on the incoming `HttpServletRequest`.
  - The toggle is controlled by core parameter: `CoreParameter.EPAYMENT_WEBHOOK_CHECK_SIGNATURE`
  - Enabled when its value equals `"TRUE"` (case-insensitive).
  - If enabled and signature validation fails, the controller logs an error and throws a `RuntimeException` (likely resulting in a 5xx response).

> Because signature generation/validation is implemented in `com.magnamedia.core.helper.epayment.EPaymentService.validateSignature(request)`, the exact header names and algorithm depend on the core module and provider.

### Request body (minimum required shape)

The controller expects the incoming JSON to contain:

- `data.reference` (string): This value must be the **transaction UUID** that exists in your DB.

Example minimal body:

```json
{
  "data": {
    "reference": "7b6d3a2a-9b0f-4c3a-8a6c-1b2c3d4e5f60"
  }
}
```

### What the server does with the webhook

On receiving the payload:

1. Optionally validates the webhook signature (if enabled).
2. Extracts the UUID from `body.data.reference`.
3. Looks up the transaction by UUID.
4. If the transaction does not exist, it returns `200 OK` with body `"done"`.
5. If the transaction exists:
   - It adds/overwrites `paymentID` in the payload with the transaction’s **numeric database ID** (`transaction.getId()`).
   - It forwards the payload (as-is) to the creator module via `InterModuleConnector`, calling:
     - bean/service: `"EPaymentService"`
     - method: `"apply"`
     - argument type: `Map`
6. Returns `200 OK` with body `"done"`.

### Example: curl (local testing)

```bash
curl -X POST "{{BASE_URL}}/payment/webhook-json" ^
  -H "Content-Type: application/json" ^
  -H "Accept: text/plain" ^
  -d "{\"data\":{\"reference\":\"7b6d3a2a-9b0f-4c3a-8a6c-1b2c3d4e5f60\"}}"
```

Expected response:

```text
done
```

### Notes for provider configuration

- **Webhook URL**: set to `{{BASE_URL}}/payment/webhook-json`
- **Reference mapping**: ensure the provider sends the local reference/merchant reference as `data.reference` and that it equals the UUID stored in `EPaymentTransaction.uuid`.
- **Retries**: because the endpoint returns `"done"` even when the transaction is missing, provider retry behavior may not re-deliver for unknown/incorrect references. Validate reference mapping carefully.

