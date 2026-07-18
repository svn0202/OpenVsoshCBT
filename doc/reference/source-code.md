# Структура исходного кода

## Основные каталоги

| Путь | Назначение |
|---|---|
| `admin/` | административные контроллеры, темы и шаблоны конфигурации |
| `public/` | вход, список тестов, прохождение и результаты участника |
| `shared/` | общая бизнес-логика, DAL, TMX и общие конфиги |
| `install/` | web/CLI-установка, схемы и миграции БД |
| `doc/` | руководства upstream и русская документация OpenVsoshCBT |
| `test/` | unit- и integration-тесты |
| `docker/` | контейнерная конфигурация и entrypoint |
| `cache/` | runtime-кэш, не источник кода |

## Конфигурация

В Git хранятся `admin/config.default`, `public/config.default` и
`shared/config.default`. Установщик создаёт рабочие `*/config`, которые
игнорируются Git. Поэтому обновление исходников не должно заменять секреты и
локальные пути.

## Ключевые компоненты

- `shared/code/tce_functions_test.php` — генерация попытки, формы вопросов,
  сохранение ответов и расчёт баллов;
- `shared/code/tce_functions_test_stats.php` — результаты и статистика;
- `shared/code/tce_db_dal_*.php` — абстракция СУБД;
- `admin/code/tce_edit_test.php` — редактор теста;
- `admin/code/tce_edit_question.php` — редактор вопроса;
- `public/code/tce_test_execute.php` — прохождение теста;
- `shared/config.default/lang/language_tmx.xml` — исходник переводов.

## Зависимости и проверки

Зависимости описаны в `composer.json` и устанавливаются в игнорируемый
`vendor/`. Команды разработки собраны в `Makefile`:

```sh
make test
make lint
make qa
make dockertest DB_TYPE=postgres
```

## Разработка OpenVsoshCBT

Официальный TCExam подключён как remote `upstream`. Синхронизацию upstream и
собственные функции следует разделять по коммитам/PR. Изменение расчёта баллов
или схемы обязательно сопровождается тестом PostgreSQL и обновлением русской
документации.

Первоисточник: [API Documentation — TCExam](https://tcexam.org/docs/srcdoc/tcexam/).
