# Simple login test to see what's returned

$baseUrl = "http://localhost:8080"

$loginBody = @{
    username = "MY_USERNAME"
    password = "MY_PASSWORD"
    device_id = "test-simple"
} | ConvertTo-Json

Write-Host "Testing login endpoint..." -ForegroundColor Cyan

try {
    $response = Invoke-WebRequest -Uri "$baseUrl/api/auth/login" `
        -Method POST `
        -ContentType "application/json" `
        -Body $loginBody `
        -UseBasicParsing
    
    Write-Host "`nStatus Code: $($response.StatusCode)" -ForegroundColor Green
    Write-Host "`nResponse Headers:" -ForegroundColor Yellow
    $response.Headers | Format-Table -AutoSize
    
    Write-Host "`nResponse Body:" -ForegroundColor Yellow
    $responseContent = $response.Content
    Write-Host $responseContent -ForegroundColor Gray
    
    # Try to parse as JSON
    try {
        $jsonResponse = $responseContent | ConvertFrom-Json
        Write-Host "`nParsed JSON:" -ForegroundColor Cyan
        $jsonResponse | ConvertTo-Json -Depth 10 | Write-Host -ForegroundColor Gray
        
        if ($jsonResponse.token) {
            Write-Host "`nToken found in response!" -ForegroundColor Green
            Write-Host "Token (first 50 chars): $($jsonResponse.token.Substring(0, 50))..." -ForegroundColor Gray
        } else {
            Write-Host "`nNo token field in response!" -ForegroundColor Red
        }
    } catch {
        Write-Host "`nCould not parse response as JSON" -ForegroundColor Red
    }
    
} catch {
    Write-Host "Request failed: $_" -ForegroundColor Red
    if ($_.Exception.Response) {
        $reader = [System.IO.StreamReader]::new($_.Exception.Response.GetResponseStream())
        $errorContent = $reader.ReadToEnd()
        $reader.Close()
        Write-Host "Error response: $errorContent" -ForegroundColor Red
    }
}
