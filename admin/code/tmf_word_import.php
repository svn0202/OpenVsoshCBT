<?php

require_once '../config/tce_config.php';

$pagelevel = K_AUTH_ADMIN_IMPORT;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_auth_sql.php';
require_once '../code/tmf_word_import_lib.php';
require_once '../code/tmf_word_import_db.php';

if (isset($_GET['download']) && $_GET['download'] === 'template') {
    $template = F_tmf_word_import_template();
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="OpenVsoshCBT-Word-template.docx"');
    header('Content-Length: ' . strlen($template));
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo $template;
    exit();
}

// "confirm" is specific to this two-step importer and is not one of the
// standard actions recognized by tce_functions_form_admin.php.
if (isset($_POST['confirm'])) {
    if (empty($_POST['csrf_token']) || !check_csrf_token($_POST['csrf_token'])) {
        exit();
    }
    $menu_mode = 'confirm';
}
if (isset($_POST['cancelpreview'])) {
    if (empty($_POST['csrf_token']) || !check_csrf_token($_POST['csrf_token'])) {
        exit();
    }
    $menu_mode = 'cancelpreview';
}

$thispage_title = 'Импорт вопросов из Word';
$thispage_title_icon = '<i class="fas fa-file-word icon-gradient bg-sunny-morning"></i> ';
$message = '';
$error = '';
$preview = null;
$batch_id = '';

f_tmf_word_import_cleanup_stale(K_PATH_CACHE);

try {
    if (isset($menu_mode) && $menu_mode === 'upload' && !empty($_FILES['userfile']['name'])) {
        $batch_id = bin2hex(random_bytes(16));
        $uploaded_file = K_PATH_CACHE . 'wordimport-upload-' . $batch_id . '.docx';
        F_tmf_receive_word_upload('userfile', $uploaded_file);
        try {
            $media_dir = K_PATH_CACHE . 'wordimport/' . $batch_id;
            $media_url = K_PATH_URL_CACHE . 'wordimport/' . $batch_id;
            $parser = new TmfWordImporter($uploaded_file, $media_dir, $media_url);
            $preview = $parser->parse();
        } finally {
            if (is_file($uploaded_file)) {
                unlink($uploaded_file);
            }
        }

        $preview_dir = K_PATH_CACHE . 'wordimport-preview';
        if (!is_dir($preview_dir) && !mkdir($preview_dir, 0750, true) && !is_dir($preview_dir)) {
            throw new TmfWordImportException('Не удалось создать каталог предварительного просмотра.');
        }
        $preview_file = $preview_dir . '/' . $batch_id . '.php';
        if (
            file_put_contents(
                $preview_file,
                "<?php exit; ?>\n" . json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                LOCK_EX,
            ) === false
        ) {
            throw new TmfWordImportException('Не удалось сохранить предварительный просмотр.');
        }
        chmod($preview_file, 0640);
    } elseif (isset($menu_mode) && in_array($menu_mode, array('confirm', 'cancelpreview'), true)) {
        $batch_id = isset($_POST['batch_id']) ? $_POST['batch_id'] : '';
        if (!f_tmf_word_import_is_batch_id($batch_id)) {
            throw new TmfWordImportException('Некорректный идентификатор импорта.');
        }
        if ($menu_mode === 'cancelpreview') {
            f_tmf_word_import_cleanup_batch(K_PATH_CACHE, $batch_id);
            $message = 'Предварительный просмотр отменён, временные файлы удалены.';
            $batch_id = '';
            $preview = null;
        } else {
            $preview_file = K_PATH_CACHE . 'wordimport-preview/' . $batch_id . '.php';
            if (
                !is_file($preview_file)
                || (time() - filemtime($preview_file)) > TMF_WORD_IMPORT_PREVIEW_TTL
            ) {
                f_tmf_word_import_cleanup_batch(K_PATH_CACHE, $batch_id);
                throw new TmfWordImportException('Предварительный просмотр не найден или устарел.');
            }
            $preview_contents = file_get_contents($preview_file);
            $preview_separator = is_string($preview_contents) ? strpos($preview_contents, "\n") : false;
            if ($preview_separator === false) {
                f_tmf_word_import_cleanup_batch(K_PATH_CACHE, $batch_id);
                throw new TmfWordImportException('Повреждены данные предварительного просмотра.');
            }
            $preview = json_decode(substr($preview_contents, $preview_separator + 1), true);
            if (!is_array($preview)) {
                f_tmf_word_import_cleanup_batch(K_PATH_CACHE, $batch_id);
                throw new TmfWordImportException('Повреждены данные предварительного просмотра.');
            }
            $counts = F_tmf_import_word_questions($preview);
            f_tmf_word_import_cleanup_batch(K_PATH_CACHE, $batch_id, false);
            $message = sprintf(
                'Импорт завершён: модуль «%s», тема «%s», вопросов %d, ответов %d.',
                htmlspecialchars($counts['module_name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($counts['subject_name'], ENT_QUOTES, 'UTF-8'),
                $counts['questions'],
                $counts['answers'],
            );
            $preview = null;
        }
    }
} catch (Exception $exception) {
    if (isset($menu_mode) && $menu_mode === 'upload' && f_tmf_word_import_is_batch_id($batch_id)) {
        f_tmf_word_import_cleanup_batch(K_PATH_CACHE, $batch_id);
    }
    $error = $exception->getMessage();
}

require_once '../code/tce_page_header.php';

echo '<div class="container">' . K_NEWLINE;
echo '<div class="tceformbox">' . K_NEWLINE;
echo '<h1>Импорт DOCX</h1>' . K_NEWLINE;

if ($message !== '') {
    F_print_error('MESSAGE', $message);
}
if ($error !== '') {
    F_print_error('ERROR', htmlspecialchars($error, ENT_QUOTES, 'UTF-8'));
}

if (is_array($preview)) {
    echo '<h5>Предварительная проверка</h5>' . K_NEWLINE;
    echo
        '<p><strong>Модуль:</strong> '
            . htmlspecialchars($preview['module'], ENT_QUOTES, 'UTF-8')
            . '<br />'
            . K_NEWLINE
    ;
    echo '<strong>Тема:</strong> ' . htmlspecialchars($preview['topic'], ENT_QUOTES, 'UTF-8') . '<br />' . K_NEWLINE;
    echo '<strong>Вопросов:</strong> ' . count($preview['questions']) . '<br />' . K_NEWLINE;
    echo '<strong>Изображений:</strong> ' . intval($preview['statistics']['images']) . '</p>' . K_NEWLINE;
    if (!empty($preview['warnings'])) {
        echo '<div class="alert alert-warning"><strong>Предупреждения:</strong><ul>' . K_NEWLINE;
        foreach ($preview['warnings'] as $warning) {
            echo '<li>' . htmlspecialchars($warning, ENT_QUOTES, 'UTF-8') . '</li>' . K_NEWLINE;
        }
        echo '</ul></div>' . K_NEWLINE;
    }
    echo '<div class="scrollmenu"><table class="userselect">' . K_NEWLINE;
    echo
        '<thead><tr><th>№</th><th>Тип</th><th>Формулировка</th><th>Ответов</th><th>Ключ</th></tr></thead><tbody>'
            . K_NEWLINE
    ;
    $type_names = array(1 => 'одиночный', 2 => 'множественный', 3 => 'текст', 4 => 'порядок');
    foreach ($preview['questions'] as $question) {
        $description = trim(strip_tags($question['description']));
        if (mb_strlen($description, 'UTF-8') > 180) {
            $description = mb_substr($description, 0, 177, 'UTF-8') . '...';
        }
        echo '<tr><td>' . intval($question['source_number']) . '</td>';
        echo '<td>' . $type_names[$question['type']] . '</td>';
        echo '<td>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . count($question['answers']) . '</td>';
        echo
            '<td>'
                . htmlspecialchars(implode(', ', $question['right_keys']), ENT_QUOTES, 'UTF-8')
                . '</td></tr>'
                . K_NEWLINE
        ;
    }
    echo '</tbody></table></div>' . K_NEWLINE;
    echo
        '<form action="'
            . htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES, 'UTF-8')
            . '" method="post">'
            . K_NEWLINE
    ;
    echo
        '<input type="hidden" name="batch_id" value="'
            . htmlspecialchars($batch_id, ENT_QUOTES, 'UTF-8')
            . '" />'
            . K_NEWLINE
    ;
    echo f_get_csrf_token_field() . K_NEWLINE;
    F_submit_button('confirm', 'Импортировать', 'Создать модуль, тему, вопросы и ответы');
    F_submit_button('cancelpreview', 'Отменить', 'Удалить предварительный просмотр и временные файлы');
    echo '</form>' . K_NEWLINE;
} else {
    echo '<p><a class="xmlbutton" href="?download=template">Скачать шаблон DOCX</a></p>' . K_NEWLINE;
    echo
        '<p>Загрузите копию файла <code>.docx</code>. Сначала будет показан предварительный разбор; база данных изменится только после подтверждения.</p>'
            . K_NEWLINE
    ;
    echo
        '<p>Поддерживаются <code>MODULE:=</code>, <code>TOPIC:=</code>, <code>Q:n)</code>, варианты <code>A:)</code> и ключ <code>RIGHT:</code>, а также таблицы, изображения, формулы и метки TMF.</p>'
            . K_NEWLINE
    ;
    echo
        '<form action="'
            . htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES, 'UTF-8')
            . '" method="post" enctype="multipart/form-data">'
            . K_NEWLINE
    ;
    echo
        '<input type="hidden" name="MAX_FILE_SIZE" value="'
            . min(K_MAX_UPLOAD_SIZE, TmfWordImporter::MAX_DOCUMENT_BYTES)
            . '" />'
            . K_NEWLINE
    ;
    echo
        '<div class="row"><span class="label"><label for="userfile"><strong>Файл Word</strong></label></span>'
            . K_NEWLINE
    ;
    echo
        '<span class="formw"><input type="file" name="userfile" id="userfile" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required="required" /></span></div>'
            . K_NEWLINE
    ;
    echo f_get_csrf_token_field() . K_NEWLINE;
    F_submit_button('upload', 'Проверить файл', 'Загрузить DOCX для предварительной проверки');
    echo '</form>' . K_NEWLINE;
}

echo '</div></div>' . K_NEWLINE;
require_once '../code/tce_page_footer.php';

function F_tmf_receive_word_upload($field_name, $destination)
{
    if (!isset($_FILES[$field_name]) || !is_array($_FILES[$field_name])) {
        throw new TmfWordImportException('Файл не был передан.');
    }
    $file = $_FILES[$field_name];
    if (intval($file['error']) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new TmfWordImportException('Ошибка загрузки DOCX.');
    }
    if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'docx') {
        throw new TmfWordImportException('Разрешены только файлы .docx.');
    }
    if (intval($file['size']) <= 0 || intval($file['size']) > TmfWordImporter::MAX_DOCUMENT_BYTES) {
        throw new TmfWordImportException('Размер DOCX должен быть от 1 байта до 20 МБ.');
    }
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new TmfWordImportException('Не удалось сохранить загруженный DOCX.');
    }
    chmod($destination, 0640);
}
