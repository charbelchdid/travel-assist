# Decode and test JWT token

$baseUrl = "http://localhost:8080"

Write-Host "`nTesting JWT Token..." -ForegroundColor Cyan

# 1. Login to get token
$loginBody = @{
    username = "MY_USERNAME"
    password = "MY_PASSWORD"
    device_id = "test-decode"
} | ConvertTo-Json

Write-Host "`n1. Getting JWT token..." -ForegroundColor Yellow
$response = Invoke-RestMethod -Uri "$baseUrl/api/auth/login" `
    -Method POST `
    -ContentType "application/json" `
    -Body $loginBody

if ($response.token) {
    $token = $response.token
    Write-Host "   Token received!" -ForegroundColor Green
    
    # Decode the JWT
    $tokenParts = $token.Split('.')
    if ($tokenParts.Count -eq 3) {
        # Decode header
        $header = $tokenParts[0]
        while ($header.Length % 4 -ne 0) { $header += "=" }
        $headerJson = [System.Text.Encoding]::UTF8.GetString([System.Convert]::FromBase64String($header))
        
        Write-Host "`n   JWT Header:" -ForegroundColor Cyan
        $headerJson | ConvertFrom-Json | ConvertTo-Json | Write-Host -ForegroundColor Gray
        
        # Decode payload
        $payload = $tokenParts[1]
        # Fix padding
        $payload = $payload.Replace('-', '+').Replace('_', '/')
        while ($payload.Length % 4 -ne 0) { $payload += "=" }
        $payloadJson = [System.Text.Encoding]::UTF8.GetString([System.Convert]::FromBase64String($payload))
        
        Write-Host "`n   JWT Payload:" -ForegroundColor Cyan
        $payloadData = $payloadJson | ConvertFrom-Json
        $payloadData | ConvertTo-Json | Write-Host -ForegroundColor Gray
        
        # Check expiration
        if ($payloadData.exp) {
            $expDate = [DateTimeOffset]::FromUnixTimeSeconds($payloadData.exp).DateTime
            Write-Host "`n   Token expires at: $expDate" -ForegroundColor Yellow
            
            if ($expDate -gt [DateTime]::UtcNow) {
                Write-Host "   Token is VALID (not expired)" -ForegroundColor Green
            } else {
                Write-Host "   Token is EXPIRED!" -ForegroundColor Red
            }
        }
    }
    
    # 2. Test with the token
    Write-Host "`n2. Testing protected endpoint with token..." -ForegroundColor Yellow
    
    $headers = @{
        "Authorization" = "Bearer $token"
    }
    
    # Show the actual header being sent
    Write-Host "   Sending header: Authorization = Bearer [token]" -ForegroundColor Gray
    
    try {
        $profile = Invoke-RestMethod -Uri "$baseUrl/api/user/profile" `
            -Method GET `
            -Headers $headers
            
        Write-Host "   SUCCESS! Profile retrieved" -ForegroundColor Green
        Write-Host "   User: $($profile.data.name) ($($profile.data.username))" -ForegroundColor Gray
    } catch {
        Write-Host "   FAILED: $($_.Exception.Message)" -ForegroundColor Red
        if ($_.ErrorDetails.Message) {
            $errorData = $_.ErrorDetails.Message | ConvertFrom-Json
            Write-Host "   Error response:" -ForegroundColor Red
            $errorData | ConvertTo-Json | Write-Host -ForegroundColor Red
        }
    }
}
