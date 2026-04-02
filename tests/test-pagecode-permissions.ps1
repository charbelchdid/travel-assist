# Test pageCode-based authorization gate (jwt.auth middleware)
param(
    [string]$Container = "laravel_php"
)

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "ERP Proxy Feature Tests" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

Write-Host "`nRunning Feature Test inside Docker..." -ForegroundColor Yellow
docker exec $Container php artisan test --testsuite=Feature --filter PageCodeAuthorizationTest

if ($LASTEXITCODE -ne 0) {
    Write-Host "`nFAILED: pageCode authorization tests failed." -ForegroundColor Red
    exit $LASTEXITCODE
}

Write-Host "`nRunning Admin E-Payment proxy tests inside Docker..." -ForegroundColor Yellow
docker exec $Container php artisan test --testsuite=Feature --filter AdminEpaymentProxyTest

if ($LASTEXITCODE -ne 0) {
    Write-Host "`nFAILED: Admin E-Payment proxy tests failed." -ForegroundColor Red
    exit $LASTEXITCODE
}

Write-Host "`nRunning Payment (public) proxy tests inside Docker..." -ForegroundColor Yellow
docker exec $Container php artisan test tests/Feature/PaymentProxyTest.php

if ($LASTEXITCODE -ne 0) {
    Write-Host "`nFAILED: Payment proxy tests failed." -ForegroundColor Red
    exit $LASTEXITCODE
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "OK: ERP proxy behaviors verified:" -ForegroundColor Green
Write-Host "  - pageCode=Permissions => 200 OK" -ForegroundColor Gray
Write-Host "  - pageCode=testPageCode => 403 Forbidden" -ForegroundColor Gray
Write-Host "  - GET /api/erp/epayment-transaction/statuses => 200 OK" -ForegroundColor Gray
Write-Host "  - POST /api/erp/epayment-transaction/create-transaction => 302 + Location (when ERP redirects)" -ForegroundColor Gray
Write-Host "  - POST /api/erp/epayment-transaction/create-transaction => 200 JSON (when ERP returns payload)" -ForegroundColor Gray
Write-Host "  - GET /api/payment/check-status => 200 OK (enveloped)" -ForegroundColor Gray
Write-Host "  - POST /api/payment/webhook-json => 200 OK (done)" -ForegroundColor Gray
Write-Host "========================================" -ForegroundColor Cyan

