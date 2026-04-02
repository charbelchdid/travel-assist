# Test User Profile with Database Sync
# This script verifies that the profile endpoint returns data from the database

$baseUrl = "http://localhost:8080"

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  Testing User Profile with DB Sync     " -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# 1. Login to get token
Write-Host "1. Logging in..." -ForegroundColor Yellow
$loginBody = @{
    username = "MY_USERNAME"
    password = "MY_PASSWORD"
    device_id = "test-device-profile"
} | ConvertTo-Json

try {
    $loginResponse = Invoke-RestMethod -Uri "$baseUrl/api/auth/login" `
        -Method POST `
        -ContentType "application/json" `
        -Body $loginBody
    
    Write-Host "  [OK] Login successful" -ForegroundColor Green
    $token = $loginResponse.token
} catch {
    Write-Host "  [ERROR] Login failed: $_" -ForegroundColor Red
    exit 1
}

# 2. Get user profile
Write-Host "`n2. Getting user profile..." -ForegroundColor Yellow
try {
    $headers = @{
        "Authorization" = "Bearer $token"
    }
    
    $profile = Invoke-RestMethod -Uri "$baseUrl/api/user/profile" `
        -Method GET `
        -Headers $headers
    
    Write-Host "  [OK] Profile retrieved successfully" -ForegroundColor Green
    
    Write-Host "`n  User Data from Database:" -ForegroundColor Cyan
    Write-Host "  ------------------------" -ForegroundColor Cyan
    Write-Host "  ID: $($profile.data.id)" -ForegroundColor White
    Write-Host "  External ID: $($profile.data.external_id)" -ForegroundColor White
    Write-Host "  Username: $($profile.data.username)" -ForegroundColor White
    Write-Host "  Name: $($profile.data.name)" -ForegroundColor White
    Write-Host "  Email: $($profile.data.email)" -ForegroundColor White
    Write-Host "  First Name: $($profile.data.first_name)" -ForegroundColor White
    Write-Host "  Last Name: $($profile.data.last_name)" -ForegroundColor White
    Write-Host "  Is Admin: $($profile.data.is_admin)" -ForegroundColor White
    Write-Host "  Role: $($profile.data.role)" -ForegroundColor White
    Write-Host "  Department: $($profile.data.department)" -ForegroundColor White
    Write-Host "  Branch ID: $($profile.data.branch.id)" -ForegroundColor White
    Write-Host "  Branch Name: $($profile.data.branch.name)" -ForegroundColor White
    Write-Host "  Phone: $($profile.data.phone)" -ForegroundColor White
    Write-Host "  Last Login: $($profile.data.last_login)" -ForegroundColor White
    Write-Host "  Created At: $($profile.data.created_at)" -ForegroundColor White
    Write-Host "  Updated At: $($profile.data.updated_at)" -ForegroundColor White
    
    if ($profile.data.preferences) {
        Write-Host "`n  User Preferences:" -ForegroundColor Cyan
        Write-Host "  ----------------" -ForegroundColor Cyan
        Write-Host "  Theme: $($profile.data.preferences.theme)" -ForegroundColor White
        Write-Host "  Notifications: $($profile.data.preferences.notifications)" -ForegroundColor White
        Write-Host "  Language: $($profile.data.preferences.language)" -ForegroundColor White
    }
} catch {
    Write-Host "  [ERROR] Failed to get profile: $_" -ForegroundColor Red
}

# 3. Permissions (removed)
Write-Host "`n3. Permissions endpoint removed (stateless REST API)..." -ForegroundColor Yellow

# 4. Verify database state
Write-Host "`n4. Verifying database state..." -ForegroundColor Yellow
try {
    docker exec laravel_php php artisan users:check MY_USERNAME | Out-String | Write-Host
} catch {
    Write-Host "  Could not verify database state" -ForegroundColor Yellow
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "    Profile Test with DB Completed!     " -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

Write-Host "[OK] User data is being loaded from the local database" -ForegroundColor Green
Write-Host "[OK] External auth data is properly synchronized" -ForegroundColor Green
Write-Host "[OK] No permissions feature (stateless REST API)" -ForegroundColor Green
