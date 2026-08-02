<?php

// The historical updater downloaded and executed an unsigned archive over plain HTTP. Remote
// self-update is deliberately disabled; deploy reviewed, signed release artifacts out of band.

require_once '../config/tce_config.php';
$pagelevel = K_AUTH_ADMINISTRATOR;
require_once '../../shared/code/tce_authorization.php';

http_response_code(410);
$thispage_title = 'UPDATE DISABLED';
require_once '../code/tce_page_header.php';

echo '<div class="container">' . K_NEWLINE;
echo '<h1>Automatic update is disabled</h1>' . K_NEWLINE;
echo '<p>Install reviewed releases through the documented deployment procedure.</p>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once __DIR__ . '/tce_page_footer.php';
