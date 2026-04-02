# Temporal Examples Test Script
# - Logs in to get a JWT (Bearer token)
# - Starts each Temporal example workflow via the API
# - For OrderWorkflow, also sends a signal and runs a query (this requires the worker to be running)

param(
    [string]$BaseUrl = "http://localhost:8080/api",
    [string]$Username = "MY_USERNAME",
    [string]$Password = "MY_PASSWORD",
    [string]$DeviceId = "test-temporal-script"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function New-WorkflowId {
    param([string]$Prefix)
    return "$Prefix-$([guid]::NewGuid().ToString())"
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
        $jsonBody = $Body | ConvertTo-Json -Depth 20
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
        } elseif ($Result.raw) {
            Write-Host "    Response: $($Result.raw)" -ForegroundColor DarkRed
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

    Write-Host "  OK: $Name (HTTP $($Result.status))" -ForegroundColor Green
    return $true
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Temporal Examples Test" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Base URL: $BaseUrl" -ForegroundColor Gray

# 0) Health check (public)
Write-Host "`n[0] Health check..." -ForegroundColor Yellow
$health = Invoke-Json -Method GET -Uri "$BaseUrl/health"
if (-not (Assert-SuccessResponse -Name "GET /health" -Result $health -AllowedStatus @(200))) {
    Write-Host "`nAPI does not look reachable. Make sure Docker is up and Nginx is listening on 8080." -ForegroundColor Red
    exit 1
}

# 1) Login (get token)
Write-Host "`n[1] Login (Bearer token)..." -ForegroundColor Yellow
$loginBody = @{
    username = $Username
    password = $Password
    device_id = $DeviceId
}
$login = Invoke-Json -Method POST -Uri "$BaseUrl/auth/login" -Body $loginBody
if (-not (Assert-SuccessResponse -Name "POST /auth/login" -Result $login -AllowedStatus @(200))) {
    Write-Host "`nLogin failed. Update -Username/-Password, or verify external auth service connectivity." -ForegroundColor Red
    exit 1
}

$token = $null
if ($login.body -and ($login.body.PSObject.Properties.Name -contains 'token')) {
    $token = $login.body.token
}
if (-not $token) {
    Write-Host "  FAILED: No token in login response. (Did you hit cookie login?)" -ForegroundColor Red
    exit 1
}
Write-Host "  Token received (length=$($token.Length))" -ForegroundColor Gray

$headers = @{ Authorization = "Bearer $token" }

# 2) Start workflows
Write-Host "`n[2] Starting workflows..." -ForegroundColor Yellow

$results = @()

$results += @{
    name = "POST /temporal/examples/greeting/start"
    res  = (Invoke-Json -Method POST -Uri "$BaseUrl/temporal/examples/greeting/start" -Headers $headers -Body @{
        name = "temporal-test"
        workflow_id = (New-WorkflowId -Prefix "greeting")
    })
}

$orderWorkflowId = New-WorkflowId -Prefix "order"
$results += @{
    name = "POST /temporal/examples/order/start"
    res  = (Invoke-Json -Method POST -Uri "$BaseUrl/temporal/examples/order/start" -Headers $headers -Body @{
        order_id = "order-$([guid]::NewGuid().ToString())"
        workflow_id = $orderWorkflowId
    })
}

$results += @{
    name = "POST /temporal/examples/child/start"
    res  = (Invoke-Json -Method POST -Uri "$BaseUrl/temporal/examples/child/start" -Headers $headers -Body @{
        value = 41
        workflow_id = (New-WorkflowId -Prefix "parent")
    })
}

$results += @{
    name = "POST /temporal/examples/retry/start"
    res  = (Invoke-Json -Method POST -Uri "$BaseUrl/temporal/examples/retry/start" -Headers $headers -Body @{
        succeed_on_attempt = 2
        workflow_id = (New-WorkflowId -Prefix "retry")
    })
}

$results += @{
    name = "POST /temporal/examples/continue-as-new/start"
    res  = (Invoke-Json -Method POST -Uri "$BaseUrl/temporal/examples/continue-as-new/start" -Headers $headers -Body @{
        items = @(1,2,3,4,5,6,7,8,9,10)
        batch_size = 5
        workflow_id = (New-WorkflowId -Prefix "continue")
    })
}

$results += @{
    name = "POST /temporal/examples/saga/start"
    res  = (Invoke-Json -Method POST -Uri "$BaseUrl/temporal/examples/saga/start" -Headers $headers -Body @{
        order_id = "saga-$([guid]::NewGuid().ToString())"
        amount_cents = 150
        workflow_id = (New-WorkflowId -Prefix "saga")
    })
}

$paymentMonitorWorkflowId = New-WorkflowId -Prefix "payment-monitor"
$results += @{
    name = "POST /temporal/examples/payment-monitor/start"
    res  = (Invoke-Json -Method POST -Uri "$BaseUrl/temporal/examples/payment-monitor/start" -Headers $headers -Body @{
        payment_uuid = "00000000-0000-0000-0000-000000000000"
        poll_seconds = 2
        max_attempts = 3
        workflow_id = $paymentMonitorWorkflowId
    })
}

$allOk = $true
foreach ($r in $results) {
    $ok = Assert-SuccessResponse -Name $r.name -Result $r.res -AllowedStatus @(202)
    if (-not $ok) { $allOk = $false }

    if ($r.res.ok -and $r.res.body -and $r.res.body.data -and $r.res.body.data.workflow_id) {
        Write-Host "    workflow_id: $($r.res.body.data.workflow_id)" -ForegroundColor Gray
        Write-Host "    run_id:      $($r.res.body.data.run_id)" -ForegroundColor Gray
    }
}

if (-not $allOk) {
    Write-Host "`nOne or more workflow start calls failed. If the error mentions Temporal connectivity, verify Temporal is running (7233) and the API has TEMPORAL_ADDRESS set correctly." -ForegroundColor Red
    exit 1
}

# 3) Worker-required checks (Order workflow signal + query)
Write-Host "`n[3] Order workflow signal + query (requires worker running)..." -ForegroundColor Yellow

$signal = Invoke-Json -Method POST -Uri "$BaseUrl/temporal/examples/order/$orderWorkflowId/signal/add-item" -Headers $headers -Body @{ item = "item-1" }
$signalOk = Assert-SuccessResponse -Name "POST /temporal/examples/order/{id}/signal/add-item" -Result $signal -AllowedStatus @(200)

Start-Sleep -Seconds 1

$query = Invoke-Json -Method GET -Uri "$BaseUrl/temporal/examples/order/$orderWorkflowId/query/status" -Headers $headers
$queryOk = Assert-SuccessResponse -Name "GET /temporal/examples/order/{id}/query/status" -Result $query -AllowedStatus @(200)

if ($queryOk -and $query.body -and $query.body.data -and $query.body.data.status) {
    Write-Host "    status.state: $($query.body.data.status.state)" -ForegroundColor Gray
}

if (-not ($signalOk -and $queryOk)) {
    Write-Host "`nSignal/query failing usually means today's API is up but the Temporal worker is NOT running." -ForegroundColor Yellow
    Write-Host "Start it with: docker exec -it laravel_php ./vendor/bin/rr serve -c .rr.yaml" -ForegroundColor Yellow
    exit 2
}

Write-Host "`n[4] Payment monitor signal + query (requires worker running)..." -ForegroundColor Yellow

$pmSignal = Invoke-Json -Method POST -Uri "$BaseUrl/temporal/examples/payment-monitor/$paymentMonitorWorkflowId/signal/webhook" -Headers $headers -Body @{
    data = @{ reference = "00000000-0000-0000-0000-000000000000" }
}
$pmSignalOk = Assert-SuccessResponse -Name "POST /temporal/examples/payment-monitor/{id}/signal/webhook" -Result $pmSignal -AllowedStatus @(200)

Start-Sleep -Seconds 1

$pmQuery = Invoke-Json -Method GET -Uri "$BaseUrl/temporal/examples/payment-monitor/$paymentMonitorWorkflowId/query/status" -Headers $headers
$pmQueryOk = Assert-SuccessResponse -Name "GET /temporal/examples/payment-monitor/{id}/query/status" -Result $pmQuery -AllowedStatus @(200)

if ($pmQueryOk -and $pmQuery.body -and $pmQuery.body.data -and $pmQuery.body.data.status) {
    Write-Host "    payment_monitor.state: $($pmQuery.body.data.status.state)" -ForegroundColor Gray
}

if (-not ($pmSignalOk -and $pmQueryOk)) {
    Write-Host "`nPayment monitor signal/query failing usually means the Temporal worker is NOT running." -ForegroundColor Yellow
    exit 2
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Temporal workflow tests PASSED" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan

