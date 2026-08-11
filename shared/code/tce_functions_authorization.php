<?php

//============================================================+
// File name   : tce_functions_authorization.php
// Begin       : 2001-09-26
// Last Update : 2023-11-30
//
// Description : Functions for Authorization / LOGIN
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Functions for Authorization / LOGIN
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2001-09-26
 */

/**
 * Returns XHTML / CSS formatted string for login form.<br>
 * The CSS classes used are:
 * <ul>
 * <li>div.login_form : container for login box</li>
 * <li>div.login_form div.login_row : container for label + input field or button</li>
 * <li>div.login_form div.login_row span.label : container for input label</li>
 * <li>div.login_form div.login_row span.formw : container for input form</li>
 * </ul>
 * @param faction String action attribute
 * @param fid String form ID attribute
 * @param fmethod String method attribute (get/post)
 * @param fenctype String enctype attribute
 * @param username String user name
 * @return string XHTML string for login form
 */
function f_login_form_markup(mixed $faction, mixed $fid, mixed $fmethod, mixed $fenctype, mixed $username): string
{
    global $l;
    /** @var array{
     *   ov_rcoko_alt: string, a_meta_charset: string, ov_login_intro: string,
     *   ov_login_intro_organization: string, w_username: string, h_login_name: string,
     *   ov_username_placeholder: string, w_password: string, h_password: string,
     *   ov_password_placeholder: string, ov_show_password: string, w_otpcode: string,
     *   h_otpcode: string, w_login: string, h_login_button: string, ov_access_control: string,
     *   t_user_registration: string, w_forgot_password: string, ov_login_support: string,
     *   ov_results_site: string
     * } $l
     */
    require_once '../config/tce_config.php';
    require_once '../../shared/config/tce_user_registration.php';
    require_once '../../shared/code/tce_functions_form.php';
    require_once '../../shared/code/tce_functions_openvsosh_settings.php';
    require_once '../../shared/code/tce_functions_site_assets.php';
    $access_settings = openvsosh_get_access_settings();
    $site_settings = openvsosh_get_site_settings();
    $str = '<div class="container login-container">' . K_NEWLINE;
    $str .= '<div class="tceformbox login-box">' . K_NEWLINE;
    $str .= '<div class="login-brand">' . K_NEWLINE;
    $logo_url = openvsosh_site_asset_metadata('logo')
        ? 'tce_site_asset.php?type=logo'
        : '../../images/vsosh-logo.png';
    $str .= '<img src="' . $logo_url . '" alt="'
        . htmlspecialchars($l['ov_rcoko_alt'], ENT_QUOTES, $l['a_meta_charset'])
        . '" width="77" height="77" />' . K_NEWLINE;
    $str .= '<p>' . htmlspecialchars($site_settings['site_name'], ENT_QUOTES, $l['a_meta_charset']) . '</p>' . K_NEWLINE;
    $str .= '</div>' . K_NEWLINE;
    $intro = $site_settings['welcome'] !== '' ? $site_settings['welcome'] : $l['ov_login_intro'];
    $description = $site_settings['site_description'] !== ''
        ? $site_settings['site_description']
        : $l['ov_login_intro_organization'];
    $str .= '<p class="login-intro">'
        . nl2br(htmlspecialchars($intro, ENT_QUOTES, $l['a_meta_charset'])) . '<br />'
        . '<strong>' . htmlspecialchars($description, ENT_QUOTES, $l['a_meta_charset'])
        . '</strong></p>' . K_NEWLINE;
    $str .=
        '<form action="'
        . (string) $faction
        . '" method="'
        . (string) $fmethod
        . '" id="'
        . (string) $fid
        . '" enctype="'
        . (string) $fenctype
        . '">'
        . K_NEWLINE;
    // user name
    $str .= get_form_row_text_input(
        'xuser_name',
        $l['w_username'],
        $l['h_login_name'],
        '',
        (string) $username,
        '',
        255,
        false,
        false,
        false,
        '',
        true,
        'username',
        '',
        $l['ov_username_placeholder'],
    );
    // password
    $str .= get_form_row_text_input(
        'xuser_password',
        $l['w_password'],
        $l['h_password'],
        '',
        '',
        '',
        255,
        false,
        false,
        true,
        '',
        true,
        'current-password',
        '',
        $l['ov_password_placeholder'],
    );
    $str .= '<button class="password-toggle" type="button" aria-label="'
        . htmlspecialchars($l['ov_show_password'], ENT_QUOTES, $l['a_meta_charset']) . '" '
        . 'aria-pressed="false">◉</button>' . K_NEWLINE;
    // One Time Password code (OTP)
    /** @var bool $otp_login */
    $otp_login = K_OTP_LOGIN;
    if ($otp_login) {
        $str .= get_form_row_text_input(
            'xuser_otpcode',
            $l['w_otpcode'],
            $l['h_otpcode'],
            '',
            '',
            '',
            255,
            false,
            false,
            true,
            '',
            true,
            'one-time-code',
        );
    }

    // buttons
    $str .= '<div class="row login-submit">' . K_NEWLINE;
    $str .=
        '<input type="submit" name="login" id="login" value="'
        . $l['w_login']
        . '" title="'
        . $l['h_login_button']
        . '" />'
        . K_NEWLINE;
    // the following field is used to check if the form has been submitted
    $str .= '<input type="hidden" name="logaction" id="logaction" value="login" />' . K_NEWLINE;
    $str .= '</div>' . K_NEWLINE;
    $str .= f_get_csrf_token_field() . K_NEWLINE;
    $str .= '</form>' . K_NEWLINE;
    if ($access_settings['registration_enabled'] || $access_settings['password_reset_enabled']) {
        $str .= '<nav class="login-access-actions" aria-label="'
            . htmlspecialchars($l['ov_access_control'], ENT_QUOTES, $l['a_meta_charset']) . '">' . K_NEWLINE;
        if ($access_settings['registration_enabled']) {
            $str .= '<a href="tce_user_registration.php">'
                . htmlspecialchars($l['t_user_registration'], ENT_QUOTES, $l['a_meta_charset'])
                . '</a>' . K_NEWLINE;
        }
        if ($access_settings['password_reset_enabled']) {
            $str .= '<a href="tce_password_reset.php">'
                . htmlspecialchars($l['w_forgot_password'], ENT_QUOTES, $l['a_meta_charset'])
                . '</a>' . K_NEWLINE;
        }
        $str .= '</nav>' . K_NEWLINE;
    }
    $str .= '<div class="login-support">' . K_NEWLINE;
    if ($site_settings['login_instruction'] !== '') {
        $str .= '<div class="login-site-instruction">'
            . nl2br(htmlspecialchars($site_settings['login_instruction'], ENT_QUOTES, $l['a_meta_charset']))
            . '</div>' . K_NEWLINE;
    }
    if ($access_settings['access_help'] !== '') {
        $str .= '<div class="login-access-help">'
            . nl2br(htmlspecialchars($access_settings['access_help'], ENT_QUOTES, $l['a_meta_charset']))
            . '</div>' . K_NEWLINE;
    } else {
        $str .= '<p>' . htmlspecialchars($l['ov_login_support'], ENT_QUOTES, $l['a_meta_charset']) . '</p>' . K_NEWLINE;
    }
    if ($site_settings['site_contact'] !== '') {
        $str .= '<p>' . htmlspecialchars($site_settings['site_contact'], ENT_QUOTES, $l['a_meta_charset'])
            . '</p>' . K_NEWLINE;
    }
    $str .= '<p>' . htmlspecialchars($l['ov_results_site'], ENT_QUOTES, $l['a_meta_charset']) . ': '
        . '<a href="https://vsoshlk.irro.ru">vsoshlk.irro.ru</a></p>' . K_NEWLINE;
    $str .= '</div>' . K_NEWLINE;
    $str .= '</div>' . K_NEWLINE;
    return $str . ('</div>' . K_NEWLINE);
}

/**
 * Display login page.
 * NOTE: This function calls exit() after execution.
 */
function f_login_form(): void
{
    global $l, $thispage_title;
    global $xuser_name, $xuser_password;
    /** @var array{
     *   m_authorization_denied: string, a_meta_language: string, a_meta_dir: string,
     *   a_meta_charset: string, w_login: string, t_login_form: string
     * } $l
     */
    require_once '../config/tce_config.php';

    // Keep the administration area from rendering its own copy of the participant login page.
    // Anonymous visitors use the regular public login and return to the requested admin page
    // after a successful operator/administrator login.
    /** @var array<string, bool|float|int|string|null> $server */
    $server = $_SERVER;
    $script_name = (string) ($server['SCRIPT_NAME'] ?? '');
    $admin_code_pos = strpos($script_name, '/admin/code/');
    if ((int) ($_SESSION['session_user_level'] ?? 0) === 0 && $admin_code_pos !== false) {
        $request_method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $request_uri = (string) ($server['REQUEST_URI'] ?? $script_name);
        $request_path = parse_url($request_uri, PHP_URL_PATH);
        if (
            in_array($request_method, ['GET', 'HEAD'], true)
            && is_string($request_path)
            && hash_equals($script_name, $request_path)
        ) {
            $_SESSION['session_login_redirect'] = $request_uri;
        }

        $install_path = substr($script_name, 0, $admin_code_pos);
        $login_page = $install_path . '/public/code/index.php';
        header('Location: ' . $login_page, true, 302);
        exit();
    }

    // HTTP-Basic authentication
    require_once '../../shared/config/tce_httpbasic.php';
    // @mago-expect analysis:redundant-logical-operation -- disabled in this installation, supported by shared code
    // @mago-expect analysis:impossible-condition -- deployments can enable HTTP Basic authentication in configuration
    if (K_HTTPBASIC_ENABLED && (!isset($_SESSION['logout']) || !$_SESSION['logout'])) {
        // force HTTP Basic Authentication
        header('WWW-Authenticate: Basic realm="TCExam"');
        header('HTTP/1.0 401 Unauthorized');
        require_once '../code/tce_page_header.php';
        F_print_error('WARNING', $l['m_authorization_denied']);
        require_once '../code/tce_page_footer.php';
        exit(); //break page here
    }

    // Shibboleth authentication
    require_once '../../shared/config/tce_shibboleth.php';
    // @mago-expect analysis:redundant-logical-operation -- disabled in this installation, supported by shared code
    // @mago-expect analysis:impossible-condition -- deployments can enable Shibboleth authentication in configuration
    if (K_SHIBBOLETH_ENABLED && (!isset($_SESSION['logout']) || !$_SESSION['logout'])) {
        // redirect to Shibboleth Login Page
        header('Location: ' . K_SHIBBOLETH_LOGIN);
        // html redirect
        echo '<!DOCTYPE html>' . K_NEWLINE;
        echo '<html lang="' . $l['a_meta_language'] . '" dir="' . $l['a_meta_dir'] . '">' . K_NEWLINE;
        echo '<head>' . K_NEWLINE;
        echo '<meta charset="' . $l['a_meta_charset'] . '" />' . K_NEWLINE;
        echo '<title>' . htmlspecialchars($l['w_login'], ENT_COMPAT, $l['a_meta_charset']) . '</title>' . K_NEWLINE;
        echo '<meta http-equiv="refresh" content="0" />' . K_NEWLINE; //reload page
        echo '</head>' . K_NEWLINE;
        echo '<body>' . K_NEWLINE;
        echo '<main id="maincontent">' . K_NEWLINE;
        echo '<a href="' . K_SHIBBOLETH_LOGIN . '">' . $l['w_login'] . '</a>' . K_NEWLINE;
        echo '</main>' . K_NEWLINE;
        echo '</body>' . K_NEWLINE;
        echo '</html>' . K_NEWLINE;
        exit(); //break page here
    }

    require_once '../../shared/code/tce_functions_form.php';
    $thispage_title = $l['t_login_form']; //set page title
    require_once '../code/tce_page_header.php';
    echo f_login_form_markup(
        $_SERVER['SCRIPT_NAME'],
        'form_login',
        'post',
        'multipart/form-data',
        $xuser_name,
    );
    require_once '../code/tce_page_footer.php';
    exit(); //break page here
}

/**
 * Display logout form.
 * @return string XHTML string for logout form.
 */
function f_logout_form(): string
{
    global $l;
    /** @var array{d_logout_desc: string, w_logout: string} $l */
    require_once '../config/tce_config.php';
    require_once '../../shared/code/tce_functions_form.php';
    $str = K_NEWLINE;
    $str .= '<div class="container">' . K_NEWLINE;
    $str .= '<div class="tceformbox">' . K_NEWLINE;
    $str .=
        '<form action="../code/tce_logout.php" method="post" id="form_logout" enctype="multipart/form-data">'
        . K_NEWLINE;
    // description
    $str .= '<div class="row">' . K_NEWLINE;
    $str .= $l['d_logout_desc'] . K_NEWLINE;
    $str .= '</div>' . K_NEWLINE;
    // buttons
    $str .= '<div class="row">' . K_NEWLINE;
    // the following field is used to check if form has been submitted
    $str .=
        '<input type="hidden" name="current_page" id="current_page" value="'
        . $_SERVER['SCRIPT_NAME']
        . '" />'
        . K_NEWLINE;
    $str .= '<input type="hidden" name="logaction" id="logaction" value="" />' . K_NEWLINE;
    $str .= '<input type="submit" name="login" id="login" value="' . $l['w_logout'] . '" />' . K_NEWLINE;
    $str .= '</div>' . K_NEWLINE;
    $str .= f_get_csrf_token_field() . K_NEWLINE;
    $str .= '</form>' . K_NEWLINE;
    return $str . ('</div>' . K_NEWLINE);
}

/**
 * Display logout page.
 * NOTE: This function calls exit() after execution.
 */
function f_logout_page(): void
{
    global $l, $thispage_title;
    /** @var array{t_logout_form: string} $l */
    require_once '../config/tce_config.php';
    $thispage_title = $l['t_logout_form']; // set page title
    require_once '../code/tce_page_header.php';
    echo F_logout_form();
    require_once '../code/tce_page_footer.php';
    exit();
}

/**
 * Returns true if the current user is authorized to update and delete the selected database record.
 * @author Nicola Asuni
 * @since 2006-03-11
 * @param $table (string) table to be modified
 * @param $field_id_name (string) name of the main ID field of the table
 * @param $value_id (int) value of the ID field of the table
 * @param $field_user_id (string) name of the foreign key to to user_id
 * @return boolean true if the user is authorized, false otherwise
 */
function f_is_authorized_user(mixed $table, mixed $field_id_name, mixed $value_id, mixed $field_user_id): bool
{
    global $l, $db;
    require_once '../config/tce_config.php';
    $table = F_escape_sql($db, $table);
    $field_id_name = F_escape_sql($db, $field_id_name);
    $value_id = (int) $value_id;
    $field_user_id = F_escape_sql($db, $field_user_id);
    $user_id = (int) ($_SESSION['session_user_id'] ?? 0);
    // check for administrator
    if (defined('K_AUTH_ADMINISTRATOR') && (int) ($_SESSION['session_user_level'] ?? 0) >= K_AUTH_ADMINISTRATOR) {
        return true;
    }

    // check for original author
    if (
        F_count_rows(
            $table
            . ' WHERE '
            . $field_id_name
            . '='
            . $value_id
            . ' AND '
            . $field_user_id
            . '='
            . $user_id
            . ' LIMIT 1',
        ) > 0
    ) {
        return true;
    }

    // check for author's groups
    // get author ID
    $author_id = 0;
    $sql = 'SELECT ' . $field_user_id . ' FROM ' . $table . ' WHERE ' . $field_id_name . '=' . $value_id . ' LIMIT 1';
    /** @var mixed $r */
    $r = F_db_query($sql, $db);
    if ($r) {
        /** @var mixed $m */
        $m = F_db_fetch_array($r);
        if (is_array($m)) {
            $author_id = (int) ($m[0] ?? 0);
        }
    } else {
        F_display_db_error();
    }

    return (
        $author_id > 1
        && F_count_rows(
            K_TABLE_USERGROUP
            . ' AS ta, '
            . K_TABLE_USERGROUP
            . ' AS tb
		WHERE ta.usrgrp_group_id=tb.usrgrp_group_id
			AND ta.usrgrp_user_id='
            . $author_id
            . '
			AND tb.usrgrp_user_id='
            . $user_id
            . '
			LIMIT 1',
        ) > 0
    );
}

/**
 * Returns a comma separated string of ID of the users that belong to the same groups.
 * @author Nicola Asuni
 * @since 2006-03-11
 * @param $user_id (int) user ID
 * @return string
 */
function f_get_authorized_users(mixed $user_id): string
{
    global $l, $db;
    require_once '../config/tce_config.php';
    $str = ''; // string to return
    $user_id = (int) $user_id;
    $sql = 'SELECT tb.usrgrp_user_id
		FROM ' . K_TABLE_USERGROUP . ' AS ta, ' . K_TABLE_USERGROUP . ' AS tb
		WHERE ta.usrgrp_group_id=tb.usrgrp_group_id
			AND ta.usrgrp_user_id=' . $user_id . '';
    /** @var mixed $r */
    $r = F_db_query($sql, $db);
    if ($r) {
        while (true) {
            /** @var mixed $m */
            $m = F_db_fetch_array($r);
            if (!is_array($m)) {
                break;
            }
            $str .= (string) ($m[0] ?? '') . ',';
        }
    } else {
        F_display_db_error();
    }

    // add the user
    $str .= $user_id;
    return $str;
}

/**
 * Sync user groups with the ones specified on the configuration file for alternate authentication.
 * @param $usrid (int) ID of the user to update.
 * @param $grpids (mixed) Group ID or comma separated list of group IDs (0=all available groups).
 * @author Nicola Asuni
 * @since 2012-09-11
 */
function f_sync_user_groups(mixed $usrid, mixed $grpids): void
{
    global $l, $db;
    require_once '../config/tce_config.php';
    $usrid = (int) $usrid;
    // select new group IDs
    $newgrps = [];
    if (is_string($grpids)) {
        // comma separated list of group IDs
        $newgrps = explode(',', $grpids);
        array_walk($newgrps, 'intval');
        $newgrps = array_unique($newgrps, SORT_NUMERIC);
    } elseif (f_legacy_int_equals($grpids, 0)) {
        // all available groups
        $sqlg = 'SELECT group_id FROM ' . K_TABLE_GROUPS . '';
        /** @var mixed $rg */
        $rg = F_db_query($sqlg, $db);
        if ($rg) {
            while (true) {
                /** @var mixed $mg */
                $mg = F_db_fetch_array($rg);
                if (!is_array($mg)) {
                    break;
                }
                $newgrps[] = (int) ($mg['group_id'] ?? 0);
            }
        } else {
            F_display_db_error();
        }
    } else {
        // single default group
        // @mago-expect analysis:mixed-operand -- alternate-auth configuration retains PHP's legacy scalar comparison
        if ($grpids > 0) {
            $newgrps[] = (int) $grpids;
        }
    }

    if ($newgrps === []) {
        return;
    }

    // select existing group IDs
    $usrgrps = [];
    $sqlu = 'SELECT usrgrp_group_id FROM ' . K_TABLE_USERGROUP . ' WHERE usrgrp_user_id=' . $usrid . '';
    /** @var mixed $ru */
    $ru = F_db_query($sqlu, $db);
    if ($ru) {
        while (true) {
            /** @var mixed $mu */
            $mu = F_db_fetch_array($ru);
            if (!is_array($mu)) {
                break;
            }
            $usrgrps[] = (int) ($mu['usrgrp_group_id'] ?? 0);
        }
    } else {
        F_display_db_error();
    }

    // extract missing groups
    $diffgrps = array_values(array_diff($newgrps, $usrgrps));
    // add missing groups
    foreach ($diffgrps as $grpid) {
        if ($grpid > 0) {
            // add user to default user groups
            $sql = 'INSERT INTO ' . K_TABLE_USERGROUP . ' (
				usrgrp_user_id,
				usrgrp_group_id
				) VALUES (
				\'' . $usrid . '\',
				\'' . $grpid . '\'
				)';
            /** @var mixed $r */
            $r = F_db_query($sql, $db);
            if (!$r) {
                F_display_db_error();
            }
        }
    }
}

/**
 * Check if the client has a valid SSL certificate.
 * @return bool True if the client has a valid SSL certificate, false otherwise.
 * @author Nicola Asuni
 * @since 2013-03-26
 */
function f_is_ssl_certificate_valid(): bool
{
    if (
        !isset($_SERVER['SSL_CLIENT_M_SERIAL'])
        || !isset($_SERVER['SSL_CLIENT_I_DN'])
        || !isset($_SERVER['SSL_CLIENT_V_END'])
        || !isset($_SERVER['SSL_CLIENT_VERIFY'])
        || $_SERVER['SSL_CLIENT_VERIFY'] !== 'SUCCESS'
        || !isset($_SERVER['SSL_CLIENT_V_REMAIN'])
        || $_SERVER['SSL_CLIENT_V_REMAIN'] <= 0
    ) {
        // invalid certificate
        return false;
    }

    // valid certificate
    return true;
}

/**
 * Get the hash code of the specified SSL certificate
 * @param string $cert String containing the certificate data.
 * @param boolean $pkcs12 Set this variable to true if the certificate is in PKCS12 format.
 * @return array containing the hash code and the validity end date in unix epoch.
 * @author Nicola Asuni
 * @since 2013-07-01
 */
function f_get_ssl_certificate_hash(string $cert, bool $pkcs12 = false): array
{
    if ($pkcs12) {
        /** @var array{cert?: string} $certs */
        $certs = [];
        openssl_pkcs12_read($cert, $certs, '');
        if (isset($certs['cert']) && is_string($certs['cert'])) {
            $cert = $certs['cert'];
        }
    }

    /** @return array<array-key, mixed> */
    $normalize_array = static fn(mixed $value): array => is_array($value) ? $value : [];
    $ssldata = $normalize_array(openssl_x509_parse($cert));
    $sslhash = '';
    $issuer = $normalize_array($ssldata['issuer'] ?? null);
    $subject = $normalize_array($ssldata['subject'] ?? null);
    $sslhash .= isset($ssldata['serialNumber']) ? bcdechex((string) $ssldata['serialNumber']) : '';
    $sslhash .= (string) ($issuer['C'] ?? '');
    $sslhash .= (string) ($issuer['ST'] ?? '');
    $sslhash .= (string) ($issuer['O'] ?? '');
    $sslhash .= (string) ($issuer['OU'] ?? '');
    $sslhash .= (string) ($issuer['CN'] ?? '');
    $sslhash .= (string) ($issuer['emailAddress'] ?? '');
    $sslhash .= (string) ($subject['C'] ?? '');
    $sslhash .= (string) ($subject['ST'] ?? '');
    $sslhash .= (string) ($subject['O'] ?? '');
    $sslhash .= (string) ($subject['OU'] ?? '');
    $sslhash .= (string) ($subject['CN'] ?? '');
    $sslhash .= (string) ($subject['emailAddress'] ?? '');
    $endtime = isset($ssldata['validTo_time_t']) ? (int) $ssldata['validTo_time_t'] : time();

    $sslhash .= $endtime;
    return [md5($sslhash), date(K_TIMESTAMP_FORMAT, $endtime)];
}

/**
 * Get the hash code for the client certificate
 * @return string containing the hash code.
 * @author Nicola Asuni
 * @since 2013-07-01
 */
function f_get_ssl_client_hash(): string
{
    $crthash = '';
    $crthash .= isset($_SERVER['SSL_CLIENT_M_SERIAL']) ? strtoupper($_SERVER['SSL_CLIENT_M_SERIAL']) : '';
    $crthash .= $_SERVER['SSL_CLIENT_I_DN_C'] ?? '';
    $crthash .= $_SERVER['SSL_CLIENT_I_DN_ST'] ?? '';
    $crthash .= $_SERVER['SSL_CLIENT_I_DN_O'] ?? '';
    $crthash .= $_SERVER['SSL_CLIENT_I_DN_OU'] ?? '';
    $crthash .= $_SERVER['SSL_CLIENT_I_DN_CN'] ?? '';
    $crthash .= $_SERVER['SSL_CLIENT_I_DN_Email'] ?? '';
    $crthash .= $_SERVER['SSL_CLIENT_S_DN_C'] ?? '';
    $crthash .= $_SERVER['SSL_CLIENT_S_DN_ST'] ?? '';
    $crthash .= $_SERVER['SSL_CLIENT_S_DN_O'] ?? '';
    $crthash .= $_SERVER['SSL_CLIENT_S_DN_OU'] ?? '';
    $crthash .= $_SERVER['SSL_CLIENT_S_DN_CN'] ?? '';
    $crthash .= $_SERVER['SSL_CLIENT_S_DN_Email'] ?? '';
    $crthash .= isset($_SERVER['SSL_CLIENT_V_END']) ? (string) strtotime($_SERVER['SSL_CLIENT_V_END']) : '';
    return md5($crthash);
}
