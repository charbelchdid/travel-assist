<?php

namespace App\Repositories\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProductRepositoryInterface extends BaseRepositoryInterface
{
    public function search(?string $search, int $perPage = 15): LengthAwarePaginator;

    /**
     * @return Collection<int, array{category: string, product_count: int}>
     */
    public function categoriesWithCounts(): Collection;
}

