# Laravel API with Custom JWT Authentication

A modern Laravel 12 API application with custom JWT authentication integrated with an external authentication service, running in Docker containers.

## Quick Reference

| Resource | Location/Command |
|----------|-----------------|
| API URL (Traditional) | http://localhost:8080/api |
| API URL (Cookie Auth) | http://localhost:8080/api/cookie |
| phpMyAdmin | http://localhost:8081 |
| Postman Collection | `Laravel-API-Collection.postman_collection.json` |
| Environment Docs | [`ENVIRONMENT.md`](ENVIRONMENT.md) |
| Database Auth Docs | [`DATABASE_AUTHENTICATION.md`](DATABASE_AUTHENTICATION.md) |
| Auth Success Info | [`AUTHENTICATION_SUCCESS.md`](AUTHENTICATION_SUCCESS.md) |
| HttpOnly Cookie Auth | [`HTTPONLY_COOKIE_AUTH.md`](HTTPONLY_COOKIE_AUTH.md) |
| JWT Token Usage | [`JWT_TOKEN_USAGE.md`](JWT_TOKEN_USAGE.md) |
| User Sync Docs | [`USER_SYNCHRONIZATION.md`](USER_SYNCHRONIZATION.md) |
| Temporal Docs | [`TEMPORAL.md`](TEMPORAL.md) |
| Temporal Examples Guide | [`TEMPORAL_EXAMPLES.md`](TEMPORAL_EXAMPLES.md) |
| Update Guide | [`update-postman-collection.md`](update-postman-collection.md) |
| Test Script | `.\tests\test-api.ps1` |
| Test User Sync | `.\tests\test-user-sync.ps1` |
| Browser Test | Open `tests\test-cookie-browser.html` in browser |
| Check Users | `docker exec laravel_php php artisan users:check` |
| View Logs | `docker exec laravel_php tail -f storage/logs/laravel.log` |
| Run Migrations | `docker exec laravel_php php artisan migrate` |
| Clear Cache | `docker exec laravel_php php artisan config:clear` |
| Generate API Docs | `.\tests\generate-api-docs.ps1` |
| Check Database | `.\tests\check-database.ps1` |
| View Routes | `docker exec laravel_php php artisan route:list` |

## Features

- **Laravel 12** - Latest version of Laravel framework
- **Custom JWT Authentication** - Integration with external auth service (https://testbackerp.teljoy.io)
- **Docker Environment** - Fully containerized development environment
- **MySQL 8.0** - Database with persistent storage
- **phpMyAdmin** - Database management GUI
- **RESTful API** - Well-structured API endpoints
- **CORS Support** - Configured for cross-origin requests

## Architecture

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Client    │────▶│    Nginx    │────▶│   PHP-FPM   │
└─────────────┘     └─────────────┘     └─────────────┘
                           │                    │
                           ▼                    ▼
                    ┌─────────────┐     ┌─────────────┐
                    │  phpMyAdmin │────▶│   MySQL     │
                    └─────────────┘     └─────────────┘
```

## Services

| Service | Port | Description |
|---------|------|-------------|
| API | 8080 | Main Laravel API |
| phpMyAdmin | 8081 | Database management interface |
| MySQL | 3306 | Database server |
| Temporal | 7233 | Temporal Server (workflow orchestration) |
| Temporal UI | 8233 | Temporal Web UI |

## Quick Start

### Prerequisites
- Docker Desktop installed
- Docker Compose installed
- PowerShell (Windows) or Bash (Linux/Mac)

### Installation

1. **Start Docker containers:**
```bash
docker-compose up -d
```

2. **Verify all services are running:**
```bash
docker ps
```

3. **Test the API:**
```bash
# Windows PowerShell
Invoke-RestMethod -Uri http://localhost:8080/api/health

# Linux/Mac
curl http://localhost:8080/api/health
```

## Authentication Flow

This application supports **two authentication approaches** with JWT tokens from an external service at `https://testbackerp.teljoy.io`:

### Approach 1: HttpOnly Cookie (More Secure) 🔐
- **Endpoint (protected resources)**: `/api/cookie/*`
- **Login endpoint**: `/api/auth/login/cookie` (or send header `X-Auth-Type: cookie` to `/api/auth/login`)
- **Token Storage**: HttpOnly cookie (not accessible to JavaScript)
- **Usage**: Cookie sent automatically with requests
- **Security**: Protected from XSS attacks
- **Documentation**: [`HTTPONLY_COOKIE_AUTH.md`](HTTPONLY_COOKIE_AUTH.md)

### Approach 2: Token in Response (Traditional) 
- **Endpoint**: `/api/*` (protected routes require `Authorization: Bearer <token>`)
- **Token Storage**: Client manages (localStorage, memory, etc.)
- **Usage**: Must include `Authorization: Bearer {token}` header
- **Security**: Vulnerable to XSS if stored in localStorage
- **Documentation**: [`JWT_TOKEN_USAGE.md`](JWT_TOKEN_USAGE.md)

### How Both Work

1. **Login Request**: Client sends username/password to login endpoint
2. **Credential Encoding**: Laravel encodes credentials as `base64(username:password:device_id)`
3. **External Auth**: Encoded string is sent to `https://testbackerp.teljoy.io/public/login/jwt`
4. **JWT Token**: External service returns JWT token in response header
5. **User Synchronization**: User data from external service is synced to local database
   - Creates new user record on first login
   - Updates existing user data on subsequent logins
   - Tracks last login time and device
   - See [`USER_SYNCHRONIZATION.md`](USER_SYNCHRONIZATION.md) for details
6. **Token Delivery**: 
   - Cookie approach: Set as HttpOnly cookie
   - Traditional: Returned in response body
7. **Protected Routes**: Token validated on each request, user loaded from database

**Important:** While authentication is **stateless** (JWT-based), user data is now **synchronized** to the local database for better performance and features. See [`USER_SYNCHRONIZATION.md`](USER_SYNCHRONIZATION.md) and [`DATABASE_AUTHENTICATION.md`](DATABASE_AUTHENTICATION.md) for details.

### JWT Token Structure

The external service returns the JWT token in the **response HEADER** (`token` header), not in the body.

The JWT token payload contains:
- `user` - Username (e.g., "MY_USERNAME")
- `device` - Device identifier
- `iat` - Issued at timestamp
- `exp` - Expiration timestamp

The response body contains additional user information:
- Full user profile with name, email
- Token expiration date
- User roles and department information

### Token Validation

The middleware (`JwtAuthentication.php`) validates tokens by:
1. Checking token format (three base64-encoded parts)
2. Decoding payload without verification (for development)
3. Checking expiration time
4. Attaching user data to request for use in controllers

**Note**: In production, implement proper JWT signature verification with the secret key from your auth service

### Authentication Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/api/auth/login` | Login with credentials | No |
| POST | `/api/auth/validate` | Validate JWT token | No |
| POST | `/api/auth/logout` | Logout (client-side) | Yes |

### Login Example

```bash
# PowerShell
$body = @{
    username = "MY_USERNAME"        # Test username (working)
    password = "MY_PASSWORD"     # Test password (working)
    device_id = "web-client"
} | ConvertTo-Json

$response = Invoke-RestMethod -Method Post `
    -Uri "http://localhost:8080/api/auth/login" `
    -Body $body `
    -ContentType "application/json"

# Use the token for protected routes
$headers = @{ Authorization = "Bearer $($response.token)" }
$profile = Invoke-RestMethod -Uri "http://localhost:8080/api/user/profile" -Headers $headers
```

## API Response Format

All API responses follow a consistent JSON structure:

### Success Response
```json
{
    "success": true,
    "message": "Operation successful",
    "data": { ... }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Error description",
    "error": "Detailed error information"
}
```

### Paginated Response
```json
{
    "success": true,
    "data": [ ... ],
    "meta": {
        "total": 100,
        "per_page": 15,
        "current_page": 1,
        "last_page": 7
    }
}
```

## API Endpoints

### Public Endpoints
- `GET /api/health` - Health check

### Protected Endpoints (Require JWT Token)

#### User Management
- `GET /api/user/profile` - Get user profile
- `PUT /api/user/profile` - Update profile
- `GET /api/user/activity` - View activity log
- `GET /api/myself` - Alternative “external-service-like” user info

#### Products
- `GET /api/products` - List products
- `POST /api/products` - Create product
- `GET /api/products/{id}` - Get product details
- `PUT /api/products/{id}` - Update product
- `DELETE /api/products/{id}` - Delete product
- `GET /api/products/categories/list` - List categories

**Note:** Products are backed by the local database table `products` (fields: `name`, `price`, `stock`, `category`, `description`). The categories endpoint aggregates categories from existing products.

#### Example Resources
- `GET /api/resources/dashboard-stats` - Example dashboard stats
- `GET /api/resources/notifications` - Example notifications list

#### Temporal Examples (Workflow Orchestration)
- All Temporal example endpoints are **JWT-protected** under: `/api/temporal/examples/*`
- See [`TEMPORAL.md`](TEMPORAL.md) for how to run the worker and call the endpoints.

#### ERP (MVPController integration)
- These endpoints are **JWT-protected** under: `/api/erp/*`
- They proxy calls to ERP `/admin/mvp/*` endpoints (see `erp/MVPController.md`)
- `GET /api/erp/is-authorized` - Checks page/API authorization (query: `pageCode`, `apiUrl`)
- `POST /api/erp/ocr/extract` - OCR extract (`multipart/form-data` with `file`)
- `POST /api/erp/ocr/detect-attachment-type` - Detect OCR attachment type (`multipart/form-data` with `file`)
- `POST /api/erp/ocr/get-text` - Get raw OCR text (`multipart/form-data` with `file`)
- `POST /api/erp/ocr/passport-service` - Passport OCR service (`multipart/form-data` with `file`)
- `POST /api/erp/send-message` - Send templated message (JSON body)

## Database Access

### phpMyAdmin
- URL: http://localhost:8081
- Username: `root`
- Password: `root_password`

### Direct MySQL Connection
- Host: `localhost`
- Port: `3306`
- Database: `laravel`
- Username: `laravel_user`
- Password: `laravel_password`

## Development

### Running Artisan Commands
```bash
docker exec laravel_php php artisan [command]

# Examples:
docker exec laravel_php php artisan migrate
docker exec laravel_php php artisan make:controller UserController
docker exec laravel_php php artisan route:list
```

### Installing Composer Packages
```bash
docker exec laravel_php composer require [package-name]
```

### Viewing Logs
```bash
# Laravel logs
docker exec laravel_php tail -f storage/logs/laravel.log

# Container logs
docker logs laravel_php
docker logs laravel_nginx
```

### Clearing Cache
```bash
docker exec laravel_php php artisan config:clear
docker exec laravel_php php artisan cache:clear
docker exec laravel_php php artisan route:clear
```

## Testing

### Using Postman Collection

A comprehensive Postman collection is included for testing all API endpoints:

1. **Import Collection**: 
   - Open Postman
   - Click "Import" → Select `Laravel-API-Collection.postman_collection.json`
   - The collection includes all endpoints with example requests

2. **Collection Features**:
   - Pre-configured environment variables (`baseUrl`, `authToken`)
   - Automatic token saving after successful login
   - Request descriptions and parameter documentation
   - Test scripts for response validation

3. **Using the Collection**:
   - Start with the "Login" request in the Authentication folder
   - Token will be automatically saved for subsequent requests
   - All protected endpoints will use the saved token

### Using PowerShell Script

Run the included test script:
```bash
# Windows PowerShell
.\tests\test-api.ps1

# Or test manually
Invoke-RestMethod -Uri http://localhost:8080/api/health
```

## Configuration

### Environment Variables

See [`ENVIRONMENT.md`](ENVIRONMENT.md) for complete documentation of all environment variables.

Key variables:
- `AUTH_BASE_URL` - External authentication service URL (https://testbackerp.teljoy.io)
- `ERP_BASE_URL` - ERP base URL for `/admin/mvp/*` endpoints (defaults to `AUTH_BASE_URL`)
- `ERP_TIMEOUT` - ERP HTTP timeout in seconds (default: 30)
- `ERP_VERIFY_SSL` - Verify SSL certificates for ERP calls (default: true)
- `DB_CONNECTION` - Database type (mysql)
- `DB_HOST` - Database host (mysql for Docker)
- `DB_DATABASE` - Database name (laravel)
- `JWT_SECRET_KEY` - JWT secret for production (optional in development)

### Custom Authentication

The authentication system is configured to work with an external JWT service. To customize:

1. Update `AUTH_BASE_URL` in `.env`
2. Modify `app/Http/Controllers/Api/AuthController.php` for login logic
3. Update JWT validation in `app/Http/Middleware/JwtAuthentication.php`
4. Add `JWT_SECRET_KEY` for production signature verification

## Troubleshooting

### Container Issues
```bash
# Restart all containers
docker-compose restart

# Rebuild containers
docker-compose down
docker-compose up -d --build

# View container logs
docker-compose logs -f
```

### Database Connection Issues
```bash
# Check database connection
docker exec laravel_php php artisan migrate:status

# Reset database
docker exec laravel_php php artisan migrate:fresh
```

### File Permission Issues
```bash
# Fix storage permissions
docker exec laravel_php chmod -R 777 storage bootstrap/cache
```

## Project Structure

```
laravel-template/
├── docker/                 # Docker configuration
│   ├── nginx/             # Nginx config
│   └── php/               # PHP config
├── src/                   # Laravel application
│   ├── app/
│   │   ├── Console/
│   │   │   └── Commands/  # Custom artisan commands
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   └── Api/   # API controllers
│   │   │   └── Middleware/ # Custom middleware
│   │   └── Models/        # Eloquent models (User with external sync)
│   ├── database/
│   │   └── migrations/    # Database migrations (including user sync)
│   ├── routes/
│   │   └── api.php        # API routes (includes cookie-auth group under /api/cookie/*)
│   └── config/
├── docker-compose.yml     # Docker orchestration
├── Dockerfile            # PHP container definition
├── Laravel-API-Collection.postman_collection.json  # Postman API collection
├── tests/               # Test scripts (PowerShell + browser test page)
│   ├── test-api.ps1                # API test script
│   ├── test-auth-simple.ps1        # Simple auth test script
│   ├── test-cookie-auth.ps1        # Cookie auth test script
│   ├── test-cookie-browser.html    # Browser test for cookie auth
│   ├── test-user-sync.ps1          # User synchronization test script
│   └── ...                         # Other test-*.ps1 scripts
├── tests/generate-api-docs.ps1  # API documentation generator
├── tests/check-database.ps1   # Database status checker
├── README.md            # Main documentation
├── ENVIRONMENT.md       # Environment variables documentation
├── DATABASE_AUTHENTICATION.md  # Database behavior during auth
├── HTTPONLY_COOKIE_AUTH.md # HttpOnly cookie implementation
├── JWT_TOKEN_USAGE.md   # JWT token usage guide
├── USER_SYNCHRONIZATION.md # User sync with external auth
├── AUTHENTICATION_SUCCESS.md  # Successful auth test documentation
├── API_ROUTES.md        # Auto-generated API routes documentation
└── update-postman-collection.md  # Guide for updating Postman collection
```

## Documentation Maintenance

### Important Guidelines

1. **README Updates**: Every functional change to the application must be documented in this README
2. **Postman Collection**: Keep `Laravel-API-Collection.postman_collection.json` updated with:
   - New endpoints added to the application
   - Changes to request/response formats
   - Updated authentication requirements
   - New environment variables

### Updating Documentation

When adding new features:
1. Update the relevant section in this README
2. Add new endpoints to the Postman collection (see `update-postman-collection.md` for guide)
3. Update API endpoint tables
4. Document any new environment variables
5. Add example requests/responses

### Developer Checklist for New Features

When adding a new API endpoint:
- [ ] Create controller method with proper validation
- [ ] Add route in `routes/api.php`
- [ ] Implement proper error handling
- [ ] Add endpoint to Postman collection
- [ ] Run `.\tests\generate-api-docs.ps1` to update API_ROUTES.md
- [ ] Update README.md with endpoint details
- [ ] Test with and without authentication
- [ ] Verify response format consistency
- [ ] Add any new environment variables to ENVIRONMENT.md
- [ ] Document any external service dependencies

### Automated Documentation

Use the documentation generator to keep API routes up-to-date:
```bash
# Generate API routes documentation
.\tests\generate-api-docs.ps1

# This creates/updates API_ROUTES.md with all current routes
```

### Current API Coverage

The Postman collection currently includes:
- **Authentication**: 4 endpoints (login, login/cookie, validate, logout)
- **User Management**: 4 endpoints (profile, update, password, activity)
- **Products**: 6 endpoints (list, create, show, update, delete, categories)
- **Resources**: 2 example endpoints
- **Health**: 1 endpoint
- **ERP (MVP)**: 6 endpoints (proxy to `/admin/mvp/*`)

## Security Considerations

### Current Implementation

- **JWT Authentication**: Tokens validated on each request
- **CORS Configuration**: Currently allows all origins (update for production)
- **Database Credentials**: Stored in environment variables
- **External Auth Service**: Credentials transmitted over HTTPS

### Production Recommendations

1. **JWT Security**:
   - Implement proper signature verification with secret key
   - Add token refresh mechanism
   - Implement token blacklisting for logout

2. **API Security**:
   - Enable HTTPS only
   - Implement rate limiting (Laravel Throttle)
   - Add API versioning
   - Configure CORS for specific domains
   - Add request validation and sanitization

3. **Infrastructure**:
   - Use secrets management system (not .env files)
   - Regular security updates for Docker images
   - Implement logging and monitoring
   - Use read-only filesystem where possible

4. **Authentication**:
   - Add multi-factor authentication
   - Implement session management
   - Add password complexity requirements
   - Log authentication attempts

## Changelog

### Version 1.3.1 (Current) - Stateless REST Cleanup
- **Removed permissions feature**: no permission fetching, caching, or permission endpoints
- **Removed server sessions usage**: API behavior is stateless (no `session()` storage)

### Version 1.2.0 - User Synchronization
- **Database Integration**: Users from external auth are synced to local database
- **User Model**: Extended with external auth fields
- **Activity Logging**: Track user login activities
- **Improved Performance**: User data loaded from database

### Version 1.1.0 - HttpOnly Cookie Authentication
- Added HttpOnly cookie authentication approach for enhanced security
- Dual authentication support (cookie-based and token-based)
- Cookie-protected endpoints are available under `/api/cookie/*`
- Comprehensive documentation for both approaches
- CORS configuration for cookie support

### Version 1.0.1
- Fixed JWT token extraction from response headers
- Successfully tested authentication with real credentials
- Updated documentation with token location details
- Working test credentials included in examples

### Version 1.0.0
- Initial Laravel 12 setup with Docker
- Custom JWT authentication with external service
- User management endpoints
- Product CRUD operations
- phpMyAdmin integration
- Postman collection for API testing

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Support

For issues or questions, please check the Laravel documentation at https://laravel.com/docs

## Contributing

When contributing to this project:
1. Update the README.md with any new functionality
2. Update the Postman collection with new endpoints
3. Follow Laravel coding standards
4. Add appropriate error handling
5. Include request validation# travel-assist
