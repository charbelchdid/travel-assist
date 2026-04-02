<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use App\Services\Interfaces\AuthenticationServiceInterface;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class JwtAuthentication
{
    public function __construct(
        private readonly AuthenticationServiceInterface $authService,
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Unified auth: prefer HttpOnly cookie (browser), fallback to Bearer token (API clients).
        $token = $request->cookie('jwt_token') ?: $request->bearerToken();
        $tokenSource = $request->cookie('jwt_token') ? 'cookie' : ($request->bearerToken() ? 'bearer' : null);

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Some upstreams return/stored `Bearer <jwt>`; normalize for internal decoding and ERP calls.
        $token = trim((string) $token);
        if (str_starts_with(strtolower($token), 'bearer ')) {
            $token = trim(substr($token, 7));
        }

        try {
            // For development, we'll decode without verifying the signature
            // In production, you should verify with the proper secret key
            $parts = explode('.', $token);

            if (count($parts) !== 3) {
                throw new \Exception('Invalid token structure');
            }

            // Decode the payload (second part)
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

            if (!$payload) {
                throw new \Exception('Invalid token payload');
            }

            // Convert to object for consistency
            $decoded = json_decode(json_encode($payload));

            Log::info('JWT Auth Middleware - Token decoded', ['source' => $tokenSource, 'payload' => $payload]);

            // Check token expiration
            if (isset($decoded->exp)) {
                // exp is a Unix timestamp
                if ($decoded->exp < time()) {
                    Log::warning('JWT Auth Middleware - Token expired', ['exp' => $decoded->exp, 'current' => time()]);
                    throw new ExpiredException('Token has expired');
                }
            } elseif (isset($decoded->tokenExpirationDate)) {
                // Fallback to tokenExpirationDate if present
                $expiry = strtotime($decoded->tokenExpirationDate);
                if ($expiry && $expiry < time()) {
                    Log::warning('JWT Auth Middleware - Token expired (tokenExpirationDate)', ['exp' => $expiry, 'current' => time()]);
                    throw new ExpiredException('Token has expired');
                }
            }

            // Attach decoded token data to request
            // Don't use $request->merge() for objects (Symfony InputBag requires scalar/array).
            // Store payload on request attributes instead.
            $request->attributes->set('jwt_payload', $decoded);

            // Try to load user from database based on JWT data
            $user = null;
            if (isset($decoded->user)) {
                // The JWT contains username in the 'user' field
                // Handle case-insensitive username matching
                try {
                    $user = User::whereRaw('LOWER(username) = LOWER(?)', [$decoded->user])->first();
                    Log::info('JWT Auth Middleware - User lookup', ['username' => $decoded->user, 'found' => $user ? 'yes' : 'no']);
                } catch (\Throwable $e) {
                    // Don't fail auth if local user sync/schema isn't available (e.g. sqlite tests).
                    Log::warning('JWT Auth Middleware - User lookup failed', [
                        'username' => $decoded->user,
                        'error' => $e->getMessage(),
                    ]);
                    $user = null;
                }
            }

            // Set user resolver to return actual User model or JWT data
            $request->setUserResolver(function () use ($user, $decoded) {
                return $user ?: (object) $decoded;
            });

            Log::info('JWT Auth Middleware - Token validation successful');

            // Optional ERP authorization gate: if client provides pageCode, verify access for this API call.
            $pageCode = $request->header('pageCode')
                ?: $request->header('page-code')
                ?: $request->header('pagecode');

            if (is_string($pageCode) && trim($pageCode) !== '') {
                $apiUrl = $request->method() . ' ' . $request->getPathInfo();

                try {
                    $allowed = $this->authService->isAuthorized(
                        pageCode: trim($pageCode),
                        apiUrl: $apiUrl,
                        jwtToken: $token
                    );
                } catch (\Throwable $e) {
                    Log::error('JWT Auth Middleware - ERP authorization check failed', [
                        'error' => $e->getMessage(),
                        'pageCode' => $pageCode,
                        'apiUrl' => $apiUrl,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Authorization check failed'
                    ], 502);
                }

                if (!$allowed) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Not authorized'
                    ], 403);
                }
            }

        } catch (ExpiredException $e) {
            Log::error('JWT Auth Middleware - Token expired exception', ['error' => $e->getMessage()]);
            $response = response()->json([
                'success' => false,
                'message' => 'Token has expired'
            ], 401);
            // If auth came from cookies, clear them to avoid the browser repeatedly sending an expired token.
            if ($tokenSource === 'cookie') {
                return $response
                    ->withCookie(\Cookie::forget('jwt_token'))
                    ->withCookie(\Cookie::forget('is_authenticated'));
            }
            return $response;
        } catch (SignatureInvalidException $e) {
            Log::error('JWT Auth Middleware - Invalid signature exception', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid token signature'
            ], 401);
        } catch (\Exception $e) {
            Log::error('JWT Auth Middleware - General exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Token validation failed: ' . $e->getMessage()
            ], 401);
        }

        return $next($request);
    }
}
