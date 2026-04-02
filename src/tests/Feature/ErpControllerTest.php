<?php

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthentication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ErpControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // These tests focus on ERP proxying behavior and outbound HTTP calls.
        // jwt.auth middleware is covered elsewhere; disabling here avoids needing a fully valid JWT payload format.
        $this->withoutMiddleware(JwtAuthentication::class);
    }

    private function makeJwt(array $payloadOverrides = []): string
    {
        $header = ['alg' => 'none', 'typ' => 'JWT'];
        $payload = array_merge([
            'user' => 'test.user',
            'iat' => time(),
            'exp' => time() + 3600,
        ], $payloadOverrides);

        $h = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $p = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));

        // Signature is ignored in this repo (dev decode only), but token must have 3 parts.
        return $h . '.' . $p . '.signature';
    }

    private function base64UrlEncode(string $data): string
    {
        // Keep padding because the middleware uses base64_decode() without restoring padding.
        return strtr(base64_encode($data), '+/', '-_');
    }

    public function test_is_authorized_true_proxies_to_erp(): void
    {
        Http::fake([
            '*' => Http::response('true', 200),
        ]);

        $jwt = $this->makeJwt();

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $jwt)
            ->getJson('/api/erp/is-authorized?pageCode=employee_dashboard&apiUrl=GET%20%2Fadmin%2Fpicklist%2Fpage%2Fsearch.*');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.authorized', true);

        Http::assertSent(function ($request) use ($jwt) {
            return $request->method() === 'GET'
                && str_contains($request->url(), '/admin/mvp/isAuthorized')
                && $request->hasHeader('Authorization', 'Bearer ' . $jwt);
        });
    }

    public function test_ocr_get_text_proxies_to_erp(): void
    {
        Http::fake([
            '*' => Http::response('Hello OCR', 200),
        ]);

        $jwt = $this->makeJwt();
        $file = UploadedFile::fake()->createWithContent('doc.jpg', 'fake');

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $jwt)
            ->post('/api/erp/ocr/get-text', ['file' => $file]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.text', 'Hello OCR');

        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/admin/mvp/OCRGetText'));
    }

    public function test_ocr_detect_attachment_type_proxies_to_erp(): void
    {
        // ERP might return a JSON string or plain string; support both.
        Http::fake([
            '*' => Http::response('"PASSPORT"', 200),
        ]);

        $jwt = $this->makeJwt();
        $file = UploadedFile::fake()->createWithContent('doc.jpg', 'fake');

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $jwt)
            ->post('/api/erp/ocr/detect-attachment-type', ['file' => $file]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.type', 'PASSPORT');

        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/admin/mvp/OCRDetectAttachmentType'));
    }

    public function test_ocr_extract_proxies_to_erp(): void
    {
        Http::fake([
            '*' => Http::response([
                'type' => 'PASSPORT',
                'subType' => null,
                'firstPhaseResult' => 'raw text',
                'result' => ['passportId' => ['value' => 'A123', 'confidence' => 0.9]],
                'attachment' => ['id' => 123],
                'rejectionReason' => null,
            ], 200),
        ]);

        $jwt = $this->makeJwt();
        $file = UploadedFile::fake()->createWithContent('doc.jpg', 'fake');

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $jwt)
            ->post('/api/erp/ocr/extract', ['file' => $file]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.type', 'PASSPORT');

        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/admin/mvp/OCRExtract'));
    }

    public function test_send_message_proxies_to_erp(): void
    {
        Http::fake([
            '*' => Http::response(['ok' => true], 200),
        ]);

        $jwt = $this->makeJwt();

        $payload = [
            'templateName' => 'SOME_TEMPLATE_CODE',
            'lang' => 'en',
            'target' => [
                [
                    'id' => -1,
                    'entityType' => 'ExternalTarget',
                    'receiverName' => 'ExternalTarget',
                    'mobileNumber' => '+971500000000',
                    'whatsappNumber' => '+971500000000',
                    'smsReceiverType' => null,
                ],
            ],
            'parameters' => [
                'name' => 'John',
            ],
        ];

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $jwt)
            ->postJson('/api/erp/send-message', $payload);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.ok', true);

        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/admin/mvp/SendMessage'));
    }
}

