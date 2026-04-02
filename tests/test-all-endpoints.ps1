# Comprehensive test script for all API endpoints
# Using HttpOnly cookie authentication

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Testing All API Endpoints" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

$baseUrl = "http://localhost:8080/api"
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

# Test 1: Health Check (Public)
Write-Host "`n[1/14] Testing Health Check (Public)..." -ForegroundColor Yellow
try {
    $health = Invoke-RestMethod -Method Get -Uri "$baseUrl/health" -UseBasicParsing
    Write-Host "  SUCCESS: Health check passed" -ForegroundColor Green
    Write-Host "    Status: $($health.status)" -ForegroundColor Gray
    Write-Host "    Auth Type: $($health.auth_type)" -ForegroundColor Gray
} catch {
    Write-Host "  FAILED: Health check failed" -ForegroundColor Red
}

# Test 2: Login
Write-Host "`n[2/14] Testing Login..." -ForegroundColor Yellow
$loginBody = @{
    username = "MY_USERNAME"
    password = "MY_PASSWORD"
    device_id = "test-script"
} | ConvertTo-Json

try {
    $loginResponse = Invoke-RestMethod -Method Post `
        -Uri "$baseUrl/auth/login" `
        -Body $loginBody `
        -ContentType "application/json" `
        -SessionVariable session `
        -UseBasicParsing
    
    Write-Host "  SUCCESS: Login successful" -ForegroundColor Green
    Write-Host "    Message: $($loginResponse.message)" -ForegroundColor Gray
    Write-Host "    User: $($loginResponse.user.fullName)" -ForegroundColor Gray
    
    # Check if token is NOT in response (should be in cookie only)
    if ($loginResponse.token) {
        Write-Host "  WARNING: Token found in response body!" -ForegroundColor Yellow
    } else {
        Write-Host "    SECURE: Token stored as HttpOnly cookie" -ForegroundColor Green
    }
} catch {
    Write-Host "  FAILED: Login failed" -ForegroundColor Red
    Write-Host "    Exiting..." -ForegroundColor Red
    exit
}

# Test 3: Check Authentication Status
Write-Host "`n[3/14] Testing Auth Check..." -ForegroundColor Yellow
try {
    $checkResponse = Invoke-RestMethod -Method Get `
        -Uri "$baseUrl/auth/check" `
        -WebSession $session `
        -UseBasicParsing
    
    Write-Host "  SUCCESS: Authentication verified" -ForegroundColor Green
    Write-Host "    Authenticated: $($checkResponse.authenticated)" -ForegroundColor Gray
} catch {
    Write-Host "  FAILED: Auth check failed" -ForegroundColor Red
}

# Test 4: Get User Profile
Write-Host "`n[4/14] Testing Get User Profile..." -ForegroundColor Yellow
try {
    $profileResponse = Invoke-RestMethod -Method Get `
        -Uri "$baseUrl/user/profile" `
        -WebSession $session `
        -UseBasicParsing
    
    Write-Host "  SUCCESS: User profile retrieved" -ForegroundColor Green
    Write-Host "    Username: $($profileResponse.data.username)" -ForegroundColor Gray
    Write-Host "    Role: $($profileResponse.data.role)" -ForegroundColor Gray
} catch {
    Write-Host "  FAILED: Could not get user profile" -ForegroundColor Red
}

# Test 5: Update User Profile
Write-Host "`n[5/14] Testing Update User Profile..." -ForegroundColor Yellow
$updateBody = @{
    first_name = "Amr"
    last_name = "Updated"
} | ConvertTo-Json

try {
    $updateResponse = Invoke-RestMethod -Method Put `
        -Uri "$baseUrl/user/profile" `
        -Body $updateBody `
        -ContentType "application/json" `
        -WebSession $session `
        -UseBasicParsing
    
    Write-Host "  SUCCESS: Profile updated" -ForegroundColor Green
    Write-Host "    Message: $($updateResponse.message)" -ForegroundColor Gray
} catch {
    Write-Host "  FAILED: Could not update profile" -ForegroundColor Red
}

# Test 6: Get Activity Log
Write-Host "`n[6/14] Testing Get Activity Log..." -ForegroundColor Yellow
try {
    $activityResponse = Invoke-RestMethod -Method Get `
        -Uri "$baseUrl/user/activity?limit=5" `
        -WebSession $session `
        -UseBasicParsing
    
    Write-Host "  SUCCESS: Activity log retrieved" -ForegroundColor Green
    Write-Host "    Activities: $($activityResponse.data.Count)" -ForegroundColor Gray
} catch {
    Write-Host "  FAILED: Could not get activity log" -ForegroundColor Red
}

# Test 7: Get Products List
Write-Host "`n[7/14] Testing Get Products..." -ForegroundColor Yellow
try {
    $productsResponse = Invoke-RestMethod -Method Get `
        -Uri "$baseUrl/products" `
        -WebSession $session `
        -UseBasicParsing
    
    Write-Host "  SUCCESS: Products retrieved" -ForegroundColor Green
    Write-Host "    Count: $($productsResponse.data.Count)" -ForegroundColor Gray
} catch {
    Write-Host "  FAILED: Could not get products" -ForegroundColor Red
}

# Test 8: Create Product
Write-Host "`n[8/14] Testing Create Product..." -ForegroundColor Yellow
$newProduct = @{
    name = "Test Product"
    price = 99.99
    stock = 10
    category = "Test"
    description = "Test product created by script"
} | ConvertTo-Json

try {
    $createResponse = Invoke-RestMethod -Method Post `
        -Uri "$baseUrl/products" `
        -Body $newProduct `
        -ContentType "application/json" `
        -WebSession $session `
        -UseBasicParsing
    
    Write-Host "  SUCCESS: Product created" -ForegroundColor Green
    Write-Host "    ID: $($createResponse.data.id)" -ForegroundColor Gray
    Write-Host "    Name: $($createResponse.data.name)" -ForegroundColor Gray
    
    $productId = $createResponse.data.id
} catch {
    Write-Host "  FAILED: Could not create product" -ForegroundColor Red
    $productId = 1
}

# Test 9: Get Single Product
Write-Host "`n[9/14] Testing Get Single Product..." -ForegroundColor Yellow
try {
    $singleProductResponse = Invoke-RestMethod -Method Get `
        -Uri "$baseUrl/products/$productId" `
        -WebSession $session `
        -UseBasicParsing
    
    Write-Host "  SUCCESS: Product details retrieved" -ForegroundColor Green
    Write-Host "    Name: $($singleProductResponse.data.name)" -ForegroundColor Gray
} catch {
    Write-Host "  FAILED: Could not get product" -ForegroundColor Red
}

# Test 10: Update Product
Write-Host "`n[10/14] Testing Update Product..." -ForegroundColor Yellow
$updateProduct = @{
    name = "Updated Product"
    price = 149.99
} | ConvertTo-Json

try {
    $updateProdResponse = Invoke-RestMethod -Method Put `
        -Uri "$baseUrl/products/$productId" `
        -Body $updateProduct `
        -ContentType "application/json" `
        -WebSession $session `
        -UseBasicParsing
    
    Write-Host "  SUCCESS: Product updated" -ForegroundColor Green
    Write-Host "    Message: $($updateProdResponse.message)" -ForegroundColor Gray
} catch {
    Write-Host "  FAILED: Could not update product" -ForegroundColor Red
}

# Test 11: Get Product Categories
Write-Host "`n[11/14] Testing Get Product Categories..." -ForegroundColor Yellow
try {
    $categoriesResponse = Invoke-RestMethod -Method Get `
        -Uri "$baseUrl/products/categories/list" `
        -WebSession $session `
        -UseBasicParsing
    
    Write-Host "  SUCCESS: Categories retrieved" -ForegroundColor Green
    Write-Host "    Count: $($categoriesResponse.data.Count)" -ForegroundColor Gray
} catch {
    Write-Host "  FAILED: Could not get categories" -ForegroundColor Red
}

# Test 12: Get Resources (Example)
Write-Host "`n[12/14] Testing Get Resources (Example)..." -ForegroundColor Yellow
try {
    $resourcesResponse = Invoke-RestMethod -Method Get `
        -Uri "$baseUrl/resources" `
        -WebSession $session `
        -UseBasicParsing
    
    Write-Host "  SUCCESS: Resources retrieved" -ForegroundColor Green
    Write-Host "    Count: $($resourcesResponse.data.Count)" -ForegroundColor Gray
} catch {
    Write-Host "  FAILED: Could not get resources" -ForegroundColor Red
}

# Test 13: Logout
Write-Host "`n[13/14] Testing Logout..." -ForegroundColor Yellow
try {
    $logoutResponse = Invoke-RestMethod -Method Post `
        -Uri "$baseUrl/auth/logout" `
        -WebSession $session `
        -UseBasicParsing
    
    Write-Host "  SUCCESS: Logged out" -ForegroundColor Green
    Write-Host "    Message: $($logoutResponse.message)" -ForegroundColor Gray
} catch {
    Write-Host "  FAILED: Could not logout" -ForegroundColor Red
}

# Try to access protected endpoint after logout (should fail)
Write-Host "`nVerifying Logout..." -ForegroundColor Yellow
try {
    $failTest = Invoke-RestMethod -Method Get `
        -Uri "$baseUrl/user/profile" `
        -WebSession $session `
        -UseBasicParsing
    
    Write-Host "  FAILED: Should not be able to access after logout!" -ForegroundColor Red
} catch {
    Write-Host "  SUCCESS: Correctly rejected after logout" -ForegroundColor Green
}

# Summary
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Test Summary" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "All endpoints have been tested with HttpOnly cookie authentication" -ForegroundColor Green
Write-Host "Authentication is working with credentials: MY_USERNAME / MY_PASSWORD" -ForegroundColor Green
Write-Host "`nEndpoint Structure:" -ForegroundColor Yellow
Write-Host "  - /api/auth/*     : Authentication endpoints" -ForegroundColor White
Write-Host "  - /api/user/*     : User management endpoints" -ForegroundColor White
Write-Host "  - /api/products/* : Product management endpoints" -ForegroundColor White
Write-Host "  - /api/resources/*: Example resource endpoints" -ForegroundColor White
Write-Host "  - /api/health     : Public health check" -ForegroundColor White
Write-Host "`n========================================" -ForegroundColor Cyan
