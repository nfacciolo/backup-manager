.PHONY: run

PROJECT_NAME = my-custom-project-name
DATABASE_USERNAME = secret
DATABASE_PASSWORD = secret
DATABASE_NAME = secret


DOCKER_COMPOSE ?= PROJECT_NAME=$(PROJECT_NAME) DATABASE_USERNAME=$(DATABASE_USERNAME) DATABASE_PASSWORD=$(DATABASE_PASSWORD) DATABASE_NAME=$(DATABASE_NAME) docker compose
DOCKER_USER ?= "$(shell id -u):$(shell id -g)"
ENV ?= "dev"

chown:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run app chown -R 1000:www-data .

build:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) build

up:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) up

down:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) down

clean:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) down -v

sh:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec app sh

cc:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec app bin/console c:c

## Migrations commands
mm:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec app bin/console make:migration

m:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec app bin/console d:m:m

mp:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec app bin/console d:m:m prev

mn:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec app bin/console d:m:m next

## Debug commands
dr:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec app bin/console debug:router

## Tests commands
t:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec app php vendor/bin/phpunit

ns:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm -i node sh

nw:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) run --rm -i node "npm run watch"

docker-compose-check:
	@$(DOCKER_COMPOSE) version >/dev/null 2>&1 || (echo "Please install docker compose binary or set DOCKER_COMPOSE=\"docker-compose\" for legacy binary" && exit 1)
	@echo "You are using \"$(DOCKER_COMPOSE)\" binary"
	@echo "Current version is \"$$($(DOCKER_COMPOSE) version)\""





## Fixtures commands
f:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec app bin/console doctrine:fixtures:load --append

faa:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec app bin/console doctrine:fixtures:load --group=admin --append

fac:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec app bin/console doctrine:fixtures:load --group=channel --append

fad:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec app bin/console doctrine:fixtures:load --group=default --append

ffr:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec app bin/console doctrine:fixtures:load --group=fruits --append

fp:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec app bin/console doctrine:fixtures:load

fc:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec app bin/console dbal:run-sql "TRUNCATE TABLE coop_person, coop_orchard RESTART IDENTITY CASCADE"

addNico:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec app bin/console app:add-person nicolas facciolo facciolo.nicolas@gmail.com

addON:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec app bin/console app:add-orchard 26

lp:
	@ENV=$(ENV) DOCKER_USER=$(DOCKER_USER) $(DOCKER_COMPOSE) exec app bin/console app:list-person



