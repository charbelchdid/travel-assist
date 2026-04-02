# Simple Authentication Test
$baseUrl = "http://localhost:8080/api"

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Testing Authentication with Real Credentials" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

$body = @{
    username = "MY_USERNAME"
    password = "MY_PASSWORD"
    device_id = "test-device"
} | ConvertTo-Json

Write-Host "`nTesting login with username: MY_USERNAME" -ForegroundColor Yellow

try {
    $response = Invoke-RestMethod -Method Post -Uri "$baseUrl/auth/login" -Body $body -ContentType "application/json" -UseBasicParsing
    
    Write-Host "`nLOGIN SUCCESSFUL!" -ForegroundColor Green
    Write-Host "Token received!" -ForegroundColor Green
    
    # Show token (first 100 chars)
    $token = $response.token
    Write-Host "`nToken (first 100 chars):" -ForegroundColor Yellow
    Write-Host $token.Substring(0, [Math]::Min(100, $token.Length)) -ForegroundColor Cyan
    
    # Decode JWT payload
    $parts = $token.Split('.')
    if ($parts.Length -eq 3) {
        $payload = $parts[1]
        $mod = $payload.Length % 4
        if ($mod -gt 0) { $payload += "=" * (4 - $mod) }
        
        $decoded = [System.Text.Encoding]::UTF8.GetString([System.Convert]::FromBase64String($payload))
        $jwt = $decoded | ConvertFrom-Json
        
        Write-Host "`nJWT Payload:" -ForegroundColor Yellow
        Write-Host "User ID: $($jwt.sub)" -ForegroundColor White
        Write-Host "Username: $($jwt.username)" -ForegroundColor White
        Write-Host "Email: $($jwt.email)" -ForegroundColor White
        if ($jwt.exp) {
            $expiry = [DateTimeOffset]::FromUnixTimeSeconds($jwt.exp).LocalDateTime
            Write-Host "Expires: $expiry" -ForegroundColor White
        }
    }
    
    # Test protected endpoint
    Write-Host "`n========================================" -ForegroundColor Cyan
    Write-Host "Testing Protected Endpoints" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    
    $headers = @{ Authorization = "Bearer $token" }
    
    # Get user profile
    Write-Host "`nGetting user profile..." -ForegroundColor Yellow
    try {
        $profile = Invoke-RestMethod -Uri "$baseUrl/user/profile" -Headers $headers -UseBasicParsing
        Write-Host "SUCCESS - Profile retrieved!" -ForegroundColor Green
        Write-Host "Username: $($profile.data.username)" -ForegroundColor White
        Write-Host "Role: $($profile.data.role)" -ForegroundColor White
    } catch {
        Write-Host "Failed to get profile" -ForegroundColor Red
    }
    
    # Get products
    Write-Host "`nGetting products..." -ForegroundColor Yellow
    try {
        $products = Invoke-RestMethod -Uri "$baseUrl/products" -Headers $headers -UseBasicParsing
        Write-Host "SUCCESS - Products retrieved!" -ForegroundColor Green
        Write-Host "Product count: $($products.data.Count)" -ForegroundColor White
    } catch {
        Write-Host "Failed to get products" -ForegroundColor Red
    }
    
} catch {
    Write-Host "`nLOGIN FAILED!" -ForegroundColor Red
    $error = $_.ErrorDetails.Message | ConvertFrom-Json
    Write-Host "Error: $($error.message)" -ForegroundColor Red
    if ($error.error) {
        Write-Host "Details: $($error.error)" -ForegroundColor Red
    }
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Test Complete" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
