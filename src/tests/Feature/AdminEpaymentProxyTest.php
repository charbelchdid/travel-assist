<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminEpaymentProxyTest extends TestCase
{
    private function base64UrlEncode(string $data): string
    {
        // Keep padding because the middleware uses base64_decode() without restoring padding.
        return strtr(base64_encode($data), '+/', '-_');
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

    public function test_epayment_statuses_proxy_returns_success_payload(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/admin/epayment-transaction/statuses')) {
                return Http::response(['PENDING', 'SUCCESS'], 200);
            }

            return Http::response('not-faked', 500);
        });

        $jwt = $this->makeJwt();

        $response = $this
            ->withToken($jwt)
            ->getJson('/api/erp/epayment-transaction/statuses');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0', 'PENDING');
    }

    public function test_epayment_create_transaction_proxy_returns_302_with_location_when_erp_redirects(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/admin/epayment-transaction/create-transaction')) {
                return Http::response('', 302, ['Location' => 'https://provider-hosted-payment-page']);
            }

            return Http::response('not-faked', 500);
        });

        $jwt = $this->makeJwt();

        $response = $this
            ->withToken($jwt)
            ->postJson('/api/erp/epayment-transaction/create-transaction', [
                'entityType' => 'ORDER',
                'amount' => 150.75,
                'customParams' => ['cart_currency' => 'USD'],
            ]);

        $response->assertStatus(302);
        $response->assertHeader('Location', 'https://provider-hosted-payment-page');
    }

    public function test_epayment_create_transaction_proxy_returns_json_when_erp_returns_200(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/admin/epayment-transaction/create-transaction')) {
                return Http::response([
                    'providerPageLink' => null,
                    'transactionId' => 123,
                ], 200);
            }

            return Http::response('not-faked', 500);
        });

        $jwt = $this->makeJwt();

        $response = $this
            ->withToken($jwt)
            ->postJson('/api/erp/epayment-transaction/create-transaction', [
                'entityType' => 'ORDER',
                'amount' => 150.75,
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.transactionId', 123);
    }
}

