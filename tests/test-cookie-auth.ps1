# Test HttpOnly Cookie Authentication
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "HttpOnly Cookie Authentication Test" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

$baseUrl = "http://localhost:8080/api/cookie"

# Create a session to store cookies
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

# Step 1: Login (Cookie will be set automatically)
Write-Host "`nStep 1: Login with Cookie Authentication" -ForegroundColor Yellow

$loginBody = @{
    username = "MY_USERNAME"
    password = "MY_PASSWORD"
    device_id = "powershell-test"
} | ConvertTo-Json

try {
    $loginResponse = Invoke-RestMethod -Method Post `
        -Uri "http://localhost:8080/api/auth/login/cookie" `
        -Body $loginBody `
        -ContentType "application/json" `
        -SessionVariable session `
        -UseBasicParsing
    
    Write-Host "SUCCESS: Login successful!" -ForegroundColor Green
    Write-Host "  Message: $($loginResponse.message)" -ForegroundColor Gray
    
    # Note: No token in response!
    if ($loginResponse.token) {
        Write-Host "WARNING: Token found in response (should not be there!)" -ForegroundColor Yellow
    } else {
        Write-Host "  SECURE: No token in response body (stored as HttpOnly cookie)" -ForegroundColor Green
    }
    
    # Check cookies in session
    Write-Host "`nCookies set by server:" -ForegroundColor White
    foreach ($cookie in $session.Cookies.GetCookies($baseUrl)) {
        Write-Host "  - $($cookie.Name): [HttpOnly=$($cookie.HttpOnly)]" -ForegroundColor Gray
    }
    
} catch {
    Write-Host "FAILED: Login error" -ForegroundColor Red
    exit
}

# Step 2: Check Authentication Status
Write-Host "`nStep 2: Check Authentication Status" -ForegroundColor Yellow

try {
    $checkResponse = Invoke-RestMethod -Method Get `
        -Uri "http://localhost:8080/api/auth/check" `
        -WebSession $session `
        -UseBasicParsing
    
    Write-Host "SUCCESS: Authentication verified" -ForegroundColor Green
    Write-Host "  Authenticated: $($checkResponse.authenticated)" -ForegroundColor Gray
    
} catch {
    Write-Host "FAILED: Not authenticated" -ForegroundColor Red
}

# Step 3: Access Protected Endpoint (Cookie sent automatically)
Write-Host "`nStep 3: Access Protected Endpoint with Cookie" -ForegroundColor Yellow

try {
    $profileResponse = Invoke-RestMethod -Method Get `
        -Uri "$baseUrl/user/profile" `
        -WebSession $session `
        -UseBasicParsing
    
    Write-Host "SUCCESS: Protected endpoint accessed" -ForegroundColor Green
    Write-Host "  Username: $($profileResponse.data.username)" -ForegroundColor Gray
    Write-Host "  Note: No Authorization header needed - cookie sent automatically!" -ForegroundColor Green
    
} catch {
    Write-Host "FAILED: Could not access protected endpoint" -ForegroundColor Red
}

# Step 4: Test Multiple Protected Endpoints
Write-Host "`nStep 4: Test Multiple Protected Endpoints" -ForegroundColor Yellow

$endpoints = @(
    "/products",
    "/products/categories/list"
)

foreach ($endpoint in $endpoints) {
    try {
        $response = Invoke-RestMethod -Method Get `
            -Uri "$baseUrl$endpoint" `
            -WebSession $session `
            -UseBasicParsing
        Write-Host "  SUCCESS: $endpoint accessible" -ForegroundColor Green
    } catch {
        Write-Host "  FAILED: $endpoint not accessible" -ForegroundColor Red
    }
}

# Step 5: Logout (Clear cookies)
Write-Host "`nStep 5: Logout (Clear Cookies)" -ForegroundColor Yellow

try {
    $logoutResponse = Invoke-RestMethod -Method Post `
        -Uri "http://localhost:8080/api/auth/logout" `
        -WebSession $session `
        -UseBasicParsing
    
    Write-Host "SUCCESS: Logged out, cookies cleared" -ForegroundColor Green
    
} catch {
    Write-Host "FAILED: Logout error" -ForegroundColor Red
}

# Step 6: Try to access protected endpoint after logout (should fail)
Write-Host "`nStep 6: Verify Logout (Should Fail)" -ForegroundColor Yellow

try {
    $failResponse = Invoke-RestMethod -Method Get `
        -Uri "$baseUrl/user/profile" `
        -WebSession $session `
        -UseBasicParsing
    Write-Host "ERROR: Should have been rejected after logout!" -ForegroundColor Red
} catch {
    Write-Host "SUCCESS: Correctly rejected after logout" -ForegroundColor Green
}

# Summary
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "HttpOnly Cookie Authentication Summary" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

Write-Host "`nSECURITY BENEFITS:" -ForegroundColor Yellow
Write-Host "  - Token NOT accessible to JavaScript (XSS protection)" -ForegroundColor White
Write-Host "  - Cookie sent automatically with requests" -ForegroundColor White
Write-Host "  - Server can clear auth cookies" -ForegroundColor White
Write-Host "  - No token management in frontend code" -ForegroundColor White

Write-Host "`nIMPLEMENTATION:" -ForegroundColor Yellow
Write-Host "  - Endpoint: /api/cookie/* (cookie-based)" -ForegroundColor White
Write-Host "  - Endpoint: /api/* (traditional token-based)" -ForegroundColor White
Write-Host "  - Both approaches available during transition" -ForegroundColor White

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Test Complete!" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
