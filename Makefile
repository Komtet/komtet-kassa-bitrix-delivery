SHELL:=/bin/bash

help:
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST) | sort

tar:  ## Создать архив
	@tar -czvf komtet.delivery.tar.gz komtet.delivery/ && \
	 cp komtet.delivery.tar.gz /home/user/Projects/github/komtet-kassa-bitrix-delivery/docker_env/php/bitrix/modules/ && \
	 rm -R komtet.delivery.tar.gz

build: ## Создать контейнер
	@cd docker_env/ && \
	 docker run -d --name bitrix -p 5666:80 -p 2222:22 -p 443:443 -p 8893:8893 -p 8894:8894 -v `pwd`/php:/home/bitrix/www -e BVAT_MEM=524288 -e TIMEZONE="Europe/Moscow" constb/bitrix-env && sudo chmod -R 777 php/

start: ## Запустить контейнер
	@cd docker_env/ && docker start mysite

stop: ## Остановить контейнер
	@cd docker_env/ && docker stop mysite

.PHONY: help tar build start stop rm-tar
.DEFAULT_GOAL := help
