<?php

namespace App\Services\Interfaces;

use App\DTOs\Auth\AuthResultDTO;
use App\DTOs\Auth\LoginDTO;
use App\Models\User;

interface AuthenticationServiceInterface
{
    /**
     * Authenticate user with external service
     *
     * @param LoginDTO $dto
     * @return AuthResultDTO
     */
    public function authenticate(LoginDTO $dto): AuthResultDTO;

    /**
     * Validate JWT token
     *
     * @param string $token
     * @return array|null Decoded token payload
     */
    public function validateToken(string $token): ?array;

    /**
     * Logout user
     *
     * @param User|null $user
     * @return bool
     */
    public function logout(?User $user = null): bool;

    /**
     * Extract token from request (header or cookie)
     *
     * @param \Illuminate\Http\Request $request
     * @return string|null
     */
    public function extractTokenFromRequest(\Illuminate\Http\Request $request): ?string;

    /**
     * Check if user is authorized to access an API url (ERP authorization gateway).
     */
    public function isAuthorized(string $pageCode, string $apiUrl, ?string $jwtToken = null): bool;
}
