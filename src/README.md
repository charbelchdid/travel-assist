# Laravel API (Project Docs)

## Purpose

This `src/` folder contains the Laravel application code. The **main project documentation** (setup, running, testing, architecture, auth) lives at the repository root in [`README.md`](../README.md).

## How to use this project

- **Start the stack**: run `docker-compose up -d` from the repo root
- **API base URL**: `http://localhost:8080/api`
- **Health check**: `GET /api/health`

## How to develop

- **App code**: `src/app`
- **Routes**: `src/routes/api.php`
- **Run artisan**: `docker exec laravel_php php artisan <command>`
- **Run tests**: `docker exec laravel_php php artisan test`

## Related docs

- [`README.md`](../README.md)
- [`ARCHITECTURE.md`](../ARCHITECTURE.md)
- [`ENVIRONMENT.md`](../ENVIRONMENT.md)
