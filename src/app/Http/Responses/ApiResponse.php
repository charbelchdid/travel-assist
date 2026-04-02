<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function success(array $payload = [], int $status = 200): JsonResponse
    {
        return response()->json(array_merge(['success' => true], $payload), $status);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function error(string $message, int $status = 400, array $payload = []): JsonResponse
    {
        return response()->json(array_merge(['success' => false, 'message' => $message], $payload), $status);
    }
}


