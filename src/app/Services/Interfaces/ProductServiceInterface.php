<?php

namespace App\Services\Interfaces;

use App\Models\Product;

interface ProductServiceInterface
{
    /**
     * @return array{data: array<int, mixed>, meta: array<string, mixed>}
     */
    public function list(int $perPage = 15, ?string $search = null): array;

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Product;

    public function find(int $id): ?Product;

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): ?Product;

    public function delete(int $id): bool;

    /**
     * @return array<int, array{id: int, name: string, slug: string, product_count: int}>
     */
    public function categories(): array;
}

