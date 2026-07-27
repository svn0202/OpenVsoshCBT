# Безопасное обновление

## Обновление из OpenVsoshCBT

1. Остановите изменение заданий и настроек.
2. Запишите текущий commit, `VERSION` и `RELEASE`.
3. Сделайте согласованную копию БД, `*/config`, `cache` и загрузок.
4. Разверните новый commit в соседний release-каталог.
5. Выполните `composer install --no-dev --optimize-autoloader` и `make lang`.
6. Сравните новые `config.default` с рабочими `config`; добавьте параметры
   вручную, не перенося чужие секреты.
7. Примените миграцию БД только на копии и только для точной версии.
8. Выполните smoke- и олимпиадный тест.
9. Переключите web-root/симлинк на новый release.
10. Сохраните предыдущий release для быстрого отката.

Не распаковывайте обновление поверх рабочего каталога и не удаляйте рабочие
конфиги. Не обновляйтесь в день мероприятия.

### Журналируемое применение миграций

Перед обновлением обязательно создайте полный дамп БД. Для существующей
установки примените ещё не выполненные миграции одной командой, передав те же
`TCEXAM_DB_*` и `TCEXAM_TABLE_PREFIX`, что использует приложение:

```bash
php install/migrate.php --dry-run
php install/migrate.php
```

Команда создаёт таблицу `tce_schema_migrations`, применяет файлы в порядке
релизов и записывает SHA-256 каждого файла. Если уже применённый файл был
изменён, выполнение останавливается. Для чистой БД, созданной из актуального
`*_db_structure.sql`, один раз выполните `php install/migrate.php --baseline`:
она только зарегистрирует уже присутствующую схему. На Oracle DDL не является
полностью транзакционным, поэтому резервная копия и отдельное тестовое
окружение обязательны.

### Миграция настроек доступа OpenVsoshCBT

Для версии с управлением регистрацией, сбросом пароля и инструкцией на странице
входа примените один файл:

- MySQL/MariaDB — `install/upgrade/mysql/openvsosh_access_settings.sql`;
- PostgreSQL — `install/upgrade/postgresql/openvsosh_access_settings.sql`;
- Oracle — `install/upgrade/oracle/openvsosh_access_settings.sql`.

Если у таблиц нестандартный префикс, замените `tce_` в выбранном файле.
Подробности: [Настройка получения доступа](access-settings.md).

### Миграция импорта Word и веса ответов

Перед первым использованием импорта Word в существующей базе примените:

- MySQL/MariaDB — `install/upgrade/mysql/openvsosh_word_import.sql`;
- PostgreSQL — `install/upgrade/postgresql/openvsosh_word_import.sql`;
- Oracle — `install/upgrade/oracle/openvsosh_word_import.sql`.

Миграция добавляет допускающий `NULL` столбец `answer_weight` с диапазоном
0–100. Пустое значение сохраняет стандартное оценивание TCExam. Подробности:
[Импорт вопросов из Microsoft Word](word-import.md).

### Миграция версий сохранения ответов

Для подтверждаемого сохранения и защиты от перезаписи более нового ответа
примените:

- MySQL/MariaDB — `install/upgrade/mysql/openvsosh_answer_save.sql`;
- PostgreSQL — `install/upgrade/postgresql/openvsosh_answer_save.sql`;
- Oracle — `install/upgrade/oracle/openvsosh_answer_save.sql`.

Миграция добавляет версию и идентификатор последней операции в журнал каждого
вопроса. Код и миграцию нужно разворачивать в одном техническом окне.
После развёртывания пересоберите языковой кеш командой `make lang`, затем
обновите открытую страницу экзамена: это переводит существующую сессию со
старого fingerprint на совместимый с AJAX вариант.
Подробности: [Надёжное сохранение ответов](answer-saving.md).

### Миграция перемешивания ответов отдельного вопроса

Чтобы преподаватель мог независимо включить перемешивание вариантов у
конкретного вопроса, примените:

- MySQL/MariaDB — `install/upgrade/mysql/openvsosh_question_shuffle.sql`;
- PostgreSQL — `install/upgrade/postgresql/openvsosh_question_shuffle.sql`;
- Oracle — `install/upgrade/oracle/openvsosh_question_shuffle.sql`.

Флаг дополняет общую настройку теста: если перемешивание включено хотя бы на
одном уровне, варианты вопроса выдаются в случайном порядке. Значение
сохраняется при копировании и обмене вопросами через TSV/XML.

### Миграция панели наблюдения

Для статусов присутствия, heartbeat, безопасного сброса и журнала действий
примените:

- MySQL/MariaDB — `install/upgrade/mysql/openvsosh_monitoring.sql`;
- PostgreSQL — `install/upgrade/postgresql/openvsosh_monitoring.sql`;
- Oracle — `install/upgrade/oracle/openvsosh_monitoring.sql`.

Миграция добавляет времена активности и сохранения ответа, причину закрытия
попытки и отдельный журнал действий наблюдателя. Подробности:
[Наблюдение за тестированием](monitoring.md).

### Миграция предварительной генерации

Для пакетной подготовки вариантов примените:

- MySQL/MariaDB — `install/upgrade/mysql/openvsosh_pregeneration.sql`;
- PostgreSQL — `install/upgrade/postgresql/openvsosh_pregeneration.sql`;
- Oracle — `install/upgrade/oracle/openvsosh_pregeneration.sql`.

Миграция добавляет признак предварительной генерации и отпечаток исходных
данных. Подробности: [Предварительная генерация вариантов](pregeneration.md).

### Миграция автономного режима

Для подписанных автономных пакетов и идемпотентного импорта примените:

- MySQL/MariaDB — `install/upgrade/mysql/openvsosh_offline.sql`;
- PostgreSQL — `install/upgrade/postgresql/openvsosh_offline.sql`;
- Oracle — `install/upgrade/oracle/openvsosh_offline.sql`.

Секрет подписи создаётся при первой выдаче и хранится в таблице настроек,
поэтому резервная копия должна включать всю БД. Подробности:
[Автономное электронное проведение](offline-exams.md).

### Условия доступа и завершения

Для prerequisites, минимального времени, обязательных ответов, управления
навигацией и сообщения после завершения примените:

- MySQL/MariaDB — `install/upgrade/mysql/openvsosh_test_access.sql`;
- PostgreSQL — `install/upgrade/postgresql/openvsosh_test_access.sql`;
- Oracle — `install/upgrade/oracle/openvsosh_test_access.sql`.

Подробности: [Условия доступа и безопасного завершения](test-access.md).

### Вложения к развёрнутым ответам

Для защищённых фото/PDF-вложений примените:

- MySQL/MariaDB — `install/upgrade/mysql/openvsosh_essay_attachments.sql`;
- PostgreSQL — `install/upgrade/postgresql/openvsosh_essay_attachments.sql`;
- Oracle — `install/upgrade/oracle/openvsosh_essay_attachments.sql`.

Сделайте каталог `cache/attachments` доступным на запись PHP и закройте к нему
прямой HTTP-доступ. Подробности:
[Вложения к развёрнутым ответам](essay-attachments.md).

## Синхронизация с официальным TCExam

```sh
git fetch upstream
git switch -c sync/tcexam-YYYY-MM-DD
git merge --no-ff upstream/main
```

Разрешайте конфликты в отдельной ветке. Выполните `make qa` и интеграционные
тесты PostgreSQL. Merge синхронизации не должен одновременно переносить новую
функцию TMFCBT: так проще ревью и откат.

## Обновление PHP

TCExam 17 требует PHP 8.2+ и проверяется upstream на 8.2–8.4. Для нового
minor-релиза PHP всё равно нужен отдельный стенд:

1. `composer install` с целевой версией;
2. полный журнал `E_ALL` без показа ошибок участнику;
3. `make qa`;
4. интеграционные тесты PostgreSQL;
5. импорт, почта, PDF, OMR и LaTeX, если используются;
6. нагрузочный тест и полный сценарий олимпиады.

## Локализация

Исходник переводов — `shared/config.default/lang/language_tmx.xml`. После
изменения выполните `make lang`. Не редактируйте сгенерированные
`cache/lang/*.php` и не коммитьте их. Пользовательские строки в PHP/JS должны
получаться из TMX; для JavaScript используйте безопасное JSON-кодирование.

## Откат

Откат кода допустим только вместе с совместимой схемой БД. Если миграция
необратима, восстановите БД и предыдущий release. Не копируйте отдельные
контроллеры из разных версий TCExam.

## Переход с legacy TMFCBT

Это не обычное обновление. Нужны снимок фактической схемы, собственная миграция
дополнительных полей, параллельный обезличенный тест и сравнение результатов.
До приёмки обе системы используют разные копии БД.
