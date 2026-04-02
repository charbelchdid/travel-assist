<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\PaymentCheckStatusRequest;
use App\Http\Requests\Payment\PaymentWebhookJsonRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Interfaces\ErpServiceInterface;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function __construct(
        private readonly ErpServiceInterface $erpService,
    ) {
    }

    public function checkStatus(PaymentCheckStatusRequest $request): JsonResponse
    {
        try {
            $paymentId = (string) $request->query('paymentID');
            $data = $this->erpService->paymentCheckStatus($paymentId);

            return ApiResponse::success(['data' => $data]);
        } catch (\Throwable $e) {
            return ApiResponse::error('ERP payment check-status call failed', 502, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function webhookJson(PaymentWebhookJsonRequest $request): Response
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = $request->validated();

            // ERP returns "done" (text). Mirror that behavior for provider compatibility.
            $result = $this->erpService->paymentWebhookJson($payload);

            return response($result !== '' ? $result : 'done', 200)
                ->header('Content-Type', 'text/plain');
        } catch (\Throwable $e) {
            return response('error', 502)->header('Content-Type', 'text/plain');
        }
    }
}

