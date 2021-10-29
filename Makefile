SHELL:=/bin/bash
ARGS=`arg="$(filter-out $@,$(MAKECMDGOALS))" && echo $${arg:-${1}}`
VERSION=$(shell grep -o '^[0-9]\+\.[0-9]\+\.[0-9]\+' CHANGELOG.rst | head -n1)
VCS_BRANCH=$(shell git branch | grep ^* | awk '{ print $$2 }')
FORCE=$(shell (re=\\bforce\\b; [[ $(call ARGS,'') =~ $$re ]]) && echo "yes" || echo '')
# Colors
Color_Off=\033[0m
Cyan=\033[1;36m
Red=\033[1;31m

version:  ## Версия проекта
	@echo $(VERSION)

help:
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST) | sort
	
allow:
	@[ "$(VCS_BRANCH)" = 'master' -o "$(FORCE)" = 'yes' ] || \
		(echo -e '${Red}Данная операция может быть выполнена только из ветки ${Cyan}master${Color_Off}'; exit 1)

build:  ## Собрать контейнер
	@sudo chmod -R 777 php/ &&\
	 docker-compose build

start:  ## Запустить контейнер
	@docker-compose up -d web

stop:  ## Остановить контейнер
	@docker-compose down

update_kassa:  ##Обновить плагин для фискализации
	@cp -r -f komtet-kassa-bitrix/komtet.kassa php/bitrix/modules/ && cp -r -f komtet-kassa-bitrix/lib php/bitrix/modules/komtet.kassa

update_delivery:  ##Обновить плагин для доставки
	@cp -r -f komtet.delivery php/bitrix/modules/

tag: allow  ## Собрать tag
	@git tag -a $(VERSION) -m $(VERSION)

release:   ## Создать архивы для загрузок
	@helpers/create_dists.bash $(VERSION)

.PHONY: help update build start stop release tag create_dists
.DEFAULT_GOAL := help
