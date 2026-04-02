# Test User Synchronization with External Auth
# This script tests the user creation/update in local database

$baseUrl = "http://localhost:8080"

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  Testing User Sync with External Auth  " -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# 1. Check initial database state
Write-Host "1. Checking initial database state..." -ForegroundColor Yellow
try {
    docker exec laravel_php php artisan tinker --execute="echo 'Users in DB: ' . \App\Models\User::count() . ' users'; \App\Models\User::all()->each(function(\$u) { echo '  - ID: ' . \$u->id . ', External ID: ' . \$u->external_id . ', Username: ' . \$u->username . ', Name: ' . \$u->name . PHP_EOL; });"
} catch {
    Write-Host "  Error checking database: $_" -ForegroundColor Red
}

# 2. Login with real credentials
Write-Host "`n2. Logging in with real credentials..." -ForegroundColor Yellow
$loginBody = @{
    username = "MY_USERNAME"
    password = "MY_PASSWORD"
    device_id = "test-device-001"
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/api/auth/login" `
        -Method POST `
        -ContentType "application/json" `
        -Body $loginBody
    
    Write-Host "  Login successful!" -ForegroundColor Green
    Write-Host "  Token received: $($response.token.Substring(0, 50))..." -ForegroundColor Gray
    
    if ($response.user) {
        Write-Host "  User data returned:" -ForegroundColor Cyan
        $response.user | ConvertTo-Json -Depth 10 | Write-Host -ForegroundColor Gray
    }
    
    $token = $response.token
} catch {
    Write-Host "  Login failed: $_" -ForegroundColor Red
    exit 1
}

# 3. Check database after login
Write-Host "`n3. Checking database after login..." -ForegroundColor Yellow
try {
    docker exec laravel_php php artisan tinker --execute="echo 'Users in DB: ' . \App\Models\User::count() . ' users'; \App\Models\User::all()->each(function(\$u) { echo '  - ID: ' . \$u->id . ', External ID: ' . \$u->external_id . ', Username: ' . \$u->username . ', Name: ' . \$u->name . ', Last Login: ' . \$u->last_login_at . PHP_EOL; });"
} catch {
    Write-Host "  Error checking database: $_" -ForegroundColor Red
}

# 4. Check user details in database
Write-Host "`n4. Checking detailed user information..." -ForegroundColor Yellow
try {
    docker exec laravel_php php artisan tinker --execute="\$user = \App\Models\User::where('username', 'MY_USERNAME')->first(); if(\$user) { echo 'User Details:' . PHP_EOL; echo '  ID: ' . \$user->id . PHP_EOL; echo '  External ID: ' . \$user->external_id . PHP_EOL; echo '  Username: ' . \$user->username . PHP_EOL; echo '  Email: ' . \$user->email . PHP_EOL; echo '  Name: ' . \$user->name . PHP_EOL; echo '  First Name: ' . \$user->first_name . PHP_EOL; echo '  Last Name: ' . \$user->last_name . PHP_EOL; echo '  Phone: ' . \$user->phone . PHP_EOL; echo '  Is Admin: ' . (\$user->is_admin ? 'Yes' : 'No') . PHP_EOL; echo '  Role: ' . \$user->role . PHP_EOL; echo '  Department: ' . \$user->department . PHP_EOL; echo '  Branch ID: ' . \$user->branch_id . PHP_EOL; echo '  Branch Name: ' . \$user->branch_name . PHP_EOL; echo '  Device ID: ' . \$user->device_id . PHP_EOL; echo '  Last Login: ' . \$user->last_login_at . PHP_EOL; } else { echo 'User not found in database!'; }"
} catch {
    Write-Host "  Error checking user details: $_" -ForegroundColor Red
}

# 5. Test accessing protected endpoint with token
Write-Host "`n5. Testing protected endpoint with token..." -ForegroundColor Yellow
try {
    $headers = @{
        "Authorization" = "Bearer $token"
    }
    
    $userProfile = Invoke-RestMethod -Uri "$baseUrl/api/user/profile" `
        -Method GET `
        -Headers $headers
    
    Write-Host "  Profile endpoint accessed successfully!" -ForegroundColor Green
    Write-Host "  Profile data:" -ForegroundColor Cyan
    $userProfile | ConvertTo-Json -Depth 10 | Write-Host -ForegroundColor Gray
} catch {
    Write-Host "  Failed to access profile: $_" -ForegroundColor Red
}

# 6. Login again to test update
Write-Host "`n6. Logging in again to test user update..." -ForegroundColor Yellow
Start-Sleep -Seconds 2

try {
    $response2 = Invoke-RestMethod -Uri "$baseUrl/api/auth/login" `
        -Method POST `
        -ContentType "application/json" `
        -Body $loginBody
    
    Write-Host "  Second login successful!" -ForegroundColor Green
} catch {
    Write-Host "  Second login failed: $_" -ForegroundColor Red
}

# 7. Check if last_login_at was updated
Write-Host "`n7. Checking if last_login_at was updated..." -ForegroundColor Yellow
try {
    docker exec laravel_php php artisan tinker --execute="\$user = \App\Models\User::where('username', 'MY_USERNAME')->first(); if(\$user) { echo 'Last Login Updated: ' . \$user->last_login_at . PHP_EOL; } else { echo 'User not found!'; }"
} catch {
    Write-Host "  Error checking last login: $_" -ForegroundColor Red
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "     User Sync Test Completed!          " -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan
