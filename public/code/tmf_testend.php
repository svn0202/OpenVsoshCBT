<?php

// Retired clients must never finish an attempt or replay an unvalidated POST.
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'none'; form-action 'none'; base-uri 'none'");
$test = filter_var($_GET['testid'] ?? $_POST['testid'] ?? null, FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]);
$target = 'index.php';
if (is_int($test)) {
    $target = 'tce_test_execute.php?testid=' . $test;
}
$draft = array_intersect_key($_POST, array_flip(['answertext', 'answpos', 'testcomment', 'testid', 'testlogid']));
?>
<!doctype html>
<html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Обновление страницы теста</title>
<style>body{font:18px/1.5 system-ui,sans-serif;max-width:48rem;margin:3rem auto;padding:0 1rem}textarea{width:100%;min-height:12rem}a{display:inline-block;padding:1rem 0}</style>
<main><h1>Открыта старая страница теста</h1>
<p>Эта страница не завершила попытку и не сохранила ответ. Перейдите к актуальной странице, проверьте сохранённые ответы и завершите тест там.</p>
<?php if ($draft !== []) { ?>
<p>Перед переходом скопируйте отправленные данные ниже. Прикреплённые файлы нужно выбрать повторно.</p>
<label>Отправленные данные<textarea readonly><?= htmlspecialchars(
    (string) json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE),
    ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea></label>
<?php } ?>
<a href="<?= htmlspecialchars($target, ENT_QUOTES, 'UTF-8') ?>">Открыть актуальную страницу</a>
</main></html>
