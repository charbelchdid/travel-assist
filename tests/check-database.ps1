# Database Status Check Script
# Shows what's currently in the database tables

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Database Status Check" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

# Check database tables
Write-Host "`n1. Current Database Tables:" -ForegroundColor Yellow
docker exec laravel_mysql mysql -u root -proot_password -e "USE laravel; SHOW TABLES;" 2>$null | Select-String -NotMatch "Warning"

# Check users table
Write-Host "`n2. Users Table (Count):" -ForegroundColor Yellow
$userCount = docker exec laravel_mysql mysql -u root -proot_password -e "USE laravel; SELECT COUNT(*) as count FROM users;" 2>$null | Select-String -NotMatch "Warning" | Select-String -NotMatch "count"
Write-Host "   Total Users: $userCount"

# Check sessions table
Write-Host "`n3. Sessions Table (Count):" -ForegroundColor Yellow
$sessionCount = docker exec laravel_mysql mysql -u root -proot_password -e "USE laravel; SELECT COUNT(*) as count FROM sessions;" 2>$null | Select-String -NotMatch "Warning" | Select-String -NotMatch "count"
Write-Host "   Active Sessions: $sessionCount"

# Check cache table
Write-Host "`n4. Cache Table (Count):" -ForegroundColor Yellow
$cacheCount = docker exec laravel_mysql mysql -u root -proot_password -e "USE laravel; SELECT COUNT(*) as count FROM cache;" 2>$null | Select-String -NotMatch "Warning" | Select-String -NotMatch "count"
Write-Host "   Cache Entries: $cacheCount"

# Check migrations
Write-Host "`n5. Applied Migrations:" -ForegroundColor Yellow
docker exec laravel_mysql mysql -u root -proot_password -e "USE laravel; SELECT migration FROM migrations;" 2>$null | Select-String -NotMatch "Warning" | Select-String -NotMatch "migration"

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Authentication Notes:" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

Write-Host "`n- Authentication is STATELESS (JWT-based)" -ForegroundColor White
Write-Host "- NO login records are stored in the database" -ForegroundColor White
Write-Host "- NO sessions are required for API authentication" -ForegroundColor White
Write-Host "- JWT tokens are validated without database queries" -ForegroundColor White
Write-Host "- User data is synced locally on login and then loaded by middleware when available" -ForegroundColor White

Write-Host "`nFor more details, see DATABASE_AUTHENTICATION.md" -ForegroundColor Cyan


