<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_products_crud_flow(): void
    {
        $jwt = $this->makeJwt();

        // Create
        $create = $this
            ->withToken($jwt)
            ->postJson('/api/products', [
                'name' => 'Widget Pro',
                'price' => 19.99,
                'stock' => 7,
                'category' => 'Gadgets',
                'description' => 'Test product',
            ]);

        $create->assertStatus(201);
        $create->assertJsonPath('success', true);
        $productId = (int) $create->json('data.id');
        $this->assertGreaterThan(0, $productId);

        // List
        $list = $this
            ->withToken($jwt)
            ->getJson('/api/products?per_page=15&search=Widget');
        $list->assertOk();
        $list->assertJsonPath('success', true);

        // Show
        $show = $this
            ->withToken($jwt)
            ->getJson('/api/products/' . $productId);
        $show->assertOk();
        $show->assertJsonPath('data.id', $productId);
        $show->assertJsonPath('data.name', 'Widget Pro');

        // Update
        $update = $this
            ->withToken($jwt)
            ->putJson('/api/products/' . $productId, [
                'stock' => 9,
            ]);
        $update->assertOk();
        $update->assertJsonPath('data.stock', 9);

        // Categories
        $cats = $this
            ->withToken($jwt)
            ->getJson('/api/products/categories/list');
        $cats->assertOk();
        $cats->assertJsonPath('success', true);

        // Delete
        $del = $this
            ->withToken($jwt)
            ->deleteJson('/api/products/' . $productId);
        $del->assertOk();
        $del->assertJsonPath('success', true);

        // Ensure gone
        $missing = $this
            ->withToken($jwt)
            ->getJson('/api/products/' . $productId);
        $missing->assertStatus(404);
    }
}

