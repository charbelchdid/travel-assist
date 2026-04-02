# Laravel API Architecture Documentation

## Overview

This Laravel API follows a clean architecture pattern with clear separation of concerns. The codebase is organized into distinct layers that promote maintainability, testability, and scalability.

## Purpose

This document explains **how to build features in this repo** (where to put code, and which layer owns what).

## How to use this project

See [`README.md`](README.md) for setup/running. The Laravel app code lives under `src/`.

## How to develop (project conventions)

- **Controllers**: Thin, HTTP-only; call services and return responses
- **Requests**: Use `FormRequest` classes for validation (`app/Http/Requests/*`)
- **DTOs**: Use simple DTO objects to move structured data between layers (`app/DTOs/*`)
- **Responses**: Use `ApiResponse` (`app/Http/Responses/ApiResponse.php`) for consistent JSON envelopes
- **Services**: Business logic + orchestration (`app/Services/*`)
- **Repositories**: Data access; extend `BaseRepository` for shared CRUD (`app/Repositories/*`)

## Architecture Layers

```
┌─────────────────────────────────────────────────┐
│                   Controllers                    │
│         (Thin controllers, HTTP logic)          │
└─────────────────────────────────────────────────┘
                        ▼
┌─────────────────────────────────────────────────┐
│                    Services                      │
│          (Business logic, orchestration)         │
└─────────────────────────────────────────────────┘
                        ▼
┌─────────────────────────────────────────────────┐
│                  Repositories                    │
│            (Data access abstraction)             │
└─────────────────────────────────────────────────┘
                        ▼
┌─────────────────────────────────────────────────┐
│                     Models                       │
│              (Eloquent ORM entities)             │
└─────────────────────────────────────────────────┘
```

## Directory Structure

```
src/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── AuthController.php       # Handles authentication
│   │   │       ├── UserController.php       # User management endpoints
│   │   │       ├── ProductController.php    # Product management endpoints
│   │   │       └── TemporalExamplesController.php # Temporal example endpoints
│   │   ├── Requests/                        # FormRequest validation classes
│   │   └── Responses/                       # API response helpers (JSON envelopes)
│   │   └── Middleware/
│   │       └── JwtAuthentication.php        # JWT validation (supports bearer + cookie)
│   │
│   ├── DTOs/                                # Data Transfer Objects between layers
│   ├── Models/
│   │   └── User.php                         # User entity (synced from external auth)
│   │
│   ├── Repositories/
│   │   ├── Interfaces/
│   │   │   └── BaseRepositoryInterface.php  # Common repository contract
│   │   │   └── UserRepositoryInterface.php  # User repository contract
│   │   ├── BaseRepository.php               # Common repository implementation
│   │   └── UserRepository.php               # User data access implementation
│   │
│   ├── Services/
│   │   ├── Interfaces/
│   │   │   ├── AuthenticationServiceInterface.php  # Auth service contract
│   │   │   └── UserServiceInterface.php           # User service contract
│   │   ├── AuthenticationService.php              # Auth business logic
│   │   └── UserService.php                        # User business logic
│   │
│   ├── Temporal/                             # Temporal workflows + activities
│   │   ├── Activities/
│   │   └── Workflows/
│   │
│   └── Providers/
│       └── RepositoryServiceProvider.php    # DI container bindings
│
├── routes/
│   └── api.php                              # All API route definitions
│
├── config/
│   ├── cors.php                             # CORS (important for cookie auth)
│   └── temporal.php                          # Temporal SDK config (address, namespace, task queue)
│
└── bootstrap/
    ├── app.php                              # Application bootstrap
    └── providers.php                        # Service provider registration
```

## Layer Responsibilities

### 1. Controllers Layer
**Location:** `app/Http/Controllers/Api/`

**Responsibilities:**
- Handle HTTP requests and responses
- Input validation
- Call appropriate service methods
- Format responses
- NO business logic

**Example:**
```php
class AuthController extends Controller
{
    public function __construct(AuthenticationServiceInterface $authService)
    {
        $this->authService = $authService;
    }

    public function login(LoginRequest $request): JsonResponse
    {
        // Validation happens in the FormRequest
        $dto = LoginDTO::fromRequest($request);
        $result = $this->authService->authenticate($dto);

        return ApiResponse::success([
            'token' => $result->token,
            'user' => $result->user,
        ]);
    }
}
```

### 2. Services Layer
**Location:** `app/Services/`

**Responsibilities:**
- Business logic implementation
- Orchestration between repositories
- External API calls
- Complex calculations
- Transaction management

**Example:**
```php
class AuthenticationService implements AuthenticationServiceInterface
{
    public function authenticate(LoginDTO $dto): AuthResultDTO
    {
        // Call external auth service
        // Sync user to database
        // Return consolidated result
    }
}
```

### 3. Repositories Layer
**Location:** `app/Repositories/`

**Responsibilities:**
- Data access abstraction
- Database queries
- CRUD operations
- Data persistence logic
- NO business logic

**Example:**
```php
class UserRepository implements UserRepositoryInterface
{
    public function findByExternalId(int $externalId): ?User
    {
        return User::where('external_id', $externalId)->first();
    }
}
```

### Base Repository

All repositories should extend `BaseRepository` for shared functionality (query builder, CRUD, pagination). Add only **entity-specific** queries to the concrete repository.

### 4. Models Layer
**Location:** `app/Models/`

**Responsibilities:**
- Entity representation
- Database table mapping
- Relationships
- Attribute casting
- Simple accessors/mutators

## Dependency Injection

All dependencies are injected through interfaces, registered in `RepositoryServiceProvider`:

```php
// Repositories
$this->app->bind(UserRepositoryInterface::class, UserRepository::class);

// Services
$this->app->bind(AuthenticationServiceInterface::class, AuthenticationService::class);
$this->app->bind(UserServiceInterface::class, UserService::class);
```

## API Routes Organization

Routes are organized in `routes/api.php` with clear grouping:

```php
// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes
Route::middleware('jwt.auth')->group(function () {
    Route::prefix('user')->group(function () {
        Route::get('/profile', [UserController::class, 'profile']);
    });
});
```

## Authentication Flow

The application supports two authentication methods:

1. **Token-based:** JWT returned in response body
2. **Cookie-based:** JWT stored in HttpOnly cookie

Both use the same controllers and services, differentiated by route name or header.

## Best Practices

### 1. Interface-First Design
Always define interfaces before implementations:
- Improves testability
- Enables easy swapping of implementations
- Clear contracts between layers

### 2. Thin Controllers
Controllers should only:
- Validate input
- Call service methods
- Return formatted responses

### 3. Service Layer Pattern
All business logic lives in services:
- Reusable across different controllers
- Easier to test
- Clear separation of concerns

### 4. Repository Pattern
Data access is abstracted through repositories:
- Database queries isolated from business logic
- Easy to switch data sources
- Mockable for testing

### 5. Dependency Injection
All dependencies injected through constructor:
- No hard dependencies
- Testable with mocks
- Follows SOLID principles

## Testing Strategy

```
tests/
├── Unit/
│   ├── Services/          # Test business logic
│   └── Repositories/      # Test data access
├── Feature/
│   └── Controllers/       # Test API endpoints
└── Integration/
    └── External/          # Test external service integration
```

## Adding New Features

### Step 1: Define the Interface
```php
interface OrderServiceInterface {
    public function createOrder(array $data): Order;
}
```

### Step 2: Implement the Service
```php
class OrderService implements OrderServiceInterface {
    public function createOrder(array $data): Order {
        // Business logic here
    }
}
```

### Step 3: Register in Provider
```php
$this->app->bind(OrderServiceInterface::class, OrderService::class);
```

### Step 4: Create Controller
```php
class OrderController extends Controller {
    public function __construct(OrderServiceInterface $orderService) {
        $this->orderService = $orderService;
    }
}
```

### Step 5: Add Routes
```php
Route::apiResource('orders', OrderController::class);
```

## Environment Configuration

Key environment variables:
```env
AUTH_BASE_URL=https://testbackerp.teljoy.io
FRONTEND_URL=http://localhost:3000
SESSION_SECURE_COOKIE=false
TEMPORAL_ADDRESS=temporal:7233
TEMPORAL_NAMESPACE=default
TEMPORAL_TASK_QUEUE=laravel-template
```

## Security Considerations

1. **JWT Storage:** HttpOnly cookies for web, bearer tokens for APIs
2. **Input Validation:** All inputs validated at controller level
3. **SQL Injection:** Protected through Eloquent ORM
4. **CORS:** Configured for specific origins
5. **Rate Limiting:** Can be added via middleware

## Performance Optimization

1. **Eager Loading:** Use with() to prevent N+1 queries
2. **Caching:** Redis/Memcached for frequently accessed data
3. **Database Indexing:** Add indexes on frequently queried columns
4. **API Response Caching:** Cache GET requests where appropriate

## Monitoring and Logging

- All authentication events logged
- User activities tracked
- Error logging with context
- Performance metrics can be added

## Future Enhancements

1. **Event-Driven Architecture:** Add events and listeners
2. **Queue Processing:** Background jobs for heavy operations
3. **API Versioning:** Version routes for backward compatibility
4. **GraphQL Support:** Alternative to REST
5. **Microservices:** Split into smaller services as needed
