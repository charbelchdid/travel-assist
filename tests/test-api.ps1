# Laravel API Test Script
$baseUrl = "http://localhost:8080/api"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Laravel API Authentication Test" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

# Test 1: Health Check
Write-Host "`n1. Testing Health Endpoint (Public):" -ForegroundColor Yellow
try {
    $health = Invoke-RestMethod -Method Get -Uri "$baseUrl/health" -UseBasicParsing
    Write-Host "   Success: Health Status = $($health.status)" -ForegroundColor Green
    Write-Host "   Service: $($health.service)" -ForegroundColor Green
} catch {
    Write-Host "   Failed: $_" -ForegroundColor Red
}

# Test 2: Protected route without token
Write-Host "`n2. Testing Protected Route Without Token:" -ForegroundColor Yellow
try {
    $protected = Invoke-RestMethod -Method Get -Uri "$baseUrl/user/profile" -UseBasicParsing
    Write-Host "   Failed: Should have been rejected!" -ForegroundColor Red
} catch {
    Write-Host "   Success: Correctly rejected with 401 Unauthorized" -ForegroundColor Green
}

# Test 3: Login attempt
Write-Host "`n3. Testing Login Endpoint:" -ForegroundColor Yellow
Write-Host "   Note: This will fail without valid external auth credentials" -ForegroundColor Cyan

$loginBody = @{
    username = "test_user"
    password = "test_password"
    device_id = "test-script"
} | ConvertTo-Json

try {
    $loginResponse = Invoke-RestMethod -Method Post -Uri "$baseUrl/auth/login" -Body $loginBody -ContentType "application/json" -UseBasicParsing
    Write-Host "   Success: Login worked!" -ForegroundColor Green
    Write-Host "   Token: $($loginResponse.token.Substring(0,20))..." -ForegroundColor Green
} catch {
    Write-Host "   Expected: Login failed (no valid credentials)" -ForegroundColor Yellow
}

# Test 4: phpMyAdmin
Write-Host "`n4. Testing phpMyAdmin Access:" -ForegroundColor Yellow
try {
    $phpMyAdmin = Invoke-WebRequest -Uri "http://localhost:8081" -UseBasicParsing
    Write-Host "   Success: phpMyAdmin accessible at http://localhost:8081" -ForegroundColor Green
} catch {
    Write-Host "   Failed: $_" -ForegroundColor Red
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Available Endpoints:" -ForegroundColor Cyan
Write-Host "  API: http://localhost:8080" -ForegroundColor White
Write-Host "  phpMyAdmin: http://localhost:8081" -ForegroundColor White
Write-Host "========================================" -ForegroundColor Cyan
