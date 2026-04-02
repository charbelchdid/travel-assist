<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PageCodeAuthorizationTest extends TestCase
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

    public function test_protected_route_allows_when_pagecode_is_authorized(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/admin/mvp/isAuthorized')) {
                $data = $request->data();
                $pageCode = $data['pageCode'] ?? null;
                $apiUrl = $data['apiUrl'] ?? null;

                if ($pageCode === 'Permissions' && $apiUrl === 'GET /api/resources/dashboard-stats') {
                    return Http::response('true', 200);
                }

                return Http::response('false', 200);
            }

            return Http::response('not-faked', 500);
        });

        $jwt = $this->makeJwt();

        $response = $this
            ->withToken($jwt)
            ->withHeader('pageCode', 'Permissions')
            ->getJson('/api/resources/dashboard-stats');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_protected_route_blocks_when_pagecode_is_not_authorized(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/admin/mvp/isAuthorized')) {
                $data = $request->data();
                $pageCode = $data['pageCode'] ?? null;
                $apiUrl = $data['apiUrl'] ?? null;

                if ($pageCode === 'testPageCode' && $apiUrl === 'GET /api/resources/dashboard-stats') {
                    return Http::response('false', 200);
                }

                return Http::response('true', 200);
            }

            return Http::response('not-faked', 500);
        });

        $jwt = $this->makeJwt();

        $response = $this
            ->withToken($jwt)
            ->withHeader('pageCode', 'testPageCode')
            ->getJson('/api/resources/dashboard-stats');

        $response->assertStatus(403);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'Not authorized');
    }
}

