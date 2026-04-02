# ERP (MVPController) API Test Script
# - Logs in to get a JWT (Bearer token) OR uses -Token
# - Tests the new ERP proxy endpoints under /api/erp/*
# - Optionally uploads a file for OCR endpoints (multipart/form-data) using HttpClient (PowerShell 5.1 compatible)

param(
    [string]$BaseUrl = "http://localhost:8080/api",
    [string]$Username = "MY_USERNAME",
    [string]$Password = "MY_PASSWORD",
    [string]$DeviceId = "test-erp-script",
    [string]$Token = "",
    [string]$FilePath = "",
    [string]$PageCode = "employee_dashboard",
    [string]$ApiUrl = "GET /admin/picklist/page/search.*",
    [string]$PaymentUuid = ""
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# Ensure System.Net.Http types are available in Windows PowerShell 5.1
try {
    Add-Type -AssemblyName System.Net.Http -ErrorAction Stop | Out-Null
} catch {
    # If Add-Type fails, we'll try again inside Invoke-MultipartFile and report a clear error.
}

function Read-ErrorResponseBody {
    param($Exception)

    $resp = $null
    try { $resp = $Exception.Response } catch { $resp = $null }
    if (-not $resp) {
        return $null
    }

    try {
        $stream = $resp.GetResponseStream()
        if (-not $stream) { return $null }
        $reader = New-Object System.IO.StreamReader($stream)
        $content = $reader.ReadToEnd()
        $reader.Close()
        return $content
    } catch {
        return $null
    }
}

function Invoke-Json {
    param(
        [ValidateSet('GET','POST')]
        [string]$Method,
        [string]$Uri,
        [hashtable]$Headers = @{},
        $Body = $null
    )

    $jsonBody = $null
    if ($null -ne $Body) {
        $jsonBody = $Body | ConvertTo-Json -Depth 30
    }

    try {
        if ($Method -eq 'GET') {
            $res = Invoke-WebRequest -Method Get -Uri $Uri -Headers $Headers -UseBasicParsing
        } else {
            $res = Invoke-WebRequest -Method Post -Uri $Uri -Headers $Headers -Body $jsonBody -ContentType "application/json" -UseBasicParsing
        }

        $obj = $null
        if ($res.Content) {
            try { $obj = $res.Content | ConvertFrom-Json } catch { $obj = $null }
        }

        return @{
            ok = $true
            status = [int]$res.StatusCode
            body = $obj
            raw = $res.Content
        }
    } catch {
        $status = $null
        try { $status = [int]$_.Exception.Response.StatusCode } catch { $status = $null }
        $raw = Read-ErrorResponseBody -Exception $_.Exception

        $obj = $null
        if ($raw) {
            try { $obj = $raw | ConvertFrom-Json } catch { $obj = $null }
        }

        return @{
            ok = $false
            status = $status
            body = $obj
            raw = $raw
            error = $_
        }
    }
}

function Invoke-WebRequest-NoRedirect {
    param(
        [ValidateSet('GET','POST')]
        [string]$Method,
        [string]$Uri,
        [hashtable]$Headers = @{},
        $Body = $null
    )

    $jsonBody = $null
    if ($null -ne $Body) {
        $jsonBody = $Body | ConvertTo-Json -Depth 30
    }

    try {
        if ($Method -eq 'GET') {
            $res = Invoke-WebRequest -Method Get -Uri $Uri -Headers $Headers -MaximumRedirection 0 -UseBasicParsing
        } else {
            $res = Invoke-WebRequest -Method Post -Uri $Uri -Headers $Headers -MaximumRedirection 0 -Body $jsonBody -ContentType "application/json" -UseBasicParsing
        }

        return @{
            ok = $true
            status = [int]$res.StatusCode
            headers = $res.Headers
            raw = $res.Content
        }
    } catch {
        # For 3xx responses, PowerShell can throw a WebException; still capture status + headers if present.
        $status = $null
        $headersOut = $null
        try { $status = [int]$_.Exception.Response.StatusCode } catch { $status = $null }
        try { $headersOut = $_.Exception.Response.Headers } catch { $headersOut = $null }
        $raw = Read-ErrorResponseBody -Exception $_.Exception

        return @{
            ok = $false
            status = $status
            headers = $headersOut
            raw = $raw
            error = $_
        }
    }
}

function Invoke-MultipartFile {
    param(
        [ValidateSet('POST')]
        [string]$Method,
        [string]$Uri,
        [hashtable]$Headers = @{},
        [string]$FilePath,
        [string]$FieldName = "file"
    )

    if (-not (Test-Path -LiteralPath $FilePath)) {
        return @{
            ok = $false
            status = $null
            body = $null
            raw = $null
            error = "File not found: $FilePath"
        }
    }

    try {
        Add-Type -AssemblyName System.Net.Http -ErrorAction Stop | Out-Null
    } catch {
        return @{
            ok = $false
            status = $null
            body = $null
            raw = $null
            error = "System.Net.Http is not available in this PowerShell environment. Try upgrading to PowerShell 7+ or enabling .NET Framework components. Original: $($_.Exception.Message)"
        }
    }

    $client = New-Object System.Net.Http.HttpClient
    try {
        foreach ($k in $Headers.Keys) {
            # Use TryAddWithoutValidation to allow Authorization header.
            [void]$client.DefaultRequestHeaders.TryAddWithoutValidation($k, [string]$Headers[$k])
        }

        $form = New-Object System.Net.Http.MultipartFormDataContent
        $fs = [System.IO.File]::OpenRead($FilePath)
        try {
            $fileContent = New-Object System.Net.Http.StreamContent($fs)
            $fileName = [System.IO.Path]::GetFileName($FilePath)
            $form.Add($fileContent, $FieldName, $fileName)

            $resp = $client.PostAsync($Uri, $form).Result
            $raw = $resp.Content.ReadAsStringAsync().Result
            $status = [int]$resp.StatusCode

            $obj = $null
            if ($raw) {
                try { $obj = $raw | ConvertFrom-Json } catch { $obj = $null }
            }

            if (-not $resp.IsSuccessStatusCode) {
                return @{
                    ok = $false
                    status = $status
                    body = $obj
                    raw = $raw
                    error = "HTTP $status"
                }
            }

            return @{
                ok = $true
                status = $status
                body = $obj
                raw = $raw
            }
        } finally {
            $fs.Dispose()
        }
    } finally {
        $client.Dispose()
    }
}

function Assert-SuccessResponse {
    param(
        [string]$Name,
        $Result,
        [int[]]$AllowedStatus = @(200, 202)
    )

    if (-not $Result.ok) {
        Write-Host "  FAILED: $Name (HTTP $($Result.status))" -ForegroundColor Red
        if ($Result.body -and ($Result.body.PSObject.Properties.Name -contains 'message')) {
            Write-Host "    Message: $($Result.body.message)" -ForegroundColor DarkRed
            if ($Result.body.PSObject.Properties.Name -contains 'error' -and $Result.body.error) {
                Write-Host "    Error: $($Result.body.error)" -ForegroundColor DarkRed
            }
        } elseif ($Result.raw) {
            Write-Host "    Response: $($Result.raw)" -ForegroundColor DarkRed
        } elseif ($Result.error) {
            Write-Host "    Error: $($Result.error)" -ForegroundColor DarkRed
        }
        return $false
    }

    if ($AllowedStatus -notcontains $Result.status) {
        Write-Host "  FAILED: $Name (unexpected HTTP $($Result.status))" -ForegroundColor Red
        return $false
    }

    if ($Result.body -and ($Result.body.PSObject.Properties.Name -contains 'success')) {
        if ($Result.body.success -ne $true) {
            Write-Host "  FAILED: $Name (success=false)" -ForegroundColor Red
            return $false
        }
    }

    Write-Host "  OK: $Name" -ForegroundColor Green
    return $true
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "ERP (MVPController) Proxy API Test" -ForegroundColor Cyan
Write-Host "BaseUrl: $BaseUrl" -ForegroundColor White
Write-Host "========================================" -ForegroundColor Cyan

# 1) Login (if needed)
if (-not $Token) {
    Write-Host "`n1) Logging in to get a JWT..." -ForegroundColor Yellow
    $loginBody = @{
        username = $Username
        password = $Password
        device_id = $DeviceId
    }
    $login = Invoke-Json -Method POST -Uri "$BaseUrl/auth/login" -Body $loginBody
    if (-not (Assert-SuccessResponse -Name "Login" -Result $login -AllowedStatus @(200))) {
        throw "Login failed; cannot continue."
    }
    $Token = $login.body.token
    if (-not $Token) {
        throw "Login succeeded but no token returned."
    }
    Write-Host "  Token acquired (first 25 chars): $($Token.Substring(0, [Math]::Min(25, $Token.Length)))..." -ForegroundColor DarkGreen
} else {
    Write-Host "`n1) Using provided token (first 25 chars): $($Token.Substring(0, [Math]::Min(25, $Token.Length)))..." -ForegroundColor Yellow
}

$headers = @{ Authorization = "Bearer $Token" }

# 2) isAuthorized
Write-Host "`n2) ERP: isAuthorized" -ForegroundColor Yellow
$pageCodeEnc = [System.Uri]::EscapeDataString($PageCode)
$apiUrlEnc = [System.Uri]::EscapeDataString($ApiUrl)
$isAuth = Invoke-Json -Method GET -Uri "$BaseUrl/erp/is-authorized?pageCode=$pageCodeEnc&apiUrl=$apiUrlEnc" -Headers $headers
[void](Assert-SuccessResponse -Name "GET /erp/is-authorized" -Result $isAuth -AllowedStatus @(200))
if ($isAuth.ok -and $isAuth.body -and $isAuth.body.data) {
    Write-Host "  authorized = $($isAuth.body.data.authorized)" -ForegroundColor White
}

# 3) Admin E-Payment (NEW)
Write-Host "`n3) ERP: Admin E-Payment - Transaction Statuses" -ForegroundColor Yellow
$statuses = Invoke-Json -Method GET -Uri "$BaseUrl/erp/epayment-transaction/statuses" -Headers $headers
[void](Assert-SuccessResponse -Name "GET /erp/epayment-transaction/statuses" -Result $statuses -AllowedStatus @(200))

Write-Host "`n4) ERP: Admin E-Payment - Create Transaction (may redirect)" -ForegroundColor Yellow
$createBody = @{
    relatedEntityId = 12345
    entityType = "ORDER"
    identifier = "ORD-2026-0001"
    amount = 150.75
    description = "Admin payment test"
    customParams = @{
        cart_currency = "USD"
        customer_email = "customer@example.com"
    }
    referer = "https://localhost/admin"
}

# Use HttpClient with AllowAutoRedirect=false to capture 302 + Location reliably.
$handler = New-Object System.Net.Http.HttpClientHandler
$handler.AllowAutoRedirect = $false
$client = New-Object System.Net.Http.HttpClient($handler)
try {
    $uri = "$BaseUrl/erp/epayment-transaction/create-transaction"
    $payloadJson = $createBody | ConvertTo-Json -Depth 30

    $req = New-Object System.Net.Http.HttpRequestMessage([System.Net.Http.HttpMethod]::Post, $uri)
    [void]$req.Headers.TryAddWithoutValidation('Authorization', [string]$headers.Authorization)
    $req.Content = New-Object System.Net.Http.StringContent($payloadJson, [System.Text.Encoding]::UTF8, 'application/json')

    $resp = $client.SendAsync($req).Result
    $status = [int]$resp.StatusCode
    $loc = $null
    try { $loc = $resp.Headers.Location } catch { $loc = $null }

    if ($status -eq 302) {
        Write-Host "  OK: POST /erp/epayment-transaction/create-transaction returned 302" -ForegroundColor Green
        if ($loc) { Write-Host "  Location: $loc" -ForegroundColor White }
    } elseif ($status -eq 200) {
        Write-Host "  OK: POST /erp/epayment-transaction/create-transaction returned 200 JSON" -ForegroundColor Green
        $raw = $resp.Content.ReadAsStringAsync().Result
        if ($raw) {
            Write-Host "  Response (truncated): $($raw.Substring(0, [Math]::Min(300, $raw.Length)))" -ForegroundColor Gray
        }
    } else {
        $raw = $resp.Content.ReadAsStringAsync().Result
        Write-Host "  FAILED: POST /erp/epayment-transaction/create-transaction (HTTP $status)" -ForegroundColor Red
        if ($raw) { Write-Host "  Response: $raw" -ForegroundColor DarkRed }
    }
} finally {
    $client.Dispose()
    $handler.Dispose()
}

# 5) SendMessage
Write-Host "`n5) ERP: SendMessage" -ForegroundColor Yellow
$sendBody = @{
    templateName = "SOME_TEMPLATE_CODE"
    lang = "en"
    target = @(
        @{
            id = -1
            entityType = "ExternalTarget"
            receiverName = "ExternalTarget"
            mobileNumber = "+971500000000"
            whatsappNumber = "+971500000000"
            smsReceiverType = $null
        }
    )
    parameters = @{
        name = "John"
    }
}
$send = Invoke-Json -Method POST -Uri "$BaseUrl/erp/send-message" -Headers $headers -Body $sendBody
[void](Assert-SuccessResponse -Name "POST /erp/send-message" -Result $send -AllowedStatus @(200))

# 6) OCR endpoints (optional)
Write-Host "`n6) ERP: OCR endpoints (optional)" -ForegroundColor Yellow
if (-not $FilePath) {
    Write-Host "  Skipping OCR calls. Provide -FilePath 'C:\path\to\file.jpg' to test uploads." -ForegroundColor DarkYellow
} else {
    Write-Host "  Using file: $FilePath" -ForegroundColor White

    $ocrGetText = Invoke-MultipartFile -Method POST -Uri "$BaseUrl/erp/ocr/get-text" -Headers $headers -FilePath $FilePath
    [void](Assert-SuccessResponse -Name "POST /erp/ocr/get-text" -Result $ocrGetText -AllowedStatus @(200))

    $ocrDetect = Invoke-MultipartFile -Method POST -Uri "$BaseUrl/erp/ocr/detect-attachment-type" -Headers $headers -FilePath $FilePath
    [void](Assert-SuccessResponse -Name "POST /erp/ocr/detect-attachment-type" -Result $ocrDetect -AllowedStatus @(200))

    $ocrExtract = Invoke-MultipartFile -Method POST -Uri "$BaseUrl/erp/ocr/extract" -Headers $headers -FilePath $FilePath
    [void](Assert-SuccessResponse -Name "POST /erp/ocr/extract" -Result $ocrExtract -AllowedStatus @(200))

    $ocrPassport = Invoke-MultipartFile -Method POST -Uri "$BaseUrl/erp/ocr/passport-service" -Headers $headers -FilePath $FilePath
    [void](Assert-SuccessResponse -Name "POST /erp/ocr/passport-service" -Result $ocrPassport -AllowedStatus @(200))
}

Write-Host "`n7) Payment (public) endpoints" -ForegroundColor Yellow
if (-not $PaymentUuid) {
    Write-Host "  Skipping check-status. Provide -PaymentUuid '<transaction-uuid>' to test it." -ForegroundColor DarkYellow
} else {
    $uuidEnc = [System.Uri]::EscapeDataString($PaymentUuid)
    $check = Invoke-Json -Method GET -Uri "$BaseUrl/payment/check-status?paymentID=$uuidEnc"
    if (-not (Assert-SuccessResponse -Name "GET /payment/check-status" -Result $check -AllowedStatus @(200))) {
        Write-Host "  Note: Upstream `/payment/*` may be blocked by reverse proxy rules in some deployments." -ForegroundColor DarkYellow
    }
}

$webhookBody = @{
    data = @{
        reference = if ($PaymentUuid) { $PaymentUuid } else { "00000000-0000-0000-0000-000000000000" }
    }
}
try {
    $res = Invoke-WebRequest -Method Post -Uri "$BaseUrl/payment/webhook-json" -Body ($webhookBody | ConvertTo-Json -Depth 30) -ContentType "application/json" -UseBasicParsing
    Write-Host "  OK: POST /payment/webhook-json (HTTP $($res.StatusCode))" -ForegroundColor Green
    if ($res.Content) { Write-Host "  Response: $($res.Content)" -ForegroundColor Gray }
} catch {
    Write-Host "  FAILED: POST /payment/webhook-json" -ForegroundColor Red
    try {
        $status = [int]$_.Exception.Response.StatusCode
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $raw = $reader.ReadToEnd()
        $reader.Close()
        Write-Host "  HTTP $status" -ForegroundColor DarkRed
        if ($raw) { Write-Host "  Response: $raw" -ForegroundColor DarkRed }
    } catch {
        Write-Host "  Error: $_" -ForegroundColor DarkRed
    }
    Write-Host "  Note: Upstream `/payment/*` may be blocked by reverse proxy rules in some deployments." -ForegroundColor DarkYellow
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "ERP test complete." -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

