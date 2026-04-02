<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Services\Interfaces\ProductServiceInterface;
use Illuminate\Support\Str;

class ProductService implements ProductServiceInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    public function list(int $perPage = 15, ?string $search = null): array
    {
        $paginator = $this->products->search($search, $perPage);

        return [
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'search' => $search ?? '',
            ],
        ];
    }

    public function create(array $data): Product
    {
        /** @var Product $product */
        $product = $this->products->create($data);
        return $product;
    }

    public function find(int $id): ?Product
    {
        $model = $this->products->find($id);
        return $model instanceof Product ? $model : null;
    }

    public function update(int $id, array $data): ?Product
    {
        $product = $this->find($id);
        if (!$product) {
            return null;
        }

        $product->fill($data);
        $product->save();

        return $product->refresh();
    }

    public function delete(int $id): bool
    {
        $product = $this->find($id);
        if (!$product) {
            return false;
        }

        return (bool) $product->delete();
    }

    public function categories(): array
    {
        $rows = $this->products->categoriesWithCounts();

        $out = [];
        $i = 1;
        foreach ($rows as $row) {
            $name = (string) ($row['category'] ?? '');
            if ($name === '') {
                continue;
            }

            $out[] = [
                'id' => $i++,
                'name' => $name,
                'slug' => Str::slug($name),
                'product_count' => (int) ($row['product_count'] ?? 0),
            ];
        }

        return $out;
    }
}

