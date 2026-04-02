<?php

namespace App\Services\Interfaces;

use Illuminate\Http\UploadedFile;

interface ErpServiceInterface
{
    /**
     * @return array<string, mixed>
     */
    public function ocrExtract(UploadedFile $file, ?string $jwtToken = null): array;

    public function ocrDetectAttachmentType(UploadedFile $file, ?string $jwtToken = null): string;

    public function ocrGetText(UploadedFile $file, ?string $jwtToken = null): string;

    /**
     * @return array<string, mixed>
     */
    public function ocrPassportService(UploadedFile $file, ?string $jwtToken = null): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function sendTemplateMessage(array $payload, ?string $jwtToken = null): array;

    /**
     * Proxy to ERP Admin E-Payment: GET /admin/epayment-transaction/statuses
     *
     * @return array<int, mixed>|array<string, mixed>
     */
    public function epaymentTransactionStatuses(?string $jwtToken = null): array;

    /**
     * Proxy to ERP Admin E-Payment: POST /admin/epayment-transaction/create-transaction
     *
     * Returns:
     * - ['status' => 302, 'location' => '<url>'] when ERP responds with redirect
     * - ['status' => <int>, 'data' => <mixed>] for non-redirect responses
     *
     * @param array<string, mixed> $payload
     * @return array{status:int, location?:string, data?:mixed}
     */
    public function epaymentCreateTransaction(array $payload, ?string $jwtToken = null): array;

    /**
     * Proxy to ERP Payment API (ERPEPaymentController): GET /payment/check-status
     *
     * @return array<string, mixed>
     */
    public function paymentCheckStatus(string $paymentId, ?string $jwtToken = null): array;

    /**
     * Proxy to ERP Payment API (ERPEPaymentController): POST /payment/webhook-json
     *
     * @param array<string, mixed> $payload
     */
    public function paymentWebhookJson(array $payload, ?string $jwtToken = null): string;
}

