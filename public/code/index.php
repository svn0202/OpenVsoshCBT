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

echo '<div class="container">' . K_NEWLINE;

echo '<div class="catalog-welcome">' . K_NEWLINE;
echo '<p>Приветствуем вас на платформе тестирования!</p>' . K_NEWLINE;
echo '<div>Обязательно ознакомьтесь с инструкциями до начала работы с тестами. В большинстве тестов доступна только одна попытка.</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="catalog-toolbar" role="search">' . K_NEWLINE;
echo '<label for="catalog-search">Найти тест</label>' . K_NEWLINE;
echo '<div class="catalog-search-wrap">' . K_NEWLINE;
echo '<input type="search" id="catalog-search" placeholder="Предмет или класс" autocomplete="off" />' . K_NEWLINE;
echo '<span class="catalog-count" id="catalog-count" aria-live="polite"></span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="tcecontentbox">' . K_NEWLINE;
require_once '../../shared/code/tce_functions_test.php';

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
        count.textContent = query === '' ? rows.length + ' тестов' : 'Найдено: ' + visible;
        table.classList.toggle('catalog-empty', visible === 0);
    }

    search.addEventListener('input', updateCatalog);
    updateCatalog();
}());
</script>
HTML;
echo K_NEWLINE;

require_once 'tce_page_footer.php';
