# Simple JWT test with raw output

$baseUrl = "http://localhost:8080"

# 1. Login
$loginBody = @{
    username = "MY_USERNAME"
    password = "MY_PASSWORD"
    device_id = "test"
} | ConvertTo-Json

Write-Host "1. Logging in..." -ForegroundColor Cyan
$loginResponse = Invoke-RestMethod -Uri "$baseUrl/api/auth/login" `
    -Method POST `
    -ContentType "application/json" `
    -Body $loginBody

if ($loginResponse.token) {
    $token = $loginResponse.token
    Write-Host "   Token received: YES" -ForegroundColor Green
    Write-Host "   Token (first 50): $($token.Substring(0, 50))..." -ForegroundColor Gray
    
    # 2. Test health endpoint (public)
    Write-Host "`n2. Testing public /api/health..." -ForegroundColor Cyan
    $health = Invoke-RestMethod -Uri "$baseUrl/api/health" -Method GET
    Write-Host "   Health: $($health.status)" -ForegroundColor Green
    
    # 3. Test protected endpoint with verbose
    Write-Host "`n3. Testing protected /api/user/profile..." -ForegroundColor Cyan
    
    $headers = @{
        "Authorization" = "Bearer $token"
    }
    
    try {
        # Using WebRequest for more control
        $request = [System.Net.HttpWebRequest]::Create("$baseUrl/api/user/profile")
        $request.Method = "GET"
        $request.Headers.Add("Authorization", "Bearer $token")
        $request.ContentType = "application/json"
        $request.Accept = "application/json"
        
        $response = $request.GetResponse()
        $stream = $response.GetResponseStream()
        $reader = New-Object System.IO.StreamReader($stream)
        $responseBody = $reader.ReadToEnd()
        $reader.Close()
        
        Write-Host "   SUCCESS!" -ForegroundColor Green
        Write-Host "   Response: $responseBody" -ForegroundColor Gray
        
    } catch [System.Net.WebException] {
        $errorResponse = $_.Exception.Response
        Write-Host "   FAILED!" -ForegroundColor Red
        Write-Host "   Status: $([int]$errorResponse.StatusCode) - $($errorResponse.StatusDescription)" -ForegroundColor Red
        
        if ($errorResponse) {
            $errorStream = $errorResponse.GetResponseStream()
            $errorReader = New-Object System.IO.StreamReader($errorStream)
            $errorBody = $errorReader.ReadToEnd()
            $errorReader.Close()
            Write-Host "   Error body: $errorBody" -ForegroundColor Red
        }
    }
} else {
    Write-Host "   No token in response!" -ForegroundColor Red
}
