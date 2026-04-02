<?php

namespace App\DTOs\Auth;

final readonly class AuthResultDTO
{
    /**
     * @param array<string, mixed>|null $user
     * @param array<string, mixed> $cookies
     */
    public function __construct(
        public bool $success,
        public ?string $token,
        public ?array $user,
        public ?string $expiresAt,
        public array $cookies = [],
    ) {}
}


