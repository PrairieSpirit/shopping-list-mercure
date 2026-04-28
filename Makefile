# Автоматичне визначення Docker Compose команди
DOCKER_COMPOSE := $(shell command -v docker-compose > /dev/null 2>&1 && echo "docker-compose" || echo "docker compose")

PHP = $(DOCKER_COMPOSE) exec php
MYSQL = $(DOCKER_COMPOSE) exec mysql

.PHONY: up down build install migrate create-test-db test shell logs reset cc

up:
	$(DOCKER_COMPOSE) up -d

down:
	$(DOCKER_COMPOSE) down --remove-orphans

build:
	$(DOCKER_COMPOSE) build --no-cache

install:
	$(PHP) composer install --no-interaction --prefer-dist

migrate:
	$(PHP) php bin/console doctrine:database:create --if-not-exists
	$(PHP) php bin/console doctrine:migrations:migrate --no-interaction

create-test-db:
	$(MYSQL) mysql -u root -proot -e "CREATE DATABASE IF NOT EXISTS symfony_test; GRANT ALL PRIVILEGES ON symfony_test.* TO 'symfony_user'@'%'; FLUSH PRIVILEGES;"

test:
	$(DOCKER_COMPOSE) exec -e APP_ENV=test php sh -c "\
	php bin/console doctrine:database:create --if-not-exists && \
	php bin/console doctrine:migrations:migrate --no-interaction && \
	php vendor/bin/phpunit --colors=always"

shell:
	$(PHP) bash
# 	docker compose exec php bash

logs:
	$(DOCKER_COMPOSE) logs -f

reset: down
	docker volume rm shopping-list_mysql_data 2>/dev/null || true
	$(MAKE) up install migrate

cc:
	$(PHP) php bin/console cache:clear
