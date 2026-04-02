# User Synchronization Documentation

## Overview

The Laravel API application now automatically synchronizes user data from the external authentication service to the local database. This provides better performance, enables local relationships, and allows for user-specific features while maintaining authentication through the external service.

## Purpose

Explain what we store locally about users, and how that affects development (services, middleware, and DB).

## How to use this project

See [`README.md`](README.md) for setup/running, and [`DATABASE_AUTHENTICATION.md`](DATABASE_AUTHENTICATION.md) for the “what’s stored” breakdown.

## How to develop

- Sync logic: `src/app/Services/AuthenticationService.php`
- Persistence: `src/app/Repositories/UserRepository.php`
- User schema: `src/database/migrations/*update_users_table_for_external_auth.php`

## How It Works

### 1. Authentication Flow with User Sync

When a user logs in:

1. **External Authentication**: Credentials are sent to `https://testbackerp.teljoy.io/public/login/jwt`
2. **JWT Token Retrieval**: The JWT token is extracted from the response header
3. **User Data Sync**: User information from the response is stored/updated in the local database
4. **Activity Logging**: Login activity is recorded for audit purposes
5. **Response**: Client receives the JWT token (either in response body or as HttpOnly cookie)

### 2. Database Schema

The `users` table has been extended with the following fields:

| Field | Type | Description |
|-------|------|-------------|
| `external_id` | bigint | Unique ID from external auth service |
| `username` | string | Login username from external service |
| `first_name` | string | User's first name |
| `last_name` | string | User's last name |
| `phone` | string | Phone number |
| `is_admin` | boolean | Admin status flag |
| `role` | string | User role (admin, manager, user) |
| `department` | string | Department name |
| `branch_id` | integer | Branch identifier |
| `branch_name` | string | Branch name |
| `external_data` | json | Complete external auth response data |
| `last_login_at` | timestamp | Last successful login time |
| `device_id` | string | Device ID used for last login |

### 3. User Model Methods

#### `createOrUpdateFromExternalAuth(array $authData, ?string $deviceId): User`

Creates or updates a user record from external authentication data:

```php
// Example usage in AuthController
$user = User::createOrUpdateFromExternalAuth($userData, $deviceId);
```

#### `logActivity(string $action, string $description, array $metadata): void`

Logs user activities (currently to Laravel logs, can be extended to database):

```php
$user->logActivity('login', 'User logged in', ['ip' => $request->ip()]);
```

## Implementation Details

### AuthController Changes

The `AuthController@login` method now includes user synchronization:

```php
// After successful external authentication
try {
    DB::beginTransaction();
    
    $user = User::createOrUpdateFromExternalAuth($userData, $deviceId);
    
    Log::info('User synced to local database', [
        'user_id' => $user->id,
        'external_id' => $user->external_id,
        'username' => $user->username
    ]);
    
    $user->logActivity('login', 'User logged in', [
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'device_id' => $deviceId
    ]);
    
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    Log::error('Failed to sync user to local database', [
        'error' => $e->getMessage(),
        'user_data' => $userData
    ]);
    // Continue with login even if local sync fails
}
```

### Middleware Updates

`JwtAuthentication` (unified cookie + bearer) now:

1. Extract user information from JWT
2. Load the user from the database if available
3. Attach the User model to the request

```php
// In middleware
$user = User::where('username', $decoded['user'])->first();

$request->setUserResolver(function () use ($user, $decoded) {
    return $user ?: (object) $decoded;
});
```

### Controller Updates

Controllers can now access the authenticated user as an Eloquent model:

```php
public function profile(Request $request): JsonResponse
{
    $user = $request->user();
    
    if ($user instanceof \App\Models\User) {
        // User is from database - full access to model
        $userData = [
            'id' => $user->id,
            'external_id' => $user->external_id,
            'username' => $user->username,
            // ... all user fields available
        ];
    } else {
        // Fallback to JWT data only
        $jwtPayload = $request->get('jwt_payload', []);
        // ... limited data from JWT
    }
}
```

## Benefits

1. **Performance**: No need to call external API for user data on every request
2. **Relationships**: Can establish Laravel relationships (e.g., user->orders, user->activities)
3. **Caching**: User data can be cached locally
4. **Audit Trail**: Login history and activities tracked in database
5. **Offline Features**: Some features can work even if external auth is temporarily unavailable
6. **Data Enrichment**: Can add local-only fields and preferences

## Testing User Sync

### Check Database Status

```bash
# View all users
docker exec laravel_php php artisan users:check

# View specific user
docker exec laravel_php php artisan users:check MY_USERNAME
```

### Test Script

```powershell
# Run the user sync test
.\tests\test-user-sync.ps1
```

This will:
1. Check initial database state
2. Perform login with real credentials
3. Verify user was created/updated in database
4. Test accessing protected endpoints
5. Verify subsequent logins update last_login_at

## Database Queries

### Check Users via Tinker

```bash
# Enter tinker
docker exec -it laravel_php php artisan tinker

# Count users
App\Models\User::count()

# Find user by username
App\Models\User::where('username', 'MY_USERNAME')->first()

# View user's external data
$user = App\Models\User::first();
print_r($user->external_data);

// (Permissions feature removed: no local permission fetching/caching)
```

## Security Considerations

1. **No Local Passwords**: Passwords are never stored locally - authentication always goes through external service
2. **Token Validation**: JWT tokens are still validated on each request
3. **Data Privacy**: Sensitive data in `external_data` field should be handled carefully
4. **Sync Failures**: Login continues even if database sync fails (graceful degradation)
5. **Data Consistency**: Users are updated on each login to keep data fresh

## Future Enhancements

1. **Activity Table**: Create dedicated `user_activities` table for better activity tracking
2. **Session Management**: Track active sessions in database
3. **Token Blacklisting**: Store revoked tokens for enhanced security
4. **Batch Sync**: Periodic sync of all users from external service
5. **Webhooks**: Real-time updates when user data changes in external system
6. **Role Mapping**: More sophisticated role and permission mapping
7. **Profile Photos**: Store user avatars locally for faster loading

## Troubleshooting

### User Not Syncing

1. Check Laravel logs: `docker exec laravel_php tail -f storage/logs/laravel.log`
2. Verify database connection: `docker exec laravel_php php artisan migrate:status`
3. Check user table structure: `docker exec laravel_php php artisan migrate:fresh`

### Duplicate User Issues

Users are uniquely identified by `external_id`. If duplicates occur:

```sql
-- Check for duplicates
SELECT external_id, COUNT(*) 
FROM users 
GROUP BY external_id 
HAVING COUNT(*) > 1;

-- Remove duplicates (keep newest)
DELETE u1 FROM users u1
INNER JOIN users u2 
WHERE u1.external_id = u2.external_id 
AND u1.id < u2.id;
```

### Permission Issues

This project no longer fetches or stores permissions (stateless REST API).
