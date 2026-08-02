<?php
// The legacy interactive installer could rewrite application configuration and database tables.
// It is intentionally unavailable; supported installation is performed by install_cli.php.
if (PHP_SAPI === 'cli') {
    fwrite(STDERR, "The web installer is disabled. Run install/install_cli.php instead.\n");
    exit(1);
}

http_response_code(404);
header('Cache-Control: no-store');
exit();
