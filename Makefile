SHELL:=/bin/bash

help:
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST) | sort

update:  ## Создать архив
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

release:  ## Архивировать для загрузки в маркет
	@cp -ar komtet.delivery .last_version && \
	tar\
	 --exclude='.last_version/lib/komtet-kassa-php-sdk/.*'\
	 --exclude='.last_version/lib/komtet-kassa-php-sdk/docker_env'\
	 --exclude='.last_version/lib/komtet-kassa-php-sdk/tests'\
	 -czvf .last_version.tar.gz .last_version/ && \
	rm -rf .last_version

.PHONY: help update build start stop release
.DEFAULT_GOAL := help
