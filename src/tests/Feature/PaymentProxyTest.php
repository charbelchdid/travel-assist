<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentProxyTest extends TestCase
{
    public function test_payment_check_status_proxy_returns_success_payload(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/payment/check-status')) {
                return Http::response([
                    'transactionStatus' => 'AUTHORIZED',
                    'uuid' => '7b6d3a2a-9b0f-4c3a-8a6c-1b2c3d4e5f60',
                    'provider' => 'PAYTABS',
                ], 200);
            }

            return Http::response('not-faked', 500);
        });

        $response = $this->getJson('/api/payment/check-status?paymentID=7b6d3a2a-9b0f-4c3a-8a6c-1b2c3d4e5f60');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.transactionStatus', 'AUTHORIZED');
        $response->assertJsonPath('data.provider', 'PAYTABS');
    }

    public function test_payment_webhook_json_proxy_returns_done_text(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/payment/webhook-json')) {
                return Http::response('done', 200);
            }

            return Http::response('not-faked', 500);
        });

        $response = $this->postJson('/api/payment/webhook-json', [
            'data' => [
                'reference' => '7b6d3a2a-9b0f-4c3a-8a6c-1b2c3d4e5f60',
            ],
        ]);

        $response->assertOk();
        $response->assertSeeText('done');
    }
}

