# Lead XLSX Import

Laravel Livewire app for importing leads from XLSX (streaming XMLReader, single transaction, failure history).

**Stack:** Laravel 13, Livewire 4, Filament Tables, PHP 8.5, MySQL 8, Docker Compose.

## Requirements

- [Docker](https://docs.docker.com/get-docker/) and [Docker Compose](https://docs.docker.com/compose/)
- Git
- Default ports: `5000` (HTTP), `8143` (HTTPS), `33036` (MySQL on the host)

PHP, Composer, and Node.js on the host are **not** required — everything runs inside the `php` container.

## Quick start

```bash
git clone <repository-url> test-import-file
cd test-import-file

cp .env.example .env
```

Adjust `.env` if needed:

| Variable | Default | Notes |
|----------|---------|-------|
| `APP_URL` | `http://127.0.0.1:5000` | Browser URL |
| `DB_HOST` | `db` | Docker service name |
| `DB_DATABASE` | `laravel_db` | |
| `DB_USERNAME` | `user_db` | |
| `DB_PASSWORD` | `password` | |

Initialize with Make:

```bash
make init
```

This will:

1. prepare `storage/logs/nginx`
2. build Docker images and start containers
3. run `composer install`
4. run `php artisan key:generate`
5. fix `storage` permissions
6. run `php artisan storage:link`
7. run `npm install` and `npm run build`

Then run migrations:

```bash
docker compose exec php php artisan migrate --no-interaction
```

Open the app: [http://127.0.0.1:5000](http://127.0.0.1:5000)

## Step by step (without Make)

```bash
cp .env.example .env

mkdir -p storage/logs/nginx
chmod -R 777 storage bootstrap/cache

docker compose build
docker compose up -d

docker compose exec -T php composer install
docker compose exec -T php php artisan key:generate --no-interaction
docker compose exec -T php php artisan storage:link
docker compose exec -T php php artisan migrate --no-interaction
docker compose exec -T php npm install
docker compose exec -T php npm run build
```

## Useful commands

All application commands go through the `php` container:

```bash
# containers
docker compose up -d
docker compose down
make up
make down

# Artisan
docker compose exec -T php php artisan migrate
docker compose exec -T php php artisan tinker

# Frontend
docker compose exec php npm run dev      # or: make dev
docker compose exec -T php npm run build # or: make prod

# Shell in the container
make php
# or:
docker compose exec php bash
```

## Docker services

| Service | Default container name | Role |
|---------|------------------------|------|
| `php` | `test_import_file_php` | PHP-FPM 8.5, Composer, Node 22 |
| `nginx` | `test_import_file_nginx` | web server (`:5000` → `:80`) |
| `db` | `test_import_file_db` | MySQL 8 (`:33036` → `:3306`) |

Override the name prefix with `DOCKER_SERVICES_NAME`. Ports: `DOCKER_SERVICES_URL_PORT`, `DOCKER_SERVICES_HTTPS_PORT`, `DOCKER_SERVICES_DB_PORT`.
