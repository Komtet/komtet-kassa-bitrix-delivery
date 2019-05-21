# komtet-kassa-bitrix-delivery

Плагин для создания заявки на доставку из birtix

## Запуск проекта

* Скачать установщик Bitrix CMS (Бизнес) - http://www.1c-bitrix.ru/download/cms.php

* Добавить в /etc/hosts  127.0.0.1 bitrix.localhost.ru
* Добавить bitrix.cfg в sites-enabled nginx
```sh
sudo cp [путь_до_проекта]/komtet-kassa-bitrix-delivery/docker_env/bitrix.cfg /etc/nginx/sites-enabled
```
* Cоздать в дирректории docker_env папку php
* Распаковать архив Bitrix CMS в папку docker_env/php
* Установить права на папку docker_env/php
```sh
sudo chmod -R 777 docker_env/php
```
* Перейти в директорию проекта и запустить сборку контейнера
```sh
cd ../ && make build
```
* Запустить проект
```sh
make start
```
* Проект будет доступен по адресу: http://bitrix.localhost.ru
* Выполнить установку CMS, указать существующую базу данных sitemanager0, нового пользователя root без пароля
* По ссылке https://www.1c-bitrix.ru/bsm_register.php получить тестовый лицензионный ключ
* Добавить тестовый лицензионный ключ на странице "Обновление платформы" http://bitrix.localhost.ru/bitrix/admin/update_system.php?lang=ru

* Собрать архив с модулем (Система автоматически создаст архив в папке 'modules')
```sh
make tar
```

* Распаковать архив из Bitrix CMS: http://bitrix.localhost.ru/bitrix/admin/fileman_admin.php?PAGEN_1=3&SIZEN_1=20&lang=ru&path=%2Fbitrix%2Fmodules&site=s1

* Утсновить модуль из Bitrix CMS: http://bitrix.localhost.ru/bitrix/admin/partner_modules.php?lang=ru

## Доступные комманды из Makefile

* Собрать проект
```sh
make build
```
* Запустить проект
```sh
make start
```

* Остановить проект
```sh
make stop
```

* Собрать архив
```sh
make tar
```
