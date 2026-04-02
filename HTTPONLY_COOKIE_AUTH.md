# HttpOnly Cookie Authentication Implementation

## Overview

This implementation uses **HttpOnly cookies** to store JWT tokens instead of returning them in the response body. This is the **most secure** approach for web applications as it prevents XSS (Cross-Site Scripting) attacks.

## Purpose

Explain how to use **cookie-based auth** in this repo (endpoints, frontend requirements, and local testing).

## How to use this project

See [`README.md`](README.md) for setup/running.

## How to develop

- Cookie behavior is implemented in `src/app/Services/AuthenticationService.php` (cookie creation)
- Cookie-protected routes are under `src/routes/api.php` in the `/api/cookie/*` group
- Middleware: `src/app/Http/Middleware/JwtAuthentication.php` (unified cookie + bearer auth)

## Security Benefits

### Traditional Approach (Token in Response) ❌
```javascript
// Token exposed to JavaScript
localStorage.setItem('token', response.token);
// Vulnerable to XSS attacks
```

### HttpOnly Cookie Approach ✅
```javascript
// Token NOT accessible to JavaScript
// Automatically sent with requests
// Protected from XSS attacks
```

## Implementation Details

### 1. Endpoints and routes

- **Login (cookie)**: `POST /api/auth/login/cookie`
  - Alternative: `POST /api/auth/login` with header `X-Auth-Type: cookie`
- **Check**: `GET /api/auth/check`
- **Cookie-protected resources**: `/api/cookie/*` (uses `jwt.auth` middleware)

### 2. How It Works

#### Login Flow
```mermaid
graph LR
    A[Client Login] --> B[Laravel API]
    B --> C[External Auth]
    C --> D[JWT Token]
    D --> E[Set HttpOnly Cookie]
    E --> F[Response WITHOUT Token]
    F --> G[Client Gets Cookie]
    
    style E fill:#9f9,stroke:#333,stroke-width:2px
    style F fill:#ff9,stroke:#333,stroke-width:2px
```

#### Request Flow
```mermaid
graph LR
    A[Client Request] --> B[Cookie Sent Automatically]
    B --> C[Laravel Middleware]
    C --> D[Extract Token from Cookie]
    D --> E[Validate Token]
    E --> F[Process Request]
    
    style B fill:#9f9,stroke:#333,stroke-width:2px
    style D fill:#ff9,stroke:#333,stroke-width:2px
```

### 3. Cookie Configuration

The JWT token is stored with these settings:
```php
Cookie::make(
    'jwt_token',           // Name
    $jwtToken,            // Value
    15 * 60,             // 15 hours expiration
    '/',                 // Path (all routes)
    null,                // Domain (current)
    true,                // Secure (HTTPS in production)
    true,                // HttpOnly (not accessible to JS)
    false,               // Raw (don't encrypt)
    'strict'             // SameSite policy
);
```

## Usage Examples

### Frontend Implementation (JavaScript)

#### Login Request
```javascript
// Fetch API (cookies handled automatically)
async function login(username, password) {
    const response = await fetch('http://localhost:8080/api/auth/login/cookie', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'include',  // IMPORTANT: Include cookies
        body: JSON.stringify({
            username: 'MY_USERNAME',
            password: 'MY_PASSWORD',
            device_id: 'web-app'
        })
    });
    
    const data = await response.json();
    
    if (data.success) {
        // No token in response!
        // Cookie is automatically set by browser
        console.log('Login successful!');
        console.log('User:', data.user);
        
        // Check if authenticated (using non-HttpOnly cookie)
        const isAuth = document.cookie.includes('is_authenticated=true');
        console.log('Authenticated:', isAuth);
    }
}
```

#### Protected Requests (Cookie sent automatically)
```javascript
// No need to manually add Authorization header!
async function getUserProfile() {
    const response = await fetch('http://localhost:8080/api/cookie/user/profile', {
        method: 'GET',
        credentials: 'include'  // IMPORTANT: Include cookies
    });
    
    if (response.ok) {
        return await response.json();
    } else if (response.status === 401) {
        // Token expired or missing
        window.location.href = '/login';
    }
}
```

### Optional: `pageCode` header authorization gate (protected routes)

If you include a `pageCode` header on a **protected** endpoint (including `/api/cookie/*`), the `jwt.auth` middleware will call ERP `/admin/mvp/isAuthorized` to decide allow/deny.

- Example header: `pageCode: Permissions`
- If ERP returns `false` → `403 Not authorized`

#### Axios Configuration
```javascript
// Configure Axios to always include cookies
axios.defaults.withCredentials = true;

// Login
axios.post('http://localhost:8080/api/auth/login/cookie', {
    username: 'MY_USERNAME',
    password: 'MY_PASSWORD'
});

// Protected requests (cookie sent automatically)
axios.get('http://localhost:8080/api/cookie/user/profile');
```

#### Logout
```javascript
async function logout() {
    const response = await fetch('http://localhost:8080/api/auth/logout', {
        method: 'POST',
        credentials: 'include'
    });
    
    if (response.ok) {
        // Cookies are cleared by server
        window.location.href = '/login';
    }
}
```

### Check Authentication Status

Since the JWT token is HttpOnly, JavaScript can't read it directly. We provide a companion cookie:

```javascript
function isAuthenticated() {
    // Check the non-HttpOnly cookie
    return document.cookie.includes('is_authenticated=true');
}

// Or call the check endpoint
async function checkAuthStatus() {
    try {
        const response = await fetch('http://localhost:8080/api/auth/check', {
            credentials: 'include'
        });
        return response.ok;
    } catch {
        return false;
    }
}
```

## CORS Configuration

For cookies to work cross-origin, you must configure CORS properly:

### Laravel Configuration (`config/cors.php`)
```php
'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],
'supports_credentials' => true,  // MUST be true
```

### Frontend Requirements
```javascript
// Always include credentials
fetch(url, {
    credentials: 'include'  // or 'same-origin' for same domain
});

// Axios
axios.defaults.withCredentials = true;
```

## Environment Variables

Add to `.env`:
```env
# Frontend URL for CORS
FRONTEND_URL=http://localhost:3000

# Cookie settings (optional)
SESSION_SECURE_COOKIE=true  # Use HTTPS in production
SESSION_SAME_SITE=strict    # Prevent CSRF
```

## Testing with PowerShell

```powershell
# Login (cookie will be stored)
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$loginBody = @{
    username = "MY_USERNAME"
    password = "MY_PASSWORD"
    device_id = "powershell"
} | ConvertTo-Json

$loginResponse = Invoke-RestMethod -Method Post `
    -Uri "http://localhost:8080/api/auth/login/cookie" `
    -Body $loginBody `
    -ContentType "application/json" `
    -SessionVariable session

# Use same session for protected requests (cookie included automatically)
$profile = Invoke-RestMethod -Method Get `
    -Uri "http://localhost:8080/api/cookie/user/profile" `
    -WebSession $session

Write-Host "User: $($profile.data.username)"
```

## Testing with Postman

1. **Enable Cookies**: Settings → Cookies → Enable
2. **Login**: POST to `/api/auth/login/cookie`
3. **Check Cookies**: Cookies tab will show `jwt_token` and `is_authenticated`
4. **Protected Requests**: Cookies sent automatically

## Migration from Token-in-Response to HttpOnly Cookies

### Step 1: Enable Both Approaches (Transition Period)
- Keep existing `/api/*` routes with token in response
- Add cookie auth (login via `/api/auth/login/cookie`, protected endpoints under `/api/cookie/*`)

### Step 2: Update Frontend
```javascript
// Old approach
const token = response.token;
localStorage.setItem('token', token);

// New approach
// No token handling needed - cookies automatic!
```

### Step 3: Update API Calls
```javascript
// Old approach
fetch(url, {
    headers: {
        'Authorization': `Bearer ${token}`
    }
});

// New approach
fetch(url, {
    credentials: 'include'
});
```

### Step 4: Deprecate Old Approach
After all clients updated, remove token-in-response endpoints.

## Security Comparison

| Aspect | Token in Response | HttpOnly Cookie |
|--------|------------------|-----------------|
| XSS Protection | ❌ Vulnerable | ✅ Protected |
| CSRF Protection | ✅ Not vulnerable | ⚠️ Need CSRF token or SameSite |
| JavaScript Access | ✅ Can read/manipulate | ❌ Cannot access |
| Storage Location | localStorage/Memory | Browser Cookie Jar |
| Sent Automatically | ❌ Must add to headers | ✅ Automatic |
| Cross-Domain | ✅ Easy | ⚠️ Needs CORS config |
| Mobile Apps | ✅ Works well | ⚠️ More complex |
| Logout | Client-side only | Server can invalidate |

## Best Practices

### 1. Use HTTPS in Production
```php
// In production
Cookie::make('jwt_token', $token, $minutes, '/', null, true, true);
//                                                      ^^^^
//                                                      Secure flag
```

### 2. Set SameSite Policy
```php
Cookie::make('jwt_token', $token, $minutes, '/', null, true, true, false, 'strict');
//                                                                         ^^^^^^^^
//                                                                         SameSite
```

### 3. Implement CSRF Protection
For state-changing operations, add CSRF tokens or use double-submit cookies.

### 4. Token Rotation
Implement token refresh to rotate tokens periodically:
```javascript
// Call refresh endpoint before token expires
setInterval(async () => {
    await fetch('/api/auth/refresh', {
        method: 'POST',
        credentials: 'include'
    });
}, 14 * 60 * 60 * 1000); // Every 14 hours
```

### 5. Secure Cookie Attributes
```php
// Production settings
'secure' => env('APP_ENV') === 'production',
'httponly' => true,
'samesite' => 'strict',
```

## Troubleshooting

### Issue: Cookies Not Being Set
- Check CORS configuration (`supports_credentials` must be `true`)
- Verify frontend sends `credentials: 'include'`
- Check browser console for warnings

### Issue: Cookies Not Sent with Requests
- Ensure `credentials: 'include'` in fetch
- Check cookie domain matches request domain
- Verify cookie hasn't expired

### Issue: CORS Errors
- Set specific origin (not `*`) when using credentials
- Ensure `supports_credentials` is true in Laravel

### Issue: Can't Read Cookie in JavaScript
- That's the point! HttpOnly cookies can't be read by JS
- Use the `is_authenticated` cookie or check endpoint

## Summary

HttpOnly cookie authentication provides:
- ✅ **Maximum Security**: Protected from XSS attacks
- ✅ **Automatic Handling**: Browser manages cookies
- ✅ **Server Control**: Can invalidate sessions server-side
- ✅ **No Token Management**: No need to store/retrieve tokens in JS
- ⚠️ **CORS Complexity**: Requires proper configuration
- ⚠️ **Mobile Limitations**: Better for web apps than native mobile

This is the **recommended approach** for web applications where security is paramount!
