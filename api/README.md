# Sphere-Os — Backend API

Laravel 12 REST API powering the Sphere-Os platform.

## Stack

- **Framework**: Laravel 12 (PHP 8.3)
- **Database**: MySQL 8 (Docker) or PostgreSQL via Supabase
- **Auth**: Laravel Sanctum (session + token)
- **Queue**: Database driver
- **Testing**: PHPUnit

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

API runs on `http://localhost:8000`. When using Docker Compose from the root, it's proxied through Nginx at `http://localhost/api`.

## Common Commands

```bash
php artisan migrate          # Run migrations
php artisan migrate:fresh --seed  # Reset DB + seed demo data
php artisan test             # Run all tests
php artisan route:list       # List all API routes
php artisan queue:work       # Process background jobs
```

## Key Directories

```
api/
├── app/Http/Controllers/    # Request handlers
├── app/Models/              # Eloquent models
├── database/migrations/     # Schema migrations
├── database/seeders/        # Demo data seeders
├── routes/api.php           # API route definitions
└── tests/                   # PHPUnit test suites
```

## Environment

See `.env.example` for all required variables. Key ones:

| Variable | Description |
|----------|-------------|
| `APP_KEY` | Application encryption key (generate with `artisan key:generate`) |
| `DB_CONNECTION` | `mysql` for Docker local, `pgsql` for Supabase |
| `DB_HOST` | `db` when using Docker Compose, otherwise `127.0.0.1` |
| `DB_DATABASE` | `sphereos` |
