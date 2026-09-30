# Define variables
APP_CONTAINER=php

init:
	sudo mkdir -p "storage/logs/nginx"
	sudo chmod -R 777 storage/logs/nginx
	docker compose build
	docker compose up -d
	docker compose exec -u fpm_user -t -i $(APP_CONTAINER) bash -c "composer install"
	docker compose exec -u fpm_user -t -i $(APP_CONTAINER) bash -c "php artisan key:generate"
	docker compose exec -u fpm_user -t -i $(APP_CONTAINER) bash -c "sudo chmod -R 777 storage/framework"
	docker compose exec -u fpm_user -t -i $(APP_CONTAINER) bash -c "sudo chmod -R 777 storage/logs"
	docker compose exec -u fpm_user -t -i $(APP_CONTAINER) bash -c "php artisan storage:link"
	docker compose exec $(APP_CONTAINER) bash -c "npm i"
	docker compose exec $(APP_CONTAINER) bash -c "npm run build"

build:
	docker compose build

up:
	docker compose up -d

dev:
	docker compose exec $(APP_CONTAINER) bash -c "npm run dev"

prod:
	docker compose exec $(APP_CONTAINER) bash -c "npm run build"

down:
	docker compose down

php:
	docker compose exec -u fpm_user -t -i $(APP_CONTAINER) bash

stan:
	docker compose exec -u fpm_user $(APP_CONTAINER) composer stan

stan-baseline:
	docker compose exec -u fpm_user $(APP_CONTAINER) composer stan-baseline

stan-clear:
	docker compose exec -u fpm_user $(APP_CONTAINER) composer stan-clear

