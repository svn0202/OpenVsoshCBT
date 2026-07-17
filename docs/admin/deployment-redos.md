# Развёртывание OpenVsoshCBT на РЕД ОС

РЕД ОС является RHEL-совместимой системой. Имена пакетов и доступные версии
PHP/PostgreSQL проверяйте в репозитории установленного выпуска РЕД ОС.

## Требования

- PHP 8.2+ с `pgsql`, `gd`, `intl`, `bcmath`, `mbstring`, `zip`, `curl`,
  `xml`, `openssl`, `posix`;
- Composer;
- PostgreSQL;
- Apache HTTP Server или сервер с эквивалентной защитой служебных каталогов;
- HTTPS;
- SELinux в режиме `Enforcing`;
- Git для доставки кода.

Команды установки пакетов зависят от версии РЕД ОС. Сверяйтесь с
[официальной документацией РЕД ОС](https://redos.red-soft.ru/product/docs/).

## Получение кода

```sh
git clone git@github.com:svn0202/OpenVsoshCBT.git
cd OpenVsoshCBT
composer install --no-dev --optimize-autoloader
make lang
```

Для первого стенда можно использовать Docker (`make up`), но рабочий профиль
олимпиады должен быть отдельно проверен с PostgreSQL.

## Конфигурация и секреты

В репозитории хранятся `admin/config.default`, `public/config.default` и
`shared/config.default`. Web- или CLI-установщик копирует их в соответствующие
`*/config`; рабочие каталоги игнорируются Git.

Не добавляйте в Git:

- `shared/config/tce_db_config.php` с паролем БД;
- `shared/config/tce_general_constants.php` с `K_RANDOM_SECURITY`;
- SMTP, LDAP, CAS, RADIUS и иные реквизиты;
- рабочий сертификат и приватный ключ PDF;
- `.env`, дампы и резервные копии.

На рабочем сервере задайте `OPENVSOSHCBT_SOURCE_URL` ссылкой на точный тег или
commit развёрнутого релиза. Она показывается пользователям в нижней панели.

Для неинтерактивной установки используйте переменные из заголовка
`install/install_cli.php`. Не сохраняйте секреты в shell history, unit-файле с
общим чтением или CI-логе. После установки проверьте, что
`K_RANDOM_SECURITY` заменён уникальным случайным значением.

## PostgreSQL

Создайте отдельную базу и роль с правами только на неё. Если приложение и БД
на одном сервере, не открывайте PostgreSQL во внешнюю сеть. Для удалённой БД
ограничьте `listen_addresses`, firewall и `pg_hba.conf` адресом приложения и
используйте TLS.

Пример переменных CLI-установщика:

```sh
TCEXAM_DB_TYPE=POSTGRESQL \
TCEXAM_DB_HOST=127.0.0.1 TCEXAM_DB_PORT=5432 \
TCEXAM_DB_NAME=openvsoshcbt TCEXAM_DB_USER=openvsoshcbt \
TCEXAM_DB_PASSWORD='set-at-deploy-time' \
TCEXAM_PATH_HOST=https://exam.example.org \
TCEXAM_PATH_TCEXAM=/ TCEXAM_STANDARD_PORT=443 \
php install/install_cli.php
```

Не копируйте этот пример с буквальным паролем. Передавайте значение из
защищённого хранилища окружения развёртывания.

## Права и SELinux

Код доступен веб-серверу на чтение. На запись нужны только runtime-каталоги,
перечисленные в `install/README.md`, прежде всего `cache/` и
`admin/backup/`; во время установки также создаются рабочие `*/config`.

Не назначайте `chmod 777` всему проекту. Используйте владельца/группу
веб-сервера и минимальные права. Для SELinux:

- коду назначается тип содержимого веб-сервера;
- право записи даётся только runtime-каталогам;
- постоянные метки задаются через `semanage fcontext` и `restorecon`;
- при отказе сначала изучается AVC-журнал, а не отключается SELinux;
- доступ httpd к удалённой БД разрешается только если он действительно нужен.

Официальная справка: [SELinux в РЕД ОС 8](https://redos.red-soft.ru/base/redos-8_0/8_0-security/8_0-selinux/8_0-selinux_doc/).

## Apache и HTTPS

- перенаправляйте HTTP на HTTPS;
- запретите листинг каталогов;
- сохраните штатные `.htaccess` либо повторите ограничения в конфигурации
  Nginx/другого сервера;
- не отдавайте `*/config`, `admin/backup`, `cache` и Composer metadata;
- удалите или полностью закройте `install/` после установки;
- при reverse proxy передавайте корректные host/proto и задайте явный URL
  сервиса.

## Проверка перед переключением

1. Вход администратора и участника.
2. Создание группы, пользователя, темы, вопросов и теста.
3. Назначение группе и видимость в разрешённое время.
4. Сохранение всех используемых типов ответа, включая значение `0`.
5. Повторный вход и продолжение попытки.
6. Завершение, баллы, экспорт и PDF.
7. Отправка почты, если включена.
8. Проверка журнала PHP/Apache/PostgreSQL и SELinux.
9. `git status` не показывает конфиги, данные или секреты.

## Демо-сертификат

`shared/config.default/tcpdf.crt` оставлен для демонстрации. Он публичен и не
подходит для доверенной подписи. Рабочий ключ устанавливается вне Git.
