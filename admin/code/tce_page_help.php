<?php

//============================================================+
// File name   : tce_page_help.php
// Begin       : 2026-07-18
// Last Update : 2026-07-18
//
// Description : Local OpenVsoshCBT help landing page.
//============================================================+

require_once '../config/tce_config.php';

$pagelevel = K_AUTH_ADMIN_INFO;
require_once '../../shared/code/tce_authorization.php';

$thispage_title = 'Помощь OpenVsoshCBT';
require_once 'tce_page_header.php';

$help_topics = [
    [
        'category' => 'Организатору',
        'title' => 'Регламент проведения олимпиады',
        'description' => 'Подготовка площадки, действия организаторов и контроль во время проведения.',
        'link' => '../../doc/admin/olympiad-runbook.md',
    ],
    [
        'category' => 'Задания',
        'title' => 'Создание вопросов на сопоставление',
        'description' => 'Пошаговая инструкция по настройке заданий с парами вариантов.',
        'link' => '../../doc/admin/matching-questions.md',
    ],
    [
        'category' => 'Быстрый старт',
        'title' => 'Первый тест',
        'description' => 'Краткий путь от создания материалов до запуска тестирования.',
        'link' => '../../doc/reference/first-test.md',
    ],
    [
        'category' => 'Справочник',
        'title' => 'Типы вопросов и расчёт баллов',
        'description' => 'Справочник по доступным заданиям и правилам оценивания.',
        'link' => '../../doc/reference/question-types.md',
    ],
    [
        'category' => 'Поддержка',
        'title' => 'Решение известных проблем',
        'description' => 'Диагностика типовых сбоев и способы восстановления работы.',
        'link' => '../../doc/admin/troubleshooting.md',
    ],
    [
        'category' => 'Эксплуатация',
        'title' => 'Безопасное обновление',
        'description' => 'Как обновлять систему без потери конфигурации и данных экземпляра.',
        'link' => '../../doc/admin/upgrade.md',
    ],
];

echo '<div class="container local-help">' . K_NEWLINE;
echo '<section class="local-help-hero" aria-labelledby="local-help-intro">' . K_NEWLINE;
echo '<span class="local-help-eyebrow">База знаний</span>' . K_NEWLINE;
echo '<p id="local-help-intro">Инструкции для подготовки площадки, настройки заданий и проведения олимпиады.</p>' . K_NEWLINE;
echo '<div class="local-help-actions">' . K_NEWLINE;
echo '<a class="local-help-primary" href="../../doc/README.md">Все инструкции</a>' . K_NEWLINE;
echo '<a class="local-help-secondary" href="mailto:olymp@gia66.ru">Написать в поддержку</a>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;
echo '</section>' . K_NEWLINE;
echo '<div class="local-help-section-heading"><h2>Популярные материалы</h2><span>Выберите нужный раздел</span></div>' . K_NEWLINE;
echo '<div class="local-help-grid">' . K_NEWLINE;
foreach ($help_topics as $topic) {
    echo '<a class="local-help-card" href="' . htmlspecialchars($topic['link'], ENT_QUOTES, $l['a_meta_charset']) . '">' . K_NEWLINE;
    echo '<span class="local-help-category">' . htmlspecialchars($topic['category'], ENT_QUOTES, $l['a_meta_charset']) . '</span>' . K_NEWLINE;
    echo '<strong class="local-help-card-title">' . htmlspecialchars($topic['title'], ENT_QUOTES, $l['a_meta_charset']) . '</strong>' . K_NEWLINE;
    echo '<span class="local-help-card-description">' . htmlspecialchars($topic['description'], ENT_QUOTES, $l['a_meta_charset']) . '</span>' . K_NEWLINE;
    echo '<span class="local-help-card-more">Открыть <span aria-hidden="true">→</span></span>' . K_NEWLINE;
    echo '</a>' . K_NEWLINE;
}
echo '</div>' . K_NEWLINE;
echo '<aside class="local-help-support"><div><strong>Не нашли ответ?</strong><span>Служба поддержки поможет разобраться с работой площадки.</span></div><a href="mailto:olymp@gia66.ru">olymp@gia66.ru</a></aside>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once 'tce_page_footer.php';
