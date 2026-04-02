<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Responses\ApiResponse;
use App\DTOs\Auth\LoginDTO;
use App\Services\Interfaces\AuthenticationServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    private AuthenticationServiceInterface $authService;

    public function __construct(AuthenticationServiceInterface $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle login request
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $loginDto = LoginDTO::fromRequest($request);

        try {
            $authResult = $this->authService->authenticate($loginDto);

            $response = ApiResponse::success([
                'token' => $authResult->token, // Will be null for cookie auth
                'token_type' => $authResult->token ? 'Bearer' : null,
                'message' => 'Login successful',
                'user' => $authResult->user,
                'expires_at' => $authResult->expiresAt,
            ], 200);

            // Add cookies if using cookie auth
            if ($loginDto->useCookies && !empty($authResult->cookies)) {
                foreach ($authResult->cookies as $cookie) {
                    $response = $response->withCookie($cookie);
                }
            }

            return $response;

        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;

            // Special handling for OTP requirement
            if ($e->getMessage() === 'OTP_REQUIRED') {
                return ApiResponse::error('OTP verification required', 403, [
                    'requires_otp' => true,
                    'username' => $loginDto->username,
                ]);
            }

            return ApiResponse::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * Handle logout request
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authService->logout($user);

        return ApiResponse::success([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Validate token
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validateToken(Request $request): JsonResponse
    {
        $token = $this->authService->extractTokenFromRequest($request);

        if (!$token) {
            return ApiResponse::error('No token provided', 400, [
                'valid' => false,
            ]);
        }

        $payload = $this->authService->validateToken($token);

        if (!$payload) {
            return ApiResponse::error('Invalid or expired token', 401, [
                'valid' => false,
            ]);
        }

        return ApiResponse::success([
            'valid' => true,
            'payload' => $payload,
        ]);
    }

    /**
     * Check authentication status (for cookie auth)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkAuth(Request $request): JsonResponse
    {
        $token = $this->authService->extractTokenFromRequest($request);

        if (!$token) {
            return ApiResponse::error('Not authenticated', 200, [
                'authenticated' => false,
            ]);
        }

        $payload = $this->authService->validateToken($token);

        if (!$payload) {
            // Clear invalid cookies
            return ApiResponse::error('Invalid or expired token', 200, [
                'authenticated' => false,
            ])
            ->withCookie(\Cookie::forget('jwt_token'))
            ->withCookie(\Cookie::forget('is_authenticated'));
        }

        return ApiResponse::success([
            'authenticated' => true,
            'user' => $payload,
        ]);
    }
}
