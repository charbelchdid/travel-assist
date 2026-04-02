# API Routes Documentation
Generated on: 2026-01-22 15:45:02

## Purpose

This file is a **human-readable reference** of the current API routes.

- **Source of truth**: src/routes/api.php
- **Regenerate**: .\tests\generate-api-docs.ps1 (auto-writes this file)
- **Auth docs**: [HTTPONLY_COOKIE_AUTH.md](HTTPONLY_COOKIE_AUTH.md), [JWT_TOKEN_USAGE.md](JWT_TOKEN_USAGE.md)

## Route Summary

| Controller | Total Routes | Protected | Public |
|------------|--------------|-----------|---------|
| Auth | 5 | 0 | 5 |
| Erp | 8 | 8 | 0 |
| Other | 3 | 2 | 1 |
| Payment | 2 | 0 | 2 |
| Product | 8 | 8 | 0 |
| TemporalExamples | 9 | 9 | 0 |
| User | 6 | 6 | 0 |
## Detailed Routes

### Auth Controller

| Method | URI | Name | Action | Auth | Middleware |
|--------|-----|------|--------|------|------------|
| GET | `api/auth/check` | `auth.check` | `App\Http\Controllers\Api\AuthController@checkAuth` | No | `api` |
| POST | `api/auth/login` | `auth.login` | `App\Http\Controllers\Api\AuthController@login` | No | `api` |
| POST | `api/auth/login/cookie` | `auth.login.cookie` | `App\Http\Controllers\Api\AuthController@login` | No | `api` |
| POST | `api/auth/logout` | `auth.logout` | `App\Http\Controllers\Api\AuthController@logout` | No | `api` |
| POST | `api/auth/validate` | `auth.validate` | `App\Http\Controllers\Api\AuthController@validateToken` | No | `api` |

### Erp Controller

| Method | URI | Name | Action | Auth | Middleware |
|--------|-----|------|--------|------|------------|
| POST | `api/erp/epayment-transaction/create-transaction` |  | `App\Http\Controllers\Api\ErpController@epaymentCreateTransaction` | Yes | `api, jwt.auth` |
| GET | `api/erp/epayment-transaction/statuses` |  | `App\Http\Controllers\Api\ErpController@epaymentTransactionStatuses` | Yes | `api, jwt.auth` |
| GET | `api/erp/is-authorized` |  | `App\Http\Controllers\Api\ErpController@isAuthorized` | Yes | `api, jwt.auth` |
| POST | `api/erp/ocr/detect-attachment-type` |  | `App\Http\Controllers\Api\ErpController@ocrDetectAttachmentType` | Yes | `api, jwt.auth` |
| POST | `api/erp/ocr/extract` |  | `App\Http\Controllers\Api\ErpController@ocrExtract` | Yes | `api, jwt.auth` |
| POST | `api/erp/ocr/get-text` |  | `App\Http\Controllers\Api\ErpController@ocrGetText` | Yes | `api, jwt.auth` |
| POST | `api/erp/ocr/passport-service` |  | `App\Http\Controllers\Api\ErpController@ocrPassportService` | Yes | `api, jwt.auth` |
| POST | `api/erp/send-message` |  | `App\Http\Controllers\Api\ErpController@sendMessage` | Yes | `api, jwt.auth` |

### Other Controller

| Method | URI | Name | Action | Auth | Middleware |
|--------|-----|------|--------|------|------------|
| GET | `api/health` |  | `Closure` | No | `api` |
| GET | `api/resources/dashboard-stats` | `resources.dashboard` | `Closure` | Yes | `api, jwt.auth` |
| GET | `api/resources/notifications` | `resources.notifications` | `Closure` | Yes | `api, jwt.auth` |

### Payment Controller

| Method | URI | Name | Action | Auth | Middleware |
|--------|-----|------|--------|------|------------|
| GET | `api/payment/check-status` |  | `App\Http\Controllers\Api\PaymentController@checkStatus` | No | `api` |
| POST | `api/payment/webhook-json` |  | `App\Http\Controllers\Api\PaymentController@webhookJson` | No | `api` |

### Product Controller

| Method | URI | Name | Action | Auth | Middleware |
|--------|-----|------|--------|------|------------|
| GET | `api/cookie/products` |  | `App\Http\Controllers\Api\ProductController@index` | Yes | `api, jwt.auth` |
| GET | `api/cookie/products/{id}` |  | `App\Http\Controllers\Api\ProductController@show` | Yes | `api, jwt.auth` |
| POST | `api/products` | `products.store` | `App\Http\Controllers\Api\ProductController@store` | Yes | `api, jwt.auth` |
| GET | `api/products` | `products.index` | `App\Http\Controllers\Api\ProductController@index` | Yes | `api, jwt.auth` |
| PUT|PATCH | `api/products/{product}` | `products.update` | `App\Http\Controllers\Api\ProductController@update` | Yes | `api, jwt.auth` |
| DELETE | `api/products/{product}` | `products.destroy` | `App\Http\Controllers\Api\ProductController@destroy` | Yes | `api, jwt.auth` |
| GET | `api/products/{product}` | `products.show` | `App\Http\Controllers\Api\ProductController@show` | Yes | `api, jwt.auth` |
| GET | `api/products/categories/list` | `products.categories` | `App\Http\Controllers\Api\ProductController@categories` | Yes | `api, jwt.auth` |

### TemporalExamples Controller

| Method | URI | Name | Action | Auth | Middleware |
|--------|-----|------|--------|------|------------|
| POST | `api/temporal/examples/child/start` |  | `App\Http\Controllers\Api\TemporalExamplesController@startChildWorkflow` | Yes | `api, jwt.auth` |
| POST | `api/temporal/examples/continue-as-new/start` |  | `App\Http\Controllers\Api\TemporalExamplesController@startContinueAsNewWorkflow` | Yes | `api, jwt.auth` |
| POST | `api/temporal/examples/greeting/start` |  | `App\Http\Controllers\Api\TemporalExamplesController@startGreeting` | Yes | `api, jwt.auth` |
| GET | `api/temporal/examples/order/{workflowId}/query/status` |  | `App\Http\Controllers\Api\TemporalExamplesController@orderStatus` | Yes | `api, jwt.auth` |
| POST | `api/temporal/examples/order/{workflowId}/signal/add-item` |  | `App\Http\Controllers\Api\TemporalExamplesController@orderAddItem` | Yes | `api, jwt.auth` |
| POST | `api/temporal/examples/order/{workflowId}/signal/cancel` |  | `App\Http\Controllers\Api\TemporalExamplesController@orderCancel` | Yes | `api, jwt.auth` |
| POST | `api/temporal/examples/order/start` |  | `App\Http\Controllers\Api\TemporalExamplesController@startOrder` | Yes | `api, jwt.auth` |
| POST | `api/temporal/examples/retry/start` |  | `App\Http\Controllers\Api\TemporalExamplesController@startRetryWorkflow` | Yes | `api, jwt.auth` |
| POST | `api/temporal/examples/saga/start` |  | `App\Http\Controllers\Api\TemporalExamplesController@startSagaWorkflow` | Yes | `api, jwt.auth` |

### User Controller

| Method | URI | Name | Action | Auth | Middleware |
|--------|-----|------|--------|------|------------|
| PUT | `api/cookie/user/profile` |  | `App\Http\Controllers\Api\UserController@updateProfile` | Yes | `api, jwt.auth` |
| GET | `api/cookie/user/profile` |  | `App\Http\Controllers\Api\UserController@profile` | Yes | `api, jwt.auth` |
| GET | `api/myself` | `user.myself` | `App\Http\Controllers\Api\UserController@myself` | Yes | `api, jwt.auth` |
| GET | `api/user/activity` | `user.activity` | `App\Http\Controllers\Api\UserController@activityLog` | Yes | `api, jwt.auth` |
| PUT | `api/user/profile` | `user.profile.update` | `App\Http\Controllers\Api\UserController@updateProfile` | Yes | `api, jwt.auth` |
| GET | `api/user/profile` | `user.profile` | `App\Http\Controllers\Api\UserController@profile` | Yes | `api, jwt.auth` |

