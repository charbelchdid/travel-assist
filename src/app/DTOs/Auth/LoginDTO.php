<?php

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\LoginRequest;

final readonly class LoginDTO
{
    public function __construct(
        public string $username,
        public string $password,
        public string $deviceId = 'web-app',
        public bool $useCookies = false,
    ) {}

    public static function fromRequest(LoginRequest $request): self
    {
        $useCookies =
            $request->route()?->getName() === 'auth.login.cookie' ||
            $request->header('X-Auth-Type') === 'cookie';

        return new self(
            username: $request->string('username')->toString(),
            password: $request->string('password')->toString(),
            deviceId: $request->string('device_id')->toString() ?: 'web-app',
            useCookies: $useCookies,
        );
    }
}


