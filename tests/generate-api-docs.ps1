# API Documentation Generator Script
# This script generates a list of all API routes for documentation

$repoRoot = Split-Path $PSScriptRoot -Parent
$outputFile = Join-Path $repoRoot "API_ROUTES.md"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "API Documentation Generator" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

Write-Host "`nGenerating API route documentation..." -ForegroundColor Yellow

# Get all routes from Laravel
$routesJson = docker exec laravel_php php artisan route:list --json 2>$null
if (-not $routesJson) {
    Write-Host "`nERROR: Could not read routes from Docker container 'laravel_php'." -ForegroundColor Red
    Write-Host "Make sure the stack is running first: docker-compose up -d" -ForegroundColor Yellow
    exit 1
}
$routes = $routesJson | ConvertFrom-Json

# Filter API routes only
$apiRoutes = $routes | Where-Object { $_.uri -like "api/*" }

# Group routes by controller
$groupedRoutes = $apiRoutes | Group-Object -Property {
    if ($_.action -match "App\\Http\\Controllers\\Api\\(\w+)Controller") {
        $Matches[1]
    } else {
        "Other"
    }
}

Write-Host "`nAPI Routes Summary:" -ForegroundColor Green
Write-Host "===================" -ForegroundColor Green

foreach ($group in $groupedRoutes | Sort-Object Name) {
    Write-Host "`n$($group.Name) Controller:" -ForegroundColor Yellow

    foreach ($route in $group.Group | Sort-Object uri) {
        $method = $route.method -replace "\|HEAD", ""
        $middleware = if ($route.middleware -match "jwt.auth") { "[Protected]" } else { "[Public]" }

        Write-Host ("  {0,-8} {1,-50} {2}" -f $method, $route.uri, $middleware) -ForegroundColor White
    }
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Route Statistics:" -ForegroundColor Cyan
Write-Host "Total API Routes: $($apiRoutes.Count)" -ForegroundColor White
Write-Host "Protected Routes: $(($apiRoutes | Where-Object { $_.middleware -match 'jwt.auth' }).Count)" -ForegroundColor White
Write-Host "Public Routes: $(($apiRoutes | Where-Object { $_.middleware -notmatch 'jwt.auth' }).Count)" -ForegroundColor White
Write-Host "========================================" -ForegroundColor Cyan

# Export to markdown file
Write-Host "`nExporting to API_ROUTES.md..." -ForegroundColor Yellow

$markdown = @"
# API Routes Documentation
Generated on: $(Get-Date -Format "yyyy-MM-dd HH:mm:ss")

## Purpose

This file is a **human-readable reference** of the current API routes.

- **Source of truth**: `src/routes/api.php`
- **Regenerate**: `.\tests\generate-api-docs.ps1` (auto-writes this file)
- **Auth docs**: [`HTTPONLY_COOKIE_AUTH.md`](HTTPONLY_COOKIE_AUTH.md), [`JWT_TOKEN_USAGE.md`](JWT_TOKEN_USAGE.md)

## Route Summary

| Controller | Total Routes | Protected | Public |
|------------|--------------|-----------|---------|
"@

foreach ($group in $groupedRoutes | Sort-Object Name) {
    $protected = ($group.Group | Where-Object { $_.middleware -match 'jwt.auth' }).Count
    $public = $group.Count - $protected
    $markdown += "`n| $($group.Name) | $($group.Count) | $protected | $public |"
}

$markdown += @"

## Detailed Routes

"@

foreach ($group in $groupedRoutes | Sort-Object Name) {
    $markdown += "`n### $($group.Name) Controller`n`n"
    $markdown += "| Method | URI | Name | Action | Auth | Middleware |`n"
    $markdown += "|--------|-----|------|--------|------|------------|`n"

    foreach ($route in $group.Group | Sort-Object uri) {
        $method = $route.method -replace "\|HEAD", ""
        $auth = if ($route.middleware -match "jwt.auth") { "Yes" } else { "No" }
        $name = if ($route.name) { "``$($route.name)``" } else { "" }
        $action = if ($route.action) { "``$($route.action)``" } else { "" }
        $mw = if ($route.middleware) { ($route.middleware -join ", ") } else { "" }
        $mw = if ($mw) { "``$mw``" } else { "" }
        $markdown += "| $method | ``$($route.uri)`` | $name | $action | $auth | $mw |`n"
    }
}

$markdown | Out-File -FilePath $outputFile -Encoding UTF8

Write-Host "Documentation exported to API_ROUTES.md" -ForegroundColor Green
Write-Host "`nDone!" -ForegroundColor Cyan


