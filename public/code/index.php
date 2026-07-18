<?php

//============================================================+
// File name   : index.php
// Begin       : 2004-04-20
// Last Update : 2023-11-30
//
// Description : main user page - allows test selection
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Main page of TCExam Public Area.
 * @package com.tecnick.tcexam.public
 * @brief TCExam Public Area
 * @author Nicola Asuni
 * @since 2004-04-20
 */

require_once '../config/tce_config.php';

$pagelevel = K_AUTH_PUBLIC_INDEX;
$thispage_title = $l['t_test_list'];
$thispage_description = $l['hp_public_index'];

require_once '../../shared/code/tce_authorization.php';
require_once 'tce_page_header.php';
require_once '../../shared/code/tce_functions_test.php';
require_once '../../shared/code/tce_functions_onboarding.php';

$pending_onboarding = F_getPendingOnboardingTests((int) $_SESSION['session_user_id']);

echo '<div class="container">' . K_NEWLINE;

echo '<div class="catalog-welcome">' . K_NEWLINE;
echo '<p>Приветствуем вас на платформе тестирования!</p>' . K_NEWLINE;
echo '<div>Обязательно ознакомьтесь с инструкциями до начала работы с тестами. В большинстве тестов доступна только одна попытка.</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

if (!empty($pending_onboarding)) {
    echo '<section class="onboarding-prompt" id="onboarding-prompt" aria-labelledby="onboarding-title">' . K_NEWLINE;
    echo '<div class="onboarding-prompt-copy">' . K_NEWLINE;
    echo '<span class="onboarding-kicker">Перед началом олимпиады</span>' . K_NEWLINE;
    echo '<h2 id="onboarding-title">Познакомьтесь с платформой</h2>' . K_NEWLINE;
    echo '<p>Пройдите вводные тесты один раз. После завершения они исчезнут из этого блока.</p>' . K_NEWLINE;
    echo '<ol class="onboarding-steps">' . K_NEWLINE;
    foreach ($pending_onboarding as $intro_test) {
        echo '<li data-onboarding-test="' . (int) $intro_test['test_id'] . '">';
        echo '<span>' . htmlspecialchars((string) $intro_test['eyebrow'], ENT_QUOTES, $l['a_meta_charset']) . '</span>';
        echo '<strong>' . htmlspecialchars((string) $intro_test['label'], ENT_QUOTES, $l['a_meta_charset']) . '</strong>';
        echo '</li>' . K_NEWLINE;
    }
    echo '</ol></div>' . K_NEWLINE;
    echo '<div class="onboarding-test-list" id="onboarding-test-list"></div>' . K_NEWLINE;
    echo '</section>' . K_NEWLINE;
}

echo '<div class="catalog-toolbar" role="search">' . K_NEWLINE;
echo '<label for="catalog-search">Найти тест</label>' . K_NEWLINE;
echo '<div class="catalog-search-wrap">' . K_NEWLINE;
echo '<input type="search" id="catalog-search" placeholder="Предмет или класс" autocomplete="off" />' . K_NEWLINE;
echo '<span class="catalog-count" id="catalog-count" aria-live="polite"></span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="tcecontentbox">' . K_NEWLINE;
echo F_getUserTests();
echo '</div>' . K_NEWLINE;

echo '<div class="pagehelp">' . $thispage_description . '</div>' . K_NEWLINE;

echo '</div>' . K_NEWLINE;

echo <<<'HTML'
<script>
(function () {
    'use strict';
    var table = document.querySelector('table.testlist');
    var search = document.getElementById('catalog-search');
    var count = document.getElementById('catalog-count');
    if (!table || !search || !count) {
        return;
    }

    var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
    var catalogBox = table.closest('.tcecontentbox');
    var onboardingList = document.getElementById('onboarding-test-list');
    var onboardingIds = Array.prototype.slice.call(document.querySelectorAll('[data-onboarding-test]')).map(function (item) {
        return item.getAttribute('data-onboarding-test');
    });
    var dateFormatter = new Intl.DateTimeFormat('ru-RU', {
        day: 'numeric', month: 'long', year: 'numeric'
    });
    var timeFormatter = new Intl.DateTimeFormat('ru-RU', {
        hour: '2-digit', minute: '2-digit'
    });

    rows.forEach(function (row) {
        row.dataset.search = row.textContent.toLocaleLowerCase('ru-RU');
        var moments = [];
        [2, 3].forEach(function (position) {
            var cell = row.querySelector('td:nth-child(' + position + ')');
            if (!cell) {
                return;
            }
            var value = cell.textContent.trim().replace(' ', 'T');
            var parsed = new Date(value);
            if (!Number.isNaN(parsed.getTime())) {
                moments[position] = parsed;
                cell.textContent = '';
                var dateTime = document.createElement('span');
                dateTime.className = 'card-datetime';
                var date = document.createElement('span');
                date.className = 'card-date';
                date.textContent = dateFormatter.format(parsed).replace(' г.', '');
                var time = document.createElement('span');
                time.className = 'card-time';
                time.textContent = timeFormatter.format(parsed);
                dateTime.appendChild(date);
                dateTime.appendChild(time);
                cell.appendChild(dateTime);
            }
        });
        var statusCell = row.querySelector('td:nth-child(4)');
        var status = document.createElement('span');
        status.className = 'test-status';
        if (row.querySelector('a.buttongreen')) {
            row.classList.add('test-card-available');
            status.textContent = 'Доступен';
            var titleCell = row.querySelector('td:first-child');
            var actionCell = row.querySelector('td:nth-child(5)');
            var actionLink = actionCell && actionCell.querySelector('a.buttongreen');
            if (titleCell && actionLink) {
                titleCell.classList.add('has-inline-action');
                titleCell.insertBefore(actionLink, titleCell.firstChild);
                var titleStrong = titleCell.querySelector('strong');
                var titleLink = titleStrong && titleStrong.querySelector('a');
                if (titleLink) {
                    var titleParts = titleLink.textContent.trim().match(/^(.+?\.)\s+(.+)$/);
                    if (titleParts) {
                        titleLink.textContent = '';
                        var titleSeries = document.createElement('span');
                        titleSeries.className = 'title-series';
                        titleSeries.textContent = titleParts[1];
                        var titleSubject = document.createElement('span');
                        titleSubject.className = 'title-subject';
                        titleSubject.textContent = titleParts[2];
                        titleLink.appendChild(titleSeries);
                        titleLink.appendChild(titleSubject);
                    }
                }
            }
        } else if (row.querySelector('a.xmlbutton')) {
            row.classList.add('test-card-progress');
            status.textContent = 'В процессе';
        } else if (row.querySelector('a.buttonblue')) {
            row.classList.add('test-card-repeat');
            status.textContent = 'Можно повторить';
        } else if (moments[2] && moments[2].getTime() > Date.now()) {
            row.classList.add('test-card-upcoming');
            status.textContent = 'Скоро';
        } else {
            row.classList.add('test-card-closed');
            status.textContent = 'Завершён';
        }
        if (row.classList.contains('test-card-closed') || row.classList.contains('test-card-upcoming')) {
            var titleCell = row.querySelector('td:first-child');
            if (titleCell) {
                var lock = document.createElement('span');
                lock.className = 'test-lock-icon';
                lock.setAttribute('aria-label', 'Тест сейчас недоступен');
                titleCell.appendChild(lock);
            }
        }
        if (statusCell) {
            statusCell.textContent = '';
            statusCell.appendChild(status);
        }
    });

    function makeTestSection(className, title, description) {
        var section = document.createElement('section');
        section.className = 'catalog-section ' + className;
        var heading = document.createElement('div');
        heading.className = 'catalog-section-heading';
        heading.innerHTML = '<div><h2>' + title + '</h2><p>' + description + '</p></div><span class="section-count"></span>';
        var sectionTable = table.cloneNode(true);
        sectionTable.querySelector('tbody').textContent = '';
        section.appendChild(heading);
        section.appendChild(sectionTable);
        return section;
    }

    var activeSection = makeTestSection('catalog-section-active', 'Доступны сейчас', 'Тесты, которые можно начать или продолжить');
    var futureSection = makeTestSection('catalog-section-future', 'Будущие', 'Откроются позднее — ближайшие даты показаны первыми');
    var pastSection = makeTestSection('catalog-section-past', 'Завершены', 'Завершённые тесты — сначала самые недавние');
    var activeBody = activeSection.querySelector('tbody');
    var futureBody = futureSection.querySelector('tbody');
    var pastBody = pastSection.querySelector('tbody');
    var onboardingTable = null;
    var onboardingBody = null;
    if (onboardingList) {
        onboardingTable = table.cloneNode(true);
        onboardingTable.classList.add('onboarding-test-table');
        onboardingBody = onboardingTable.querySelector('tbody');
        onboardingBody.textContent = '';
        onboardingList.appendChild(onboardingTable);
    }

    rows.forEach(function (row) {
        var testId = row.getAttribute('data-test-id');
        if (onboardingBody && onboardingIds.indexOf(testId) !== -1) {
            row.classList.add('test-card-onboarding');
            onboardingBody.appendChild(row);
        } else if (row.classList.contains('test-card-available') || row.classList.contains('test-card-progress') || row.classList.contains('test-card-repeat')) {
            activeBody.appendChild(row);
        } else if (row.classList.contains('test-card-upcoming')) {
            futureBody.appendChild(row);
        } else {
            pastBody.appendChild(row);
        }
    });

    function timestamp(row, attribute) {
        var value = new Date((row.getAttribute(attribute) || '').replace(' ', 'T')).getTime();
        return Number.isNaN(value) ? 0 : value;
    }
    function sortBody(body, attribute, ascending) {
        Array.prototype.slice.call(body.children).sort(function (left, right) {
            var difference = timestamp(left, attribute) - timestamp(right, attribute);
            return ascending ? difference : -difference;
        }).forEach(function (row) { body.appendChild(row); });
    }
    sortBody(activeBody, 'data-end', true);
    sortBody(futureBody, 'data-begin', true);
    sortBody(pastBody, 'data-end', false);
    table.remove();
    catalogBox.appendChild(activeSection);
    catalogBox.appendChild(futureSection);
    catalogBox.appendChild(pastSection);
    if (onboardingTable && onboardingBody.children.length === 0) {
        document.getElementById('onboarding-prompt').hidden = true;
    }

    function updateCatalog() {
        var query = search.value.trim().toLocaleLowerCase('ru-RU');
        var visible = 0;
        rows.forEach(function (row) {
            var show = query === '' || row.dataset.search.indexOf(query) !== -1;
            row.hidden = !show;
            if (show) {
                visible += 1;
            }
        });
        [activeSection, futureSection, pastSection].forEach(function (section) {
            var sectionRows = Array.prototype.slice.call(section.querySelectorAll('tbody tr'));
            var sectionVisible = sectionRows.filter(function (row) { return !row.hidden; }).length;
            section.hidden = sectionVisible === 0;
            section.querySelector('.section-count').textContent = sectionVisible + ' тестов';
        });
        count.textContent = query === '' ? rows.length + ' тестов' : 'Найдено: ' + visible;
        catalogBox.classList.toggle('catalog-empty', visible === 0);
    }

    search.addEventListener('input', updateCatalog);
    updateCatalog();
}());
</script>
HTML;
echo K_NEWLINE;

require_once 'tce_page_footer.php';
