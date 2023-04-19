# komtet-kassa-bitrix-delivery

Плагин для создания заявки на доставку из birtix

## Запуск проекта

- Скачать проект

```sh
git clone --recursive git@github.com:Komtet/komtet-kassa-bitrix-delivery.git
```

- Скачать установщик Bitrix CMS (Бизнес) - http://www.1c-bitrix.ru/download/cms.php

- Добавить в /etc/hosts 127.0.0.1 bitrix.localhost.ru

```sh
127.0.0.1 bitrix.localhost.ru
```

- Добавить bitrix_nginx.cfg в sites-enabled nginx

```sh
sudo cp [путь_до_проекта]/komtet-kassa-bitrix-delivery/bitrix_nginx.cfg /etc/nginx/sites-enabled
```

````
* Перезапустить nginx
```sh
sudo nginx -t
````

```sh
sudo nginx -s reload
```

- Создать папку php в папке komtet-kassa-bitrix-delivery

```sh
mkdir php
```

- Распаковать архив Bitrix CMS в папку php

- Установить права на папку php

```sh
sudo chmod -R 777 php
```

- Перейти в директорию проекта и запустить сборку контейнера

```sh
make build
```

- Запустить проект с php7

```sh
make start_web7
```

## Установка Bitrix

- Создание базы данных

```sh
    сервер : mysql
    имя пользователя : devuser
    пароль : devpass
    имя БД : test_db
    Порт : 9906
```

- Проект будет доступен по адресу: http://bitrix.localhost.ru

- Выполнить установку CMS Bitrix и обновить модули Битрикса из админки Битрикса.
  После чего запустить контейнер на необходимой версии php 8.x
  (https://helpdesk.bitrix24.ru/open/17332020/)

- Запустить проект с php 8.1

```sh
make start_web_8_1
```

- Запустить проект с php 8.2

```sh
make start_web_8_2
```

## Установка / обновление плагина Комтет Кассы для доставки

- Изменить кодировки всех файлов в папке плагина lang на UTF-8, сохранить.

- Установить / Обновить плагин Комтет Кассы для доставки (будет создан модуль в папке modules)

```sh
make update_delivery
```

## Установка / обновление плагина Комтет Кассы для фискализации чеков

- Если так же необходимо установить плагин Комтет Кассы для обычной фискализации, то клонируем репозиторий рядом с папкой плагина курьерского приложения:

```sh
git clone --recursive git@github.com:Komtet/komtet-kassa-bitrix.git
```

- Изменить кодировки всех файлов в папке плагина lang на UTF-8, сохранить.

- Установить / Обновить плагин Комтет Кассы для обычной фискализации (будет создан модуль в папке modules)

```sh
make update_kassa
```

- По ссылке https://www.1c-bitrix.ru/bsm_register.php получить тестовый лицензионный ключ
- Добавить тестовый лицензионный ключ на странице "Обновление платформы" http://bitrix.localhost.ru/bitrix/admin/update_system.php?lang=ru

## Доступные комманды из Makefile

- Собрать проект

```sh
make build
```

- Запустить проект

```sh
make start
```

- Остановить проект

```sh
make stop
```
