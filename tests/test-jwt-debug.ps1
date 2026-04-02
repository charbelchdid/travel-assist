# Debug JWT Token Issue

$baseUrl = "http://localhost:8080"

Write-Host "`nTesting JWT Authentication Flow..." -ForegroundColor Cyan

# 1. Login to get token
$loginBody = @{
    username = "MY_USERNAME"
    password = "MY_PASSWORD"
    device_id = "test-debug"
} | ConvertTo-Json

Write-Host "`n1. Getting JWT token..." -ForegroundColor Yellow
$response = Invoke-RestMethod -Uri "$baseUrl/api/auth/login" `
    -Method POST `
    -ContentType "application/json" `
    -Body $loginBody `
    -ErrorAction SilentlyContinue

if ($response -and $response.token) {
    $token = $response.token
    Write-Host "   Token received successfully" -ForegroundColor Green
    Write-Host "   Token (first 50 chars): $($token.Substring(0, [Math]::Min(50, $token.Length)))..." -ForegroundColor Gray
    
    # Decode the JWT payload
    $tokenParts = $token.Split('.')
    if ($tokenParts.Count -eq 3) {
        $payload = $tokenParts[1]
        # Add padding if needed
        while ($payload.Length % 4 -ne 0) {
            $payload += "="
        }
        $payloadJson = [System.Text.Encoding]::UTF8.GetString([System.Convert]::FromBase64String($payload))
        Write-Host "`n   Decoded JWT Payload:" -ForegroundColor Cyan
        $payloadJson | ConvertFrom-Json | ConvertTo-Json -Depth 10 | Write-Host -ForegroundColor Gray
    }
    
    # 2. Test with Authorization header
    Write-Host "`n2. Testing /api/user/profile with Bearer token..." -ForegroundColor Yellow
    try {
        $headers = @{
            "Authorization" = "Bearer $token"
        }
        
        $profile = Invoke-RestMethod -Uri "$baseUrl/api/user/profile" `
            -Method GET `
            -Headers $headers `
            -ErrorAction Stop
            
        Write-Host "   SUCCESS: Profile endpoint worked!" -ForegroundColor Green
        Write-Host "   User: $($profile.data.username)" -ForegroundColor Gray
    } catch {
        Write-Host "   FAILED: $($_.Exception.Message)" -ForegroundColor Red
        if ($_.ErrorDetails.Message) {
            Write-Host "   Error details: $($_.ErrorDetails.Message)" -ForegroundColor Red
        }
    }
    
    # 3. Test raw request to see headers
    Write-Host "`n3. Testing raw request with verbose output..." -ForegroundColor Yellow
    try {
        $webRequest = [System.Net.WebRequest]::Create("$baseUrl/api/user/profile")
        $webRequest.Method = "GET"
        $webRequest.Headers.Add("Authorization", "Bearer $token")
        $webRequest.ContentType = "application/json"
        
        $webResponse = $webRequest.GetResponse()
        $reader = New-Object System.IO.StreamReader($webResponse.GetResponseStream())
        $responseText = $reader.ReadToEnd()
        $reader.Close()
        
        Write-Host "   SUCCESS: Raw request worked!" -ForegroundColor Green
        Write-Host "   Response: $responseText" -ForegroundColor Gray
    } catch [System.Net.WebException] {
        $errorResponse = $_.Exception.Response
        if ($errorResponse) {
            Write-Host "   HTTP Status: $([int]$errorResponse.StatusCode) - $($errorResponse.StatusDescription)" -ForegroundColor Red
            $reader = New-Object System.IO.StreamReader($errorResponse.GetResponseStream())
            $errorText = $reader.ReadToEnd()
            $reader.Close()
            Write-Host "   Error response: $errorText" -ForegroundColor Red
        } else {
            Write-Host "   Error: $($_.Exception.Message)" -ForegroundColor Red
        }
    }
    
} else {
    Write-Host "   Failed to get token" -ForegroundColor Red
}

Write-Host "`nDone!" -ForegroundColor Cyan
