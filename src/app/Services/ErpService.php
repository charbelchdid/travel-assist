<?php

namespace App\Services;

use App\Services\Interfaces\ErpServiceInterface;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class ErpService implements ErpServiceInterface
{
    private string $baseUrl;

    public function __construct()
    {
        // Default to AUTH_BASE_URL since many deployments host /admin/mvp under the same ERP backend.
        $this->baseUrl = (string) config('services.erp.base_url', env('AUTH_BASE_URL', ''));
    }

    public function ocrExtract(UploadedFile $file, ?string $jwtToken = null): array
    {
        $response = $this->waitForResponse($this->request($jwtToken)
            ->attach('file', $this->openUploadedFileStream($file), $file->getClientOriginalName())
            ->post('/admin/mvp/OCRExtract'))
            ->throw();

        return $this->expectJsonArray($response);
    }

    public function ocrDetectAttachmentType(UploadedFile $file, ?string $jwtToken = null): string
    {
        $response = $this->waitForResponse($this->request($jwtToken)
            ->attach('file', $this->openUploadedFileStream($file), $file->getClientOriginalName())
            ->post('/admin/mvp/OCRDetectAttachmentType'))
            ->throw();

        return $this->expectStringBodyOrJsonScalar($response);
    }

    public function ocrGetText(UploadedFile $file, ?string $jwtToken = null): string
    {
        $response = $this->waitForResponse($this->request($jwtToken)
            ->attach('file', $this->openUploadedFileStream($file), $file->getClientOriginalName())
            ->post('/admin/mvp/OCRGetText'))
            ->throw();

        return $this->expectStringBodyOrJsonScalar($response);
    }

    public function ocrPassportService(UploadedFile $file, ?string $jwtToken = null): array
    {
        $response = $this->waitForResponse($this->request($jwtToken)
            ->attach('file', $this->openUploadedFileStream($file), $file->getClientOriginalName())
            ->post('/admin/mvp/OCRPassportService'))
            ->throw();

        return $this->expectJsonArray($response);
    }

    public function sendTemplateMessage(array $payload, ?string $jwtToken = null): array
    {
        $response = $this->waitForResponse($this->request($jwtToken)
            ->post('/admin/mvp/SendMessage', $payload))
            ->throw();

        return $this->expectJsonArray($response);
    }

    public function epaymentTransactionStatuses(?string $jwtToken = null): array
    {
        $response = $this->waitForResponse(
            $this->request($jwtToken)->get('/admin/epayment-transaction/statuses')
        )->throw();

        return $this->expectJsonArray($response);
    }

    public function epaymentCreateTransaction(array $payload, ?string $jwtToken = null): array
    {
        // Important: do NOT follow redirects; callers may need the Location header for hosted payment page.
        $response = $this->waitForResponse(
            $this->request($jwtToken)
                ->withOptions(['allow_redirects' => false])
                ->post('/admin/epayment-transaction/create-transaction', $payload)
        );

        if ($response->status() === 302) {
            $location = $response->header('Location');
            return [
                'status' => 302,
                'location' => is_string($location) ? $location : '',
            ];
        }

        if ($response->status() >= 400) {
            $response->throw();
        }

        return [
            'status' => $response->status(),
            'data' => $response->json(),
        ];
    }

    public function paymentCheckStatus(string $paymentId, ?string $jwtToken = null): array
    {
        $response = $this->waitForResponse(
            $this->request($jwtToken)->get('/payment/check-status', [
                'paymentID' => $paymentId,
            ])
        )->throw();

        return $this->expectJsonArray($response);
    }

    public function paymentWebhookJson(array $payload, ?string $jwtToken = null): string
    {
        $response = $this->waitForResponse(
            $this->request($jwtToken)->post('/payment/webhook-json', $payload)
        )->throw();

        return $this->expectStringBodyOrJsonScalar($response);
    }

    private function request(?string $jwtToken): \Illuminate\Http\Client\PendingRequest
    {
        $req = Http::baseUrl($this->baseUrl)
            ->timeout((int) config('services.erp.timeout', 30))
            ->acceptJson()
            ->async(false);

        $verify = config('services.erp.verify_ssl');
        if ($verify !== null) {
            $req = $req->withOptions(['verify' => (bool) $verify]);
        }

        if ($jwtToken) {
            $jwtToken = trim($jwtToken);
            if (str_starts_with(strtolower($jwtToken), 'bearer ')) {
                $jwtToken = trim(substr($jwtToken, 7));
            }
            $req = $req->withToken($jwtToken);
        }

        return $req;
    }

    private function waitForResponse(Response|PromiseInterface $response): Response
    {
        if ($response instanceof PromiseInterface) {
            /** @var Response $response */
            $response = $response->wait();
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function expectJsonArray(Response $response): array
    {
        $json = $response->json();
        return is_array($json) ? $json : ['value' => $json];
    }

    private function expectStringBodyOrJsonScalar(Response $response): string
    {
        $body = trim((string) $response->body());
        if ($body !== '') {
            // If ERP returns JSON-encoded string, body might be quoted.
            $decoded = json_decode($body, true);
            if (is_string($decoded)) {
                return $decoded;
            }
            if (is_bool($decoded)) {
                return $decoded ? 'true' : 'false';
            }

            return $body;
        }

        $json = $response->json();
        if (is_string($json)) {
            return $json;
        }
        if (is_bool($json)) {
            return $json ? 'true' : 'false';
        }
        if (is_numeric($json)) {
            return (string) $json;
        }

        return '';
    }

    /**
     * @return resource
     */
    private function openUploadedFileStream(UploadedFile $file)
    {
        $path = $file->getPathname();
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException('Failed to open uploaded file');
        }

        return $handle;
    }
}

