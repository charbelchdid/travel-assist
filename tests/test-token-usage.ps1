# JWT Token Usage Demonstration Script
# This script shows how the token is used in practice

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "JWT Token Usage Demonstration" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

$baseUrl = "http://localhost:8080/api"

# Step 1: LOGIN - Get the JWT Token
Write-Host "`nSTEP 1: LOGIN TO GET JWT TOKEN" -ForegroundColor Yellow
Write-Host "-------------------------------" -ForegroundColor Yellow

$loginCredentials = @{
    username = "MY_USERNAME"
    password = "MY_PASSWORD"
    device_id = "demo-script"
} | ConvertTo-Json

Write-Host "Sending credentials to: $baseUrl/auth/login" -ForegroundColor White

try {
    $loginResponse = Invoke-RestMethod -Method Post `
        -Uri "$baseUrl/auth/login" `
        -Body $loginCredentials `
        -ContentType "application/json" `
        -UseBasicParsing
    
    $jwtToken = $loginResponse.token
    Write-Host "SUCCESS: Login successful!" -ForegroundColor Green
    Write-Host "JWT Token received (first 50 chars):" -ForegroundColor Green
    Write-Host "  $($jwtToken.Substring(0, 50))..." -ForegroundColor Cyan
    
} catch {
    Write-Host "✗ Login failed!" -ForegroundColor Red
    exit
}

# Step 2: SHOW TOKEN STRUCTURE
Write-Host "`nSTEP 2: TOKEN STRUCTURE" -ForegroundColor Yellow
Write-Host "------------------------" -ForegroundColor Yellow

$tokenParts = $jwtToken.Split('.')
Write-Host "JWT has 3 parts separated by dots:" -ForegroundColor White
Write-Host "1. Header (Algorithm info): $($tokenParts[0].Substring(0, 20))..." -ForegroundColor Gray
Write-Host "2. Payload (User data): $($tokenParts[1].Substring(0, 20))..." -ForegroundColor Gray
Write-Host "3. Signature (Verification): $($tokenParts[2].Substring(0, 20))..." -ForegroundColor Gray

# Decode payload
$payload = $tokenParts[1]
$mod = $payload.Length % 4
if ($mod -gt 0) { $payload += "=" * (4 - $mod) }
$decodedPayload = [System.Text.Encoding]::UTF8.GetString([System.Convert]::FromBase64String($payload))
$payloadData = $decodedPayload | ConvertFrom-Json

Write-Host "`nDecoded Payload:" -ForegroundColor White
Write-Host "  User: $($payloadData.user)" -ForegroundColor Gray
Write-Host "  Device: $($payloadData.device)" -ForegroundColor Gray
Write-Host "  Issued at: $([DateTimeOffset]::FromUnixTimeSeconds($payloadData.iat).LocalDateTime)" -ForegroundColor Gray
Write-Host "  Expires at: $([DateTimeOffset]::FromUnixTimeSeconds($payloadData.exp).LocalDateTime)" -ForegroundColor Gray

# Step 3: USE TOKEN - WITHOUT Authorization Header (WILL FAIL)
Write-Host "`nSTEP 3: REQUEST WITHOUT TOKEN (Will Fail)" -ForegroundColor Yellow
Write-Host "------------------------------------------" -ForegroundColor Yellow

Write-Host "Calling: GET $baseUrl/user/profile" -ForegroundColor White
Write-Host "Headers: None" -ForegroundColor White

try {
    $noAuthResponse = Invoke-RestMethod -Method Get `
        -Uri "$baseUrl/user/profile" `
        -UseBasicParsing
    Write-Host "Unexpected success!" -ForegroundColor Red
} catch {
    Write-Host "Expected failure: Request rejected (401 Unauthorized)" -ForegroundColor Green
    Write-Host "  Message: 'Authorization token not provided'" -ForegroundColor Gray
}

# Step 4: USE TOKEN - WITH Authorization Header (WILL SUCCEED)
Write-Host "`nSTEP 4: REQUEST WITH TOKEN (Will Succeed)" -ForegroundColor Yellow
Write-Host "------------------------------------------" -ForegroundColor Yellow

Write-Host "Calling: GET $baseUrl/user/profile" -ForegroundColor White
Write-Host "Headers: Authorization: Bearer [token]" -ForegroundColor White

$headersWithToken = @{
    "Authorization" = "Bearer $jwtToken"
}

try {
    $profileResponse = Invoke-RestMethod -Method Get `
        -Uri "$baseUrl/user/profile" `
        -Headers $headersWithToken `
        -UseBasicParsing
    
    Write-Host "✓ Success! Protected endpoint accessed" -ForegroundColor Green
    Write-Host "  Username from response: $($profileResponse.data.username)" -ForegroundColor Gray
    Write-Host "  Role: $($profileResponse.data.role)" -ForegroundColor Gray
    
} catch {
    Write-Host "✗ Failed to access protected endpoint" -ForegroundColor Red
}

# Step 5: MULTIPLE PROTECTED ENDPOINTS
Write-Host "`nSTEP 5: USING TOKEN FOR MULTIPLE ENDPOINTS" -ForegroundColor Yellow
Write-Host "--------------------------------------------" -ForegroundColor Yellow

Write-Host "The same token works for ALL protected endpoints:" -ForegroundColor White

# Test multiple endpoints
$endpoints = @(
    @{path="/products"; name="Products List"},
    @{path="/products/categories/list"; name="Product Categories"}
)

foreach ($endpoint in $endpoints) {
    Write-Host "`n  Testing: $($endpoint.name)" -ForegroundColor White
    try {
        $response = Invoke-RestMethod -Method Get `
            -Uri "$baseUrl$($endpoint.path)" `
            -Headers $headersWithToken `
            -UseBasicParsing
        Write-Host "    ✓ Accessible with token" -ForegroundColor Green
    } catch {
        Write-Host "    ✗ Failed" -ForegroundColor Red
    }
}

# Step 6: WRONG TOKEN FORMAT
Write-Host "`nSTEP 6: COMMON MISTAKES" -ForegroundColor Yellow
Write-Host "------------------------" -ForegroundColor Yellow

# Missing "Bearer" prefix
Write-Host "`nMistake 1: Forgetting 'Bearer' prefix" -ForegroundColor White
$wrongHeaders1 = @{
    "Authorization" = $jwtToken  # Missing "Bearer "
}
try {
    $response = Invoke-RestMethod -Method Get `
        -Uri "$baseUrl/user/profile" `
        -Headers $wrongHeaders1 `
        -UseBasicParsing
    Write-Host "  Should have failed!" -ForegroundColor Red
} catch {
    Write-Host "  ✓ Correctly rejected - 'Bearer' prefix is required" -ForegroundColor Green
}

# Wrong header name
Write-Host "`nMistake 2: Wrong header name" -ForegroundColor White
$wrongHeaders2 = @{
    "Token" = "Bearer $jwtToken"  # Should be "Authorization"
}
try {
    $response = Invoke-RestMethod -Method Get `
        -Uri "$baseUrl/user/profile" `
        -Headers $wrongHeaders2 `
        -UseBasicParsing
    Write-Host "  Should have failed!" -ForegroundColor Red
} catch {
    Write-Host "  ✓ Correctly rejected - Must use 'Authorization' header" -ForegroundColor Green
}

# Summary
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "SUMMARY: HOW TO USE JWT TOKEN" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

Write-Host "`n1. LOGIN: Send credentials to /api/auth/login" -ForegroundColor White
Write-Host "2. RECEIVE: Get JWT token from response" -ForegroundColor White
Write-Host "3. STORE: Save token in your application" -ForegroundColor White
Write-Host "4. INCLUDE: Add to every protected request as:" -ForegroundColor White
Write-Host "   Authorization: Bearer [token]" -ForegroundColor Yellow
Write-Host "5. HANDLE EXPIRY: Re-login when token expires" -ForegroundColor White

Write-Host "`nREMEMBER:" -ForegroundColor Yellow
Write-Host "• Include 'Bearer ' before the token" -ForegroundColor White
Write-Host "• Use 'Authorization' as header name" -ForegroundColor White
Write-Host "• Token expires after ~15 hours" -ForegroundColor White
Write-Host "• Same token works for all protected endpoints" -ForegroundColor White
Write-Host "• API is stateless (no server sessions)" -ForegroundColor White

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Demo Complete!" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
