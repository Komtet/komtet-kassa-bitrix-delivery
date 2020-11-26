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

update:  ## Обновить плагин
	@tar -czvf komtet.delivery.tar.gz komtet.delivery/ && \
	 cp komtet.delivery.tar.gz docker_env/php/bitrix/modules/ && \
	 rm -R komtet.delivery.tar.gz &&\
	 cd docker_env/php/bitrix/modules/ &&\
	 sudo tar xvzf komtet.delivery.tar.gz

build: ## Создать контейнер
	@cd docker_env/ && \
	 docker run -d --name bitrix -p 5666:80 -p 2222:22 -p 443:443 -p 8893:8893 -p 8894:8894 -p 3306:3306 -v `pwd`/php:/home/bitrix/www -e BVAT_MEM=524288 -e TIMEZONE="Europe/Moscow" constb/bitrix-env && \
	 sudo chmod -R 777 php/

start: ## Запустить контейнер
	@cd docker_env/ && docker start bitrix

stop: ## Остановить контейнер
	@cd docker_env/ && docker stop bitrix

tag: allow  ## Собрать tag
	@git tag -a $(VERSION) -m $(VERSION)

release:   ## Создать архивы для загрузок
	@helpers/create_dists.bash $(VERSION)

.PHONY: help update build start stop release tag create_dists
.DEFAULT_GOAL := help
