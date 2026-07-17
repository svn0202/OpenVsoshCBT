# Карта официальной документации TCExam

Локальные документы не копируют сайт целиком. Ссылки ведут к первоисточнику,
а здесь указана применимость к OpenVsoshCBT.

## Официальные источники

- [Документация TCExam](https://tcexam.org/docs/?lang=en)
- [Официальный Git-репозиторий](https://github.com/tecnickcom/tcexam)
- [Описание](https://tcexam.org/docs/description/)
- [Функции](https://tcexam.org/docs/features/)
- [Первый тест](https://tcexam.org/docs/first_test/)
- [Типы вопросов](https://tcexam.org/docs/question-types/)
- [Расчёт баллов](https://tcexam.org/docs/scoring/)
- [Безопасность](https://tcexam.org/docs/security/)
- [Установка](https://tcexam.org/docs/installation/)
- [Документация БД](https://tcexam.org/docs/database_docs/)
- [Документация исходного кода](https://tcexam.org/docs/srcdoc/tcexam/)

Наиболее актуальные технические инструкции входят в репозиторий:

- `install/README.md` — полная установка;
- `doc/UPGRADE.md` — обновление;
- `doc/TRANSLATORS.md` — локализация;
- `doc/LATEX.md` — формулы;
- `SECURITY.md` — безопасность;
- `CONTRIBUTING.md` — разработка.

## Что считать устаревшим

Старые страницы могут описывать TCExam 14 и рекомендовать слишком широкие
права вроде `chmod 777`. Для OpenVsoshCBT применяются инструкции TCExam 17 из
текущего Git и принцип минимальных прав/SELinux.

## Первый пробный тест

1. Создайте группу и участника.
2. Создайте модуль и тему.
3. Создайте и включите вопросы/ответы.
4. Создайте тест и набор тем.
5. Назначьте тест группе и задайте период.
6. Настройте длительность, попытки, баллы и результаты.
7. Завершите весь сценарий контрольным участником.

Для олимпиады этого недостаточно: выполните
[расширенный регламент](olympiad-runbook.md).
