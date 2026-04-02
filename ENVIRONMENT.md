# Environment Configuration

## Purpose

This document lists environment variables and how to configure them for development.

## How to use this project

See [`README.md`](README.md) for setup/running. This file is the reference for what to put in `.env`.

## How to develop

- **Where `.env` lives**: inside the `src/` app directory when running in Docker
- **Apply env changes**: run `docker exec laravel_php php artisan config:clear`

---

This document describes all environment variables used in the Laravel API application.

## Required Environment Variables

These variables MUST be configured for the application to work properly:

### Application Settings
- `APP_NAME`: Application name (default: Laravel)
- `APP_ENV`: Environment (local, staging, production)
- `APP_KEY`: Application encryption key (generated with `php artisan key:generate`)
- `APP_DEBUG`: Debug mode (true for development, false for production)
- `APP_URL`: Application URL (default: http://localhost)

### Database Configuration
- `DB_CONNECTION`: Database type (mysql)
- `DB_HOST`: Database host (mysql for Docker, localhost for local)
- `DB_PORT`: Database port (3306)
- `DB_DATABASE`: Database name (laravel)
- `DB_USERNAME`: Database username (laravel_user)
- `DB_PASSWORD`: Database password (laravel_password)

### External Authentication Service
- `AUTH_BASE_URL`: Base URL for external auth service (https://testbackerp.teljoy.io)
- `JWT_SECRET_KEY`: (Optional) JWT secret key for signature verification in production

### ERP (MVPController integration)
- `ERP_BASE_URL`: Base URL for ERP endpoints (defaults to `AUTH_BASE_URL` if not set)
- `ERP_TIMEOUT`: ERP HTTP timeout in seconds (default: 30)
- `ERP_VERIFY_SSL`: Whether to verify SSL certificates when calling ERP (default: true)

### Temporal (Workflow Orchestration)
- `TEMPORAL_ADDRESS`: Temporal Frontend address (default: `temporal:7233` in Docker)
- `TEMPORAL_NAMESPACE`: Temporal namespace (default: `default`)
- `TEMPORAL_TASK_QUEUE`: Default task queue name for this app (default: `laravel-template`)

Notes:
- `TEMPORAL_ADDRESS` must be reachable **from where the code is running**:
  - If Laravel/worker are running in Docker, use a DNS/IP reachable from containers.
  - If running locally, you can use `127.0.0.1:7233`.
- The RoadRunner worker also has its own connection setting in `src/.rr.yaml` (`temporal.address`).

## Optional Environment Variables

### Logging
- `LOG_CHANNEL`: Logging channel (stack, single, daily, etc.)
- `LOG_LEVEL`: Minimum log level (debug, info, warning, error)

### Cache & Session
- `CACHE_STORE`: Cache driver (database, file, redis)
- `SESSION_DRIVER`: Session storage driver (database, file, redis)
- `SESSION_LIFETIME`: Session lifetime in minutes (120)

### Queue
- `QUEUE_CONNECTION`: Queue driver (database, redis, sync)

### Mail (if email functionality is added)
- `MAIL_MAILER`: Mail driver (smtp, log)
- `MAIL_HOST`: Mail server host
- `MAIL_PORT`: Mail server port
- `MAIL_USERNAME`: Mail server username
- `MAIL_PASSWORD`: Mail server password
- `MAIL_ENCRYPTION`: Encryption protocol (tls, ssl)
- `MAIL_FROM_ADDRESS`: Default from email
- `MAIL_FROM_NAME`: Default from name

## Docker-Specific Settings

When running with Docker, use these values:
```env
DB_HOST=mysql
REDIS_HOST=redis
MEMCACHED_HOST=memcached
```

When running locally without Docker:
```env
DB_HOST=127.0.0.1
REDIS_HOST=127.0.0.1
MEMCACHED_HOST=127.0.0.1
```

## Setting Up Environment

1. Create a `.env` inside `src/` (this repo is Docker-first, so `docker-compose.yml` already injects the required env vars).
   If you want to persist settings, create `src/.env` manually and add the variables from this document.

2. Generate application key:
```bash
docker exec laravel_php php artisan key:generate
```

3. Configure database credentials to match docker-compose.yml

4. Add AUTH_BASE_URL for external authentication

5. (Production) Add JWT_SECRET_KEY from your authentication service

## Environment File Security

- Never commit `.env` file to version control
- Use different credentials for different environments
- Rotate keys and passwords regularly
- Use secrets management in production (AWS Secrets Manager, Vault, etc.)
- Restrict file permissions: `chmod 600 .env`

## Validating Configuration

Test your environment configuration:
```bash
# Check database connection
docker exec laravel_php php artisan db:show

# Clear configuration cache
docker exec laravel_php php artisan config:clear

# View current configuration
docker exec laravel_php php artisan config:show
```
