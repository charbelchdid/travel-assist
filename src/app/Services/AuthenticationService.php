<?php

namespace App\Services;

use App\DTOs\Auth\AuthResultDTO;
use App\DTOs\Auth\LoginDTO;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Interfaces\AuthenticationServiceInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuthenticationService implements AuthenticationServiceInterface
{
    private Client $httpClient;
    private string $authBaseUrl;
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
        $this->httpClient = new Client([
            'timeout' => 30,
            'verify' => false, // For development only
        ]);
        $this->authBaseUrl = env('AUTH_BASE_URL', 'https://testbackerp.teljoy.io');
    }

    /**
     * Authenticate user with external service
     *
     * @param LoginDTO $dto
     * @return AuthResultDTO
     */
    public function authenticate(LoginDTO $dto): AuthResultDTO
    {
        $username = $dto->username;
        $password = $dto->password;
        $deviceId = $dto->deviceId;
        $useCookies = $dto->useCookies;

        // Encode credentials as required by external service
        $credentials = base64_encode($username . ':' . $password . ':' . $deviceId);
        
        try {
            $response = $this->httpClient->post(
                $this->authBaseUrl . '/public/login/jwt',
                [
                    'body' => '"' . $credentials . '"',
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Access-Control-Allow-Origin' => '*'
                    ],
                    'http_errors' => false
                ]
            );

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();

            if ($statusCode !== 200) {
                throw new \Exception('Authentication failed', $statusCode);
            }

            // Extract JWT token from response header
            $tokenFromHeader = $response->getHeader('token');
            $jwtToken = null;

            if (!empty($tokenFromHeader)) {
                $jwtToken = $tokenFromHeader[0];
                Log::info('JWT token retrieved from response header');
            } else {
                // Fallback to body if not in header
                $data = json_decode($responseBody, true);
                if (isset($data['token'])) {
                    $jwtToken = $data['token'];
                    Log::info('JWT token found in response body');
                }
            }

            if (!$jwtToken) {
                throw new \Exception('No JWT token received from auth service');
            }

            // Normalize: some upstreams return `token: Bearer <jwt>`.
            // We store/return the raw JWT only (our middleware + Http::withToken expect that).
            $jwtToken = $this->normalizeJwtToken($jwtToken);

            // Parse user data
            $userData = json_decode($responseBody, true);
            
            // Sync user to database
            DB::beginTransaction();
            try {
                $user = $this->userRepository->createOrUpdateFromExternalAuth($userData, $deviceId);
                
                // Log activity
                $this->userRepository->logActivity($user, 'login', 'User logged in', [
                    'device_id' => $deviceId,
                    'auth_type' => $useCookies ? 'cookie' : 'token'
                ]);
                
                DB::commit();
                
                Log::info('User synced to local database', [
                    'user_id' => $user->id,
                    'external_id' => $user->external_id,
                    'username' => $user->username
                ]);
                
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Failed to sync user to local database', [
                    'error' => $e->getMessage()
                ]);
                // Continue even if sync fails
            }
            
            // Prepare cookies if needed
            $cookies = [];
            if ($useCookies) {
                $expirationMinutes = 15 * 60; // 15 hours
                
                $cookies['jwt_token'] = Cookie::make(
                    'jwt_token',
                    $jwtToken,
                    $expirationMinutes,
                    '/',
                    null,
                    env('SESSION_SECURE_COOKIE', false),
                    true,  // HttpOnly
                    false,
                    'strict'
                );
                
                $cookies['is_authenticated'] = Cookie::make(
                    'is_authenticated',
                    'true',
                    $expirationMinutes,
                    '/',
                    null,
                    env('SESSION_SECURE_COOKIE', false),
                    false, // Not HttpOnly (JS accessible)
                    false,
                    'strict'
                );
            }
            
            return new AuthResultDTO(
                success: true,
                token: $useCookies ? null : $jwtToken, // Don't expose token if using cookies
                user: $userData['user'] ?? null,
                expiresAt: $userData['tokenExpirationDate'] ?? null,
                cookies: $cookies
            );
            
        } catch (GuzzleException $e) {
            Log::error('External auth service error', [
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Authentication service unavailable', 503);
        }
    }

    /**
     * Validate JWT token
     *
     * @param string $token
     * @return array|null
     */
    public function validateToken(string $token): ?array
    {
        try {
            // For development, decode without verifying signature
            $parts = explode('.', $token);
            
            if (count($parts) !== 3) {
                return null;
            }
            
            // Decode the payload
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            
            if (!$payload) {
                return null;
            }
            
            // Check expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return null;
            } elseif (isset($payload['tokenExpirationDate'])) {
                $expiry = strtotime($payload['tokenExpirationDate']);
                if ($expiry && $expiry < time()) {
                    return null;
                }
            }
            
            return $payload;
            
        } catch (\Exception $e) {
            Log::error('Token validation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Logout user
     *
     * @param User|null $user
     * @return bool
     */
    public function logout(?User $user = null): bool
    {
        if ($user) {
            $this->userRepository->logActivity($user, 'logout', 'User logged out');
        }
        
        // Clear cookies
        Cookie::queue(Cookie::forget('jwt_token'));
        Cookie::queue(Cookie::forget('is_authenticated'));
        
        return true;
    }

    /**
     * Extract token from request
     *
     * @param Request $request
     * @return string|null
     */
    public function extractTokenFromRequest(Request $request): ?string
    {
        // Check cookie first
        $token = $request->cookie('jwt_token');
        
        if ($token) {
            return $this->normalizeJwtToken($token);
        }
        
        // Check Authorization header
        $authHeader = $request->header('Authorization');
        
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return $this->normalizeJwtToken(substr($authHeader, 7));
        }
        
        return null;
    }

    public function isAuthorized(string $pageCode, string $apiUrl, ?string $jwtToken = null): bool
    {
        $baseUrl = (string) config('services.erp.base_url', env('AUTH_BASE_URL', $this->authBaseUrl));

        $req = Http::baseUrl($baseUrl)
            ->timeout((int) config('services.erp.timeout', 30))
            ->acceptJson()
            ->async(false);

        $verify = config('services.erp.verify_ssl');
        if ($verify !== null) {
            $req = $req->withOptions(['verify' => (bool) $verify]);
        }

        if ($jwtToken) {
            $req = $req->withToken($this->normalizeJwtToken($jwtToken));
        }

        $response = $req->get('/admin/mvp/isAuthorized', [
            'pageCode' => $pageCode,
            'apiUrl' => $apiUrl,
        ])->throw();

        $body = trim((string) $response->body());

        // ERP returns plain boolean (`true`/`false`) per the MVPController docs.
        if ($body === 'true' || $body === '1') {
            return true;
        }
        if ($body === 'false' || $body === '0' || $body === '') {
            return false;
        }

        // Fallback if it returns JSON boolean / string.
        $json = $response->json();
        if (is_bool($json)) {
            return $json;
        }
        if (is_string($json)) {
            return strtolower(trim($json)) === 'true';
        }

        return false;
    }

    private function normalizeJwtToken(string $token): string
    {
        $token = trim($token);
        if (str_starts_with(strtolower($token), 'bearer ')) {
            return trim(substr($token, 7));
        }
        return $token;
    }
}
