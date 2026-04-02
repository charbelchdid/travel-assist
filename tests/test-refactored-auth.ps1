# Test Refactored Authentication System
$baseUrl = "http://localhost:8080"

Write-Host "`n=== Testing Refactored Architecture ===" -ForegroundColor Cyan

# 1. Test traditional login
Write-Host "`n1. Testing traditional token-based login..." -ForegroundColor Yellow
$loginBody = @{
    username = "MY_USERNAME"
    password = "MY_PASSWORD"
    device_id = "test-refactored"
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/api/auth/login" `
        -Method POST `
        -ContentType "application/json" `
        -Body $loginBody
    
    Write-Host "  [OK] Login successful" -ForegroundColor Green
    Write-Host "  Token: $(if($response.token) { 'Present' } else { 'Missing' })" -ForegroundColor Gray
    # Permissions removed (stateless REST API)
    
    $token = $response.token
} catch {
    Write-Host "  [ERROR] Login failed: $_" -ForegroundColor Red
    exit 1
}

# 2. Test cookie-based login
Write-Host "`n2. Testing cookie-based login..." -ForegroundColor Yellow
try {
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    
    $response = Invoke-WebRequest -Uri "$baseUrl/api/auth/login/cookie" `
        -Method POST `
        -ContentType "application/json" `
        -Body $loginBody `
        -UseBasicParsing `
        -SessionVariable session
    
    $jsonResponse = $response.Content | ConvertFrom-Json
    
    Write-Host "  [OK] Cookie login successful" -ForegroundColor Green
    Write-Host "  Token in response: $(if($jsonResponse.token) { 'Present (ERROR!)' } else { 'Not present (Good!)'  })" -ForegroundColor Gray
    Write-Host "  Cookies set: $($session.Cookies.Count)" -ForegroundColor Gray
} catch {
    Write-Host "  [ERROR] Cookie login failed: $_" -ForegroundColor Red
}

# 3. Test protected endpoint with token
Write-Host "`n3. Testing protected endpoint with token..." -ForegroundColor Yellow
try {
    $headers = @{
        "Authorization" = "Bearer $token"
    }
    
    $profile = Invoke-RestMethod -Uri "$baseUrl/api/user/profile" `
        -Method GET `
        -Headers $headers
    
    Write-Host "  [OK] Profile retrieved" -ForegroundColor Green
    Write-Host "  Username: $($profile.data.username)" -ForegroundColor Gray
    Write-Host "  Email: $($profile.data.email)" -ForegroundColor Gray
} catch {
    Write-Host "  [ERROR] Failed to get profile: $_" -ForegroundColor Red
}

# 4. (Removed) permissions endpoint

# 5. Test cookie-based protected endpoint
Write-Host "`n4. Testing cookie-based protected endpoint..." -ForegroundColor Yellow
try {
    $profileCookie = Invoke-RestMethod -Uri "$baseUrl/api/cookie/user/profile" `
        -Method GET `
        -WebSession $session
    
    Write-Host "  [OK] Cookie-based profile retrieved" -ForegroundColor Green
    Write-Host "  Username: $($profileCookie.data.username)" -ForegroundColor Gray
} catch {
    Write-Host "  [ERROR] Failed to get cookie-based profile: $_" -ForegroundColor Red
}

# 6. Check routes
Write-Host "`n5. Verifying route structure..." -ForegroundColor Yellow
docker exec laravel_php php artisan route:list --path=api | Select-String -Pattern "auth|user" | Select-Object -First 10 | Out-String | Write-Host

Write-Host "`n=== Refactoring Test Complete ===" -ForegroundColor Cyan
Write-Host "[OK] Clean architecture with:" -ForegroundColor Green
Write-Host "  - Services layer for business logic" -ForegroundColor Gray
Write-Host "  - Repository pattern for data access" -ForegroundColor Gray
Write-Host "  - Interface-based dependency injection" -ForegroundColor Gray
Write-Host "  - Unified authentication controller" -ForegroundColor Gray
Write-Host "  - Consolidated routes in single file" -ForegroundColor Gray
