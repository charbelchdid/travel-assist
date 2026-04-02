<?php

namespace App\Repositories;

use App\Models\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    public function search(?string $search, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->query()->orderByDesc('id');

        $search = is_string($search) ? trim($search) : null;
        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('category', 'like', '%' . $search . '%');
            });
        }

        return $query->paginate($perPage);
    }

    public function categoriesWithCounts(): Collection
    {
        /** @var Collection<int, array{category: string, product_count: int}> $rows */
        $rows = $this->query()
            ->selectRaw('category, COUNT(*) as product_count')
            ->groupBy('category')
            ->orderBy('category')
            ->get()
            ->map(fn ($row) => [
                'category' => (string) $row->category,
                'product_count' => (int) $row->product_count,
            ]);

        return $rows;
    }
}

