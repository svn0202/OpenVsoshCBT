# Документация OpenVsoshCBT

Документация относится к OpenVsoshCBT на базе TCExam 17.2.4.

## Администратору

- [Развёртывание на РЕД ОС](admin/deployment-redos.md)
- [Регламент проведения олимпиады](admin/olympiad-runbook.md)
- [Создание вопросов на сопоставление](admin/matching-questions.md)
- [Обновление без потери рабочего экземпляра](admin/upgrade.md)
- [Решение известных проблем](admin/troubleshooting.md)
- [Карта официальной документации TCExam](admin/tcexam-reference.md)

## Разработчику

- [Стратегия форка и переноса TMFCBT](development/upstream-strategy.md)
- [Матрица переноса функций](development/feature-porting-matrix.md)
- [Лицензия и происхождение](development/licensing.md)
- [Журнал изменений OpenVsoshCBT](development/changes.md)

## Русский справочник TCExam

- [Общее описание](reference/description.md)
- [Основные возможности](reference/features.md)
- [Первый тест](reference/first-test.md)
- [Типы вопросов](reference/question-types.md)
- [Расчёт баллов](reference/scoring.md)
- [Безопасность](reference/security.md)
- [Установка](reference/installation.md)
- [Структура базы данных](reference/database.md)
- [Структура исходного кода](reference/source-code.md)

## Где документация TCExam

Оригинальные руководства TCExam находятся рядом в этом каталоге, а также в
`install/README.md`, `SECURITY.md` и `CONTRIBUTING.md`. Разделы `admin/`,
`development/` и `reference/` описывают OpenVsoshCBT, РЕД ОС и олимпиадный
процесс.

## Главный принцип эксплуатации

Git хранит код и безопасные шаблоны. Конкретный сервер хранит собственные
конфиги, секреты и runtime-данные в игнорируемых каталогах. Обновление кода не
должно перетирать базу, ответы, загрузки или ключи экземпляра.
