<?php

/**
 * Return the translated participant notice about school-managed access.
 * @param array<string,mixed> $translations
 */
function openvsosh_access_notice_markup(array $translations): string
{
    $title = htmlspecialchars((string) $translations['ov_access_notice_title'], ENT_QUOTES, 'UTF-8');
    $situations = htmlspecialchars((string) $translations['ov_access_notice_situations'], ENT_QUOTES, 'UTF-8');
    $authority = htmlspecialchars((string) $translations['ov_access_notice_authority'], ENT_QUOTES, 'UTF-8');
    return '<section class="participant-access-notice" aria-label="' . $title . '">'
        . '<h2>' . $title . '</h2><p>' . $situations . '</p><p>' . $authority . '</p></section>';
}
