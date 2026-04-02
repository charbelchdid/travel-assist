# How JWT Token is Used After Authentication

## Overview
After successful login, the JWT token must be included in **every request** to protected endpoints. Here's the complete flow:

## Purpose

Explain how to use **Bearer token auth** in this repo (login → store token → call protected routes).

## How to use this project

See [`README.md`](README.md) for setup/running and a quick login example.

## How to develop

- Middleware: `src/app/Http/Middleware/JwtAuthentication.php`
- Routes: `src/routes/api.php` (protected group uses `jwt.auth`)

---

## 1. Token Reception After Login

### Login Response
```json
{
    "success": true,
    "token": "eyJhbGciOiJIUzUxMiJ9.eyJ1c2VyIjoiQW1yLm9iIi...",
    "token_type": "Bearer",
    "message": "Login successful",
    "user": { },
    "expires_at": "2026-01-07T02:00:00 +0400"
}
```

## 2. Client-Side Token Storage

### Where to Store the Token

#### Option A: localStorage (Common for SPAs)
```javascript
// After successful login
localStorage.setItem('jwt_token', response.token);

// Retrieve for use
const token = localStorage.getItem('jwt_token');
```

#### Option B: sessionStorage (More Secure, Expires with Tab)
```javascript
// Store
sessionStorage.setItem('jwt_token', response.token);

// Retrieve
const token = sessionStorage.getItem('jwt_token');
```

#### Option C: In-Memory (Most Secure)
```javascript
// Store in a variable/state
let authToken = response.token;

// Or in a state management system (Redux, Vuex, etc.)
store.dispatch(setAuthToken(response.token));
```

#### Option D: HttpOnly Cookie (Not applicable here since we return token in response)

## 3. Including Token in Subsequent Requests

### The Authorization Header Format
```
Authorization: Bearer <token>
```

**Important:** The word "Bearer" must be included before the token, followed by a space!

### Optional: `pageCode` header authorization gate (protected routes)

If you send a `pageCode` header on a **protected** endpoint, the `jwt.auth` middleware will call ERP `/admin/mvp/isAuthorized` using:

- `pageCode`: header value (example: `Permissions`)
- `apiUrl`: current request in the form `METHOD /path` (example: `GET /api/resources/dashboard-stats`)

Behavior:

- If ERP returns `true` → request continues (200/whatever the endpoint returns)
- If ERP returns `false` → `403 Not authorized`
- If ERP call fails → `502 Authorization check failed`

### Examples in Different Technologies

#### JavaScript/Fetch
```javascript
const token = localStorage.getItem('jwt_token');

fetch('http://localhost:8080/api/user/profile', {
    method: 'GET',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    }
})
.then(response => response.json())
.then(data => console.log(data));
```

#### Axios (JavaScript)
```javascript
// Set as default for all requests
axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

// Or per request
axios.get('http://localhost:8080/api/user/profile', {
    headers: {
        'Authorization': `Bearer ${token}`
    }
});
```

#### jQuery
```javascript
$.ajax({
    url: 'http://localhost:8080/api/user/profile',
    type: 'GET',
    headers: {
        'Authorization': `Bearer ${token}`
    },
    success: function(data) {
        console.log(data);
    }
});
```

#### PowerShell
```powershell
$token = "eyJhbGciOiJIUzUxMiJ9..."
$headers = @{
    "Authorization" = "Bearer $token"
}

Invoke-RestMethod -Uri "http://localhost:8080/api/user/profile" `
    -Headers $headers `
    -Method Get
```

#### cURL
```bash
curl -X GET http://localhost:8080/api/user/profile \
  -H "Authorization: Bearer eyJhbGciOiJIUzUxMiJ9..."
```

#### Postman
1. Go to the "Authorization" tab
2. Select "Bearer Token" as the type
3. Paste the token (without "Bearer" prefix - Postman adds it)

## 4. Server-Side Token Validation Flow

```mermaid
graph TD
    A[Client Request with Token] --> B[Laravel Receives Request]
    B --> C{Has Authorization Header?}
    C -->|No| D[Return 401 Unauthorized]
    C -->|Yes| E[Extract Bearer Token]
    E --> F[JwtAuthentication Middleware]
    F --> G{Valid Token Format?}
    G -->|No| H[Return 401 Invalid Token]
    G -->|Yes| I[Decode JWT Payload]
    I --> J{Token Expired?}
    J -->|Yes| K[Return 401 Token Expired]
    J -->|No| L[Attach User to Request]
    L --> M[Process Request]
    M --> N[Return Protected Resource]
    
    style A fill:#f9f,stroke:#333,stroke-width:2px
    style N fill:#9f9,stroke:#333,stroke-width:2px
    style D fill:#f99,stroke:#333,stroke-width:2px
    style H fill:#f99,stroke:#333,stroke-width:2px
    style K fill:#f99,stroke:#333,stroke-width:2px
```

## 5. How Laravel Processes the Token

### Step 1: Middleware Intercepts Request
File: `src/app/Http/Middleware/JwtAuthentication.php`

```php
public function handle(Request $request, Closure $next): Response
{
    // Extract token from Authorization header
    $authHeader = $request->header('Authorization');
    
    if (!$token) {
        return response()->json([
            'success' => false,
            'message' => 'Authorization token not provided'
        ], 401);
    }
```

### Step 2: Token Validation
```php
    // Decode JWT payload
    $tokenParts = explode('.', $token);
    if (count($tokenParts) !== 3) {
        return response()->json(['message' => 'Invalid token format'], 401);
    }
    
    // Decode payload (middle part)
    $payload = base64_decode($tokenParts[1]);
    $data = json_decode($payload, true);
```

### Step 3: Check Expiration
```php
    // Check if token is expired
    if (isset($data['exp']) && $data['exp'] < time()) {
        return response()->json(['message' => 'Token has expired'], 401);
    }
```

### Step 4: Attach User Data to Request
```php
    // Make user data available to controllers
    $request->merge(['jwt_payload' => $data]);
    $request->setUserResolver(function () use ($data) {
        return (object) $data;
    });
    
    return $next($request);  // Continue to the controller
```

## 6. Practical Examples

### Example 1: Complete Login and Use Flow

```javascript
// Step 1: Login
async function login(username, password) {
    const response = await fetch('http://localhost:8080/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            username: 'MY_USERNAME',
            password: 'MY_PASSWORD',
            device_id: 'web-app'
        })
    });
    
    const data = await response.json();
    if (data.success) {
        // Store the token
        localStorage.setItem('jwt_token', data.token);
        return data.token;
    }
}

// Step 2: Use token for protected request
async function getUserProfile() {
    const token = localStorage.getItem('jwt_token');
    
    if (!token) {
        throw new Error('Not authenticated');
    }
    
    const response = await fetch('http://localhost:8080/api/user/profile', {
        headers: {
            'Authorization': `Bearer ${token}`
        }
    });
    
    if (response.status === 401) {
        // Token expired or invalid
        localStorage.removeItem('jwt_token');
        window.location.href = '/login';
        return;
    }
    
    return await response.json();
}
```

### Example 2: Axios Interceptor (Auto-attach token)

```javascript
// Setup interceptor to automatically add token
axios.interceptors.request.use(
    config => {
        const token = localStorage.getItem('jwt_token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    error => Promise.reject(error)
);

// Now all requests automatically include the token
axios.get('/api/user/profile');  // Token included automatically
axios.post('/api/products', productData);  // Token included automatically
```

### Example 3: React Hook for Auth

```javascript
// Custom hook for authenticated requests
function useAuthenticatedRequest() {
    const makeRequest = async (url, options = {}) => {
        const token = localStorage.getItem('jwt_token');
        
        if (!token) {
            throw new Error('Not authenticated');
        }
        
        const response = await fetch(url, {
            ...options,
            headers: {
                ...options.headers,
                'Authorization': `Bearer ${token}`
            }
        });
        
        if (response.status === 401) {
            // Handle token expiration
            localStorage.removeItem('jwt_token');
            // Redirect to login or refresh token
        }
        
        return response;
    };
    
    return { makeRequest };
}

// Usage in component
function UserProfile() {
    const { makeRequest } = useAuthenticatedRequest();
    
    useEffect(() => {
        makeRequest('http://localhost:8080/api/user/profile')
            .then(res => res.json())
            .then(data => setUserData(data));
    }, []);
}
```

## 7. Common Issues and Solutions

### Issue 1: "Authorization token not provided"
**Cause:** Token not included in request headers
**Solution:** Ensure `Authorization: Bearer <token>` header is present

### Issue 2: "Token has expired"
**Cause:** JWT expiration time has passed
**Solution:** Redirect to login or implement token refresh

### Issue 3: "Invalid token format"
**Cause:** Token is malformed or "Bearer" prefix is missing
**Solution:** Check token format and include "Bearer " prefix

### Issue 4: CORS errors
**Cause:** Browser blocking cross-origin requests
**Solution:** Ensure Laravel CORS is configured properly

## 8. Token Lifecycle Management

### Login Flow
```
1. User enters credentials
2. App sends POST /api/auth/login
3. Receive JWT token
4. Store token in client storage
5. Set token in request headers for future calls
```

### Request Flow
```
1. Client prepares request
2. Attach "Authorization: Bearer <token>" header
3. Send request to protected endpoint
4. Server validates token
5. Server processes request if valid
6. Return response or 401 if invalid
```

### Logout Flow
```
1. Remove token from client storage
2. (Optional) Call POST /api/auth/logout
3. (Optional) Add token to server blacklist
4. Redirect to login page
```

### Refresh Flow

If the client implements refresh, call:

- `POST /api/auth/refresh` with `{ username, token, device_id? }`

## 9. Security Best Practices

1. **Never store sensitive data in JWT payload** - It's base64 encoded, not encrypted
2. **Use HTTPS in production** - Tokens can be intercepted over HTTP
3. **Implement token expiration** - Current tokens expire after ~15 hours
4. **Handle token refresh** - Don't force re-login unnecessarily
5. **Clear token on logout** - Remove from all storage locations
6. **Validate on every request** - Never trust client-side validation alone

## 10. Testing Token Usage

### PowerShell Test Script
```powershell
# Login and get token
$loginBody = @{
    username = "MY_USERNAME"
    password = "MY_PASSWORD"
    device_id = "test"
} | ConvertTo-Json

$loginResponse = Invoke-RestMethod -Method Post `
    -Uri "http://localhost:8080/api/auth/login" `
    -Body $loginBody `
    -ContentType "application/json"

$token = $loginResponse.token

# Use token for protected endpoint
$headers = @{
    "Authorization" = "Bearer $token"
}

$profile = Invoke-RestMethod -Method Get `
    -Uri "http://localhost:8080/api/user/profile" `
    -Headers $headers

Write-Host "User: $($profile.data.username)"
```

## Summary

The JWT token acts as a **temporary password** that:
1. **Proves authentication** without sending credentials each time
2. **Contains user information** without database queries
3. **Expires automatically** for security
4. **Must be included** in every protected request
5. **Is validated** by middleware before reaching controllers

Every protected API request follows this pattern:
```
Authorization: Bearer eyJhbGciOiJIUzUxMiJ9...
```

Without this header, protected endpoints will return `401 Unauthorized`.
