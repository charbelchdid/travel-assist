<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ErpController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TemporalExamplesController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and are assigned the
| "api" middleware group.
|
*/

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
        'environment' => app()->environment()
    ]);
});

/*
|--------------------------------------------------------------------------
| Authentication Routes (Public)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    // Traditional token-based authentication
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/validate', [AuthController::class, 'validateToken'])->name('auth.validate');

    // Cookie-based authentication (same controller, different response)
    Route::post('/login/cookie', [AuthController::class, 'login'])->name('auth.login.cookie');
    Route::get('/check', [AuthController::class, 'checkAuth'])->name('auth.check');

    // Logout (works for both methods)
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
});

/*
|--------------------------------------------------------------------------
| Payment (ERPEPaymentController proxy) - Public
|--------------------------------------------------------------------------
| These endpoints proxy ERP `/payment/*` endpoints (marked @NoPermission upstream).
| Keep webhook endpoint public for provider callbacks.
*/
Route::prefix('payment')->group(function () {
    Route::get('/check-status', [PaymentController::class, 'checkStatus']);
    Route::post('/webhook-json', [PaymentController::class, 'webhookJson']);
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Require JWT Authentication)
|--------------------------------------------------------------------------
*/
Route::middleware('jwt.auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | User Management Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('user')->group(function () {
        Route::get('/profile', [UserController::class, 'profile'])->name('user.profile');
        Route::put('/profile', [UserController::class, 'updateProfile'])->name('user.profile.update');
        Route::get('/activity', [UserController::class, 'activityLog'])->name('user.activity');
    });

    // Alternative endpoint for user info (compatible with external service)
    Route::get('/myself', [UserController::class, 'myself'])->name('user.myself');

    /*
    |--------------------------------------------------------------------------
    | Product Management Routes
    |--------------------------------------------------------------------------
    */
    Route::apiResource('products', ProductController::class);
    Route::get('products/categories/list', [ProductController::class, 'categories'])->name('products.categories');

    /*
    |--------------------------------------------------------------------------
    | Additional Resource Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('resources')->group(function () {
        // Dashboard statistics
        Route::get('/dashboard-stats', function (Request $request) {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_products' => 150,
                    'total_orders' => 1234,
                    'revenue' => 45678.90,
                    'active_users' => 89
                ]
            ]);
        })->name('resources.dashboard');

        // Notifications
        Route::get('/notifications', function (Request $request) {
            return response()->json([
                'success' => true,
                'data' => [
                    ['id' => 1, 'message' => 'New order received', 'read' => false],
                    ['id' => 2, 'message' => 'Product stock low', 'read' => true],
                ]
            ]);
        })->name('resources.notifications');
    });

    /*
    |--------------------------------------------------------------------------
    | Temporal Examples (Workflow Orchestration)
    |--------------------------------------------------------------------------
    | These endpoints start/query/signal example Temporal workflows.
    | Make sure to run the Temporal worker (RoadRunner) alongside the stack.
    */
    Route::prefix('temporal/examples')->group(function () {
        Route::post('/greeting/start', [TemporalExamplesController::class, 'startGreeting']);

        Route::post('/order/start', [TemporalExamplesController::class, 'startOrder']);
        Route::post('/order/{workflowId}/signal/add-item', [TemporalExamplesController::class, 'orderAddItem']);
        Route::post('/order/{workflowId}/signal/cancel', [TemporalExamplesController::class, 'orderCancel']);
        Route::get('/order/{workflowId}/query/status', [TemporalExamplesController::class, 'orderStatus']);

        Route::post('/child/start', [TemporalExamplesController::class, 'startChildWorkflow']);
        Route::post('/retry/start', [TemporalExamplesController::class, 'startRetryWorkflow']);
        Route::post('/continue-as-new/start', [TemporalExamplesController::class, 'startContinueAsNewWorkflow']);
        Route::post('/saga/start', [TemporalExamplesController::class, 'startSagaWorkflow']);

        // Payment (ERP /payment/*) example: poll + signal + query
        Route::post('/payment-monitor/start', [TemporalExamplesController::class, 'startPaymentStatusMonitor']);
        Route::post('/payment-monitor/{workflowId}/signal/webhook', [TemporalExamplesController::class, 'paymentStatusMonitorWebhook']);
        Route::get('/payment-monitor/{workflowId}/query/status', [TemporalExamplesController::class, 'paymentStatusMonitorStatus']);
    });

    /*
    |--------------------------------------------------------------------------
    | ERP (MVPController integration) - Test endpoints
    |--------------------------------------------------------------------------
    | These endpoints proxy requests to ERP `/admin/mvp/*` endpoints via ErpService.
    */
    Route::prefix('erp')->group(function () {
        Route::get('/is-authorized', [ErpController::class, 'isAuthorized']);

        // Admin E-Payment (proxy to /admin/epayment-transaction/*)
        Route::get('/epayment-transaction/statuses', [ErpController::class, 'epaymentTransactionStatuses']);
        Route::post('/epayment-transaction/create-transaction', [ErpController::class, 'epaymentCreateTransaction']);

        Route::prefix('ocr')->group(function () {
            Route::post('/extract', [ErpController::class, 'ocrExtract']);
            Route::post('/detect-attachment-type', [ErpController::class, 'ocrDetectAttachmentType']);
            Route::post('/get-text', [ErpController::class, 'ocrGetText']);
            Route::post('/passport-service', [ErpController::class, 'ocrPassportService']);
        });

        Route::post('/send-message', [ErpController::class, 'sendMessage']);
    });
});

/*
|--------------------------------------------------------------------------
| Cookie-Based Authentication Routes
|--------------------------------------------------------------------------
| These routes use the same controllers but with cookie middleware
*/
Route::prefix('cookie')->middleware('jwt.auth')->group(function () {
    // User routes
    Route::prefix('user')->group(function () {
        Route::get('/profile', [UserController::class, 'profile']);
        Route::put('/profile', [UserController::class, 'updateProfile']);
    });

    // Products routes
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/{id}', [ProductController::class, 'show']);
    });
});
