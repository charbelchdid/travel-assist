<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Erp\CreateEpaymentTransactionRequest;
use App\Http\Requests\Erp\IsAuthorizedRequest;
use App\Http\Requests\Erp\OcrFileRequest;
use App\Http\Requests\Erp\SendTemplateMessageRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Interfaces\AuthenticationServiceInterface;
use App\Services\Interfaces\ErpServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErpController extends Controller
{
    public function __construct(
        private readonly ErpServiceInterface $erpService,
        private readonly AuthenticationServiceInterface $authService,
    ) {
    }

    /**
     * Test endpoint: checks ERP authorization for given pageCode + apiUrl.
     * ERP returns plain boolean (true/false).
     */
    public function isAuthorized(IsAuthorizedRequest $request): JsonResponse
    {
        try {
            $token = $this->authService->extractTokenFromRequest($request);

            $allowed = $this->authService->isAuthorized(
                pageCode: (string) $request->input('pageCode'),
                apiUrl: (string) $request->input('apiUrl'),
                jwtToken: $token
            );

            return ApiResponse::success([
                'data' => ['authorized' => $allowed],
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('ERP isAuthorized call failed', 502, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function ocrExtract(OcrFileRequest $request): JsonResponse
    {
        try {
            $token = $this->authService->extractTokenFromRequest($request);
            $file = $request->file('file');

            $data = $this->erpService->ocrExtract($file, $token);

            return ApiResponse::success(['data' => $data]);
        } catch (\Throwable $e) {
            return ApiResponse::error('ERP OCRExtract call failed', 502, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function ocrDetectAttachmentType(OcrFileRequest $request): JsonResponse
    {
        try {
            $token = $this->authService->extractTokenFromRequest($request);
            $file = $request->file('file');

            $type = $this->erpService->ocrDetectAttachmentType($file, $token);

            return ApiResponse::success(['data' => ['type' => $type]]);
        } catch (\Throwable $e) {
            return ApiResponse::error('ERP OCRDetectAttachmentType call failed', 502, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function ocrGetText(OcrFileRequest $request): JsonResponse
    {
        try {
            $token = $this->authService->extractTokenFromRequest($request);
            $file = $request->file('file');

            $text = $this->erpService->ocrGetText($file, $token);

            return ApiResponse::success(['data' => ['text' => $text]]);
        } catch (\Throwable $e) {
            return ApiResponse::error('ERP OCRGetText call failed', 502, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function ocrPassportService(OcrFileRequest $request): JsonResponse
    {
        try {
            $token = $this->authService->extractTokenFromRequest($request);
            $file = $request->file('file');

            $data = $this->erpService->ocrPassportService($file, $token);

            return ApiResponse::success(['data' => $data]);
        } catch (\Throwable $e) {
            return ApiResponse::error('ERP OCRPassportService call failed', 502, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendMessage(SendTemplateMessageRequest $request): JsonResponse
    {
        try {
            $token = $this->authService->extractTokenFromRequest($request);

            /** @var array<string, mixed> $payload */
            $payload = $request->validated();

            $data = $this->erpService->sendTemplateMessage($payload, $token);

            return ApiResponse::success(['data' => $data]);
        } catch (\Throwable $e) {
            return ApiResponse::error('ERP SendMessage call failed', 502, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function epaymentTransactionStatuses(Request $request): JsonResponse
    {
        try {
            $token = $this->authService->extractTokenFromRequest($request);
            $data = $this->erpService->epaymentTransactionStatuses($token);

            return ApiResponse::success(['data' => $data]);
        } catch (\Throwable $e) {
            return ApiResponse::error('ERP e-payment statuses call failed', 502, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function epaymentCreateTransaction(CreateEpaymentTransactionRequest $request)
    {
        try {
            $token = $this->authService->extractTokenFromRequest($request);

            /** @var array<string, mixed> $payload */
            $payload = $request->validated();

            $result = $this->erpService->epaymentCreateTransaction($payload, $token);

            if (($result['status'] ?? null) === 302) {
                $location = (string) ($result['location'] ?? '');
                if ($location === '') {
                    return ApiResponse::error('ERP e-payment create transaction returned redirect without Location', 502);
                }

                return response('', 302)->header('Location', $location);
            }

            return ApiResponse::success(['data' => $result['data'] ?? null], (int) ($result['status'] ?? 200));
        } catch (\Throwable $e) {
            return ApiResponse::error('ERP e-payment create transaction call failed', 502, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

