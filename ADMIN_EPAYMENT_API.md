# Admin E-Payment API Documentation

## Overview

This document describes how to use the Admin E-Payment endpoints exposed by `AdminEpaymentController`.

This API is designed to **initialize an e-payment transaction** and (when supported by the provider) redirect the caller to the **hosted payment page**.

## Base URL

This application is deployed under the Tomcat context path:

- **Context path**: `/admin` (see `src/main/webapp/META-INF/context.xml`)

So the typical base URL is:

```text
https://<your-domain>/admin
```

## Authentication

This module uses Spring Security (see `pom.xml` dependency `spring-boot-starter-security`) and most “admin” endpoints are intended to be protected.

- If you receive **`401 Unauthorized`**, authenticate the same way you do for other Admin APIs in this deployment (commonly HTTP Basic Auth or an authenticated session cookie).

> Note: The exact auth mechanism is configured outside this repository (in `magnamedia-core` / parent configuration), so this doc focuses on request/response payloads and behavior.

---

## Endpoints

### 1) List transaction statuses

Returns the list of available transaction statuses.

- **Endpoint**: `GET /epayment-transaction/statuses`
- **Full URL (typical)**: `GET /admin/epayment-transaction/statuses`

**Example (curl):**

```bash
curl -sS -X GET "https://<your-domain>/admin/epayment-transaction/statuses"
```

**Response:** `200 OK` with a JSON array (enum list).

---

### 2) Create (initialize) a transaction

Initializes a new e-payment transaction using the `adminEPaymentService` flow and returns either:

- **`302 Found`** with a `Location` header pointing to the provider hosted payment page (when `providerPageLink` exists), or
- **`200 OK`** with the raw transaction initialization map returned by the e-payment engine.

- **Endpoint**: `POST /epayment-transaction/create-transaction`
- **Full URL (typical)**: `POST /admin/epayment-transaction/create-transaction`
- **Content-Type**: `application/json`

#### Request body schema

The request body is `CreateTransactionRequestDto`:

| Field | Type | Required | Notes |
|------|------|----------|------|
| `relatedEntityId` | number (integer) | No | Defaults to `-1` if omitted/null. |
| `entityType` | string | Yes* | Passed to the payment transaction creation. Usually identifies the related entity type in your domain. |
| `identifier` | string | No | Passed to the payment transaction creation (often a local reference / identifier). |
| `amount` | number | Yes* | Payment amount. |
| `description` | string | No | Human-readable description shown in dashboards/provider. |
| `customParams` | object (string→string map) | No | Arbitrary provider/application parameters. Defaults to `{}`. |
| `customRedirectUrl` | string (URL) | No | If present, forwarded into custom params as `custom_redirect_url`. |
| `customFailRedirectUrl` | string (URL) | No | If present, forwarded into custom params as `custom_fail_redirect_url`. |
| `referer` | string | No | Forwarded into the payment engine `initializeTransaction(...)` call; used by some providers/flows. |

\* The DTO itself does not enforce required fields, but the underlying e-payment engine typically needs at least `entityType` and `amount`. If you omit required business values, you should expect an error from the service layer.

#### What the controller does (important behavior)

On receipt of the request, the controller builds a context map and forces hosted page mode:

- `hostedPageMode` is always set to `PAGE`.
- `customRedirectUrl` and `customFailRedirectUrl` (if present) are copied into the context with snake_case keys:
  - `custom_redirect_url`
  - `custom_fail_redirect_url`

Then it calls:

- `ePaymentService.initializeTransaction("admin", "adminEPaymentService", context, true, referer)`

Finally, it checks the returned map for:

- `providerPageLink` (string)

If present, the API returns **`302 Found`** and sets **`Location: <providerPageLink>`**.

#### Example request

```json
{
  "relatedEntityId": 12345,
  "entityType": "ORDER",
  "identifier": "ORD-2026-0001",
  "amount": 150.75,
  "description": "Admin payment for order ORD-2026-0001",
  "customParams": {
    "cart_currency": "USD",
    "customer_email": "customer@example.com"
  },
  "customRedirectUrl": "https://<your-domain>/admin/#/payments/success",
  "customFailRedirectUrl": "https://<your-domain>/admin/#/payments/fail",
  "referer": "https://<your-domain>/admin"
}
```

#### curl examples

**A) Don’t follow redirects (see the 302 and Location header):**

```bash
curl -i -X POST "https://<your-domain>/admin/epayment-transaction/create-transaction" \
  -H "Content-Type: application/json" \
  -d "{\"relatedEntityId\":12345,\"entityType\":\"ORDER\",\"identifier\":\"ORD-2026-0001\",\"amount\":150.75,\"description\":\"Admin payment\",\"customParams\":{},\"referer\":\"https://<your-domain>/admin\"}"
```

**Expected response (when hosted page link is returned):**

```text
HTTP/1.1 302
Location: https://<provider-hosted-payment-page>
```

**B) Follow redirects (takes you to the provider page):**

```bash
curl -L -X POST "https://<your-domain>/admin/epayment-transaction/create-transaction" \
  -H "Content-Type: application/json" \
  -d "{\"relatedEntityId\":12345,\"entityType\":\"ORDER\",\"amount\":150.75,\"customParams\":{}}"
```

**C) If the provider doesn’t return a hosted link (or the engine chooses not to redirect):**

The API returns **`200 OK`** with a JSON object (a map) from the e-payment engine. At minimum, the controller expects that it *may* contain:

- `providerPageLink` (string, optional)

The rest of the keys depend on the e-payment provider integration within `magnamedia-core`.

#### Responses

- **302 Found**
  - **When**: `providerPageLink` is present in the initialization result map
  - **Headers**:
    - `Location: <providerPageLink>`
  - **Body**: empty

- **200 OK**
  - **When**: `providerPageLink` is not present (or is null)
  - **Body**: JSON object (transaction initialization payload/map)

- **4xx / 5xx**
  - **When**: invalid input, authentication failure, or payment engine/provider errors
  - **Body**: depends on global exception handling (often a JSON error payload)

---

## Practical notes / common pitfalls

- **Redirect handling**: Many HTTP clients (Postman, curl without `-L`, some frontends) do not automatically follow 302s; decide whether you want the **Location header** (to show in UI) or to **follow** the redirect.
- **Double vs integer amounts**: `amount` is a `Double` in the DTO. Avoid floating point rounding issues when generating it; prefer sending values with 2 decimals (e.g., `150.75`).
- **Custom params**: `customParams` is a string map; if you need nested structures, serialize them as strings (e.g., JSON string) unless your provider supports complex values.
- **Redirect URLs**: If you provide `customRedirectUrl` / `customFailRedirectUrl`, they are forwarded as `custom_redirect_url` / `custom_fail_redirect_url` in the provider params.

