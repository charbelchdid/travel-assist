<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Interfaces\ProductServiceInterface;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductServiceInterface $products,
    ) {
    }

    /**
     * Display a listing of products
     *
     * @return JsonResponse
     */
    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 15);
        $search = (string) $request->get('search', '');

        $result = $this->products->list($perPage, $search);

        return ApiResponse::success([
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * Store a newly created product
     *
     * @param StoreProductRequest $request
     * @return JsonResponse
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->products->create($request->validated());

        return ApiResponse::success([
            'message' => 'Product created successfully',
            'data' => $product,
        ], 201);
    }

    /**
     * Display the specified product
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $product = $this->products->find((int) $id);
        if (!$product) {
            return ApiResponse::error('Product not found', 404);
        }

        return ApiResponse::success(['data' => $product]);
    }

    /**
     * Update the specified product
     *
     * @param UpdateProductRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateProductRequest $request, $id): JsonResponse
    {
        $product = $this->products->update((int) $id, $request->validated());
        if (!$product) {
            return ApiResponse::error('Product not found', 404);
        }

        return ApiResponse::success([
            'message' => 'Product updated successfully',
            'data' => $product,
        ]);
    }

    /**
     * Remove the specified product
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $deleted = $this->products->delete((int) $id);
        if (!$deleted) {
            return ApiResponse::error('Product not found', 404);
        }

        return ApiResponse::success([
            'message' => 'Product deleted successfully',
            'deleted_id' => (int) $id,
        ]);
    }

    /**
     * Get product categories
     *
     * @return JsonResponse
     */
    public function categories(): JsonResponse
    {
        return ApiResponse::success([
            'data' => $this->products->categories(),
        ]);
    }
}
