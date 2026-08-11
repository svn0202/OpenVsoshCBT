<?php

//============================================================+
// File name   : tce_authorization.php
// Begin       : 2001-09-26
// Last Update : 2023-11-30
//
// Description : Check user authorization level.
//               Grants / deny access to pages.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * This script handles user's sessions.
 * Just the registered users granted with a username and a password are entitled to access the restricted areas (level > 0) of TCExam and the public area to perform the tests.
 * The user's level is a numeric value that indicates which resources (pages, modules, services) are accessible by the user.
 * To gain access to a specific resource, the user's level must be equal or greater to the one specified for the requested resource.
 * TCExam has 10 predefined user's levels:<ul>
 * <li>0 = anonymous user (unregistered).</li>
 * <li>1 = basic user (registered);</li>
 * <li>2-9 = configurable/custom levels;</li>
 * <li>10 = administrator with full access rights</li>
 * </ul>
 * @package com.tecnick.tcexam.shared
 * @brief TCExam Shared Area
 * @author Nicola Asuni
 * @since 2001-09-26
 */

require_once '../config/tce_config.php';
require_once '../../shared/code/tce_functions_authorization.php';
require_once '../../shared/code/tce_functions_roles.php';
require_once '../../shared/code/tce_functions_session.php';
require_once '../../shared/code/tce_functions_otp.php';

/** @var mixed $db */
/** @var string $PHPSESSID */
/**
 * @var array{
 *     a_meta_language:string,a_meta_dir:string,a_meta_charset:string,w_logout:string,
 *     m_login_brute_force:string,m_login_wrong:string,t_login_form:string,
 *     m_ssl_certificate_required:string,w_index:string,m_wrong_test_password:string
 * } $l
 */
/** @var array{REMOTE_ADDR:string,REQUEST_METHOD:string,REQUEST_URI?:string,SCRIPT_NAME?:string} $server */
$server = $_SERVER;

$logged = false; // the user is not yet logged in

// --- read existing user's session data from database
$PHPSESSIDSQL = openvsosh_authorization_string(F_escape_sql($db, $PHPSESSID));
$fingerprintkey = get_client_fingerprint();
$sqls = 'SELECT * FROM ' . K_TABLE_SESSIONS . " WHERE cpsession_id='" . $PHPSESSIDSQL . "'";
/** @var object|resource|bool $rs */
$rs = F_db_query($sqls, $db);
if ($rs) {
    $ms = openvsosh_authorization_row(F_db_fetch_array($rs));
    if ($ms) { // the user's session already exist
        /** @var array{cpsession_data:string} $ms */
        // decode session data
        session_decode($ms['cpsession_data']);
        // check for possible session hijacking
        $legacy_fingerprint = get_legacy_client_fingerprint();
        $session_hash = isset($_SESSION['session_hash'])
            ? openvsosh_authorization_string($_SESSION['session_hash'])
            : null;
        $fingerprint_matches = $session_hash !== null
            && (
                hash_equals($session_hash, $fingerprintkey)
                || hash_equals($session_hash, $legacy_fingerprint)
            );
        if (
            openvsosh_authorization_bool(K_CHECK_SESSION_FINGERPRINT)
            && !$fingerprint_matches
        ) {
            // display login form
            session_regenerate_id(true);
            F_login_form();
            exit();
        }
        if ($fingerprint_matches && !hash_equals($session_hash, $fingerprintkey)) {
            $_SESSION['session_hash'] = $fingerprintkey;
        }

        // update session expiration time
        $expiry = date(K_TIMESTAMP_FORMAT, time() + openvsosh_authorization_int(K_SESSION_LIFE));
        $sqlx =
            'UPDATE '
            . K_TABLE_SESSIONS
            . " SET cpsession_expiry='"
            . $expiry
            . "' WHERE cpsession_id='"
            . $PHPSESSIDSQL
            . "'";
        /** @var object|resource|bool $rx */
        $rx = F_db_query($sqlx, $db);
        if (!$rx) {
            F_display_db_error();
        }
    } else { // session do not exist so, create new anonymous session
        $_SESSION['session_hash'] = $fingerprintkey;
        $_SESSION['session_user_id'] = 1;
        $_SESSION['session_user_name'] = '- [' . substr($PHPSESSID, 12, 8) . ']';
        $_SESSION['session_user_ip'] = get_normalized_ip($server['REMOTE_ADDR']);
        $_SESSION['session_user_level'] = 0;
        $_SESSION['session_user_firstname'] = '';
        $_SESSION['session_user_lastname'] = '';
        $_SESSION['session_test_login'] = '';
        // read client cookie
        $_SESSION['session_last_visit'] = isset($_COOKIE['LastVisit']) ? (int) $_COOKIE['LastVisit'] : 0;

        // set client cookie
        $cookie_now_time = time(); // note: while time() function returns a 32 bit integer, it works fine until year 2038.
        $cookie_expire_time = $cookie_now_time + openvsosh_authorization_int(K_COOKIE_EXPIRE); // set cookie expiration time
        setcookie('LastVisit', (string) $cookie_now_time, [
            'expires' => $cookie_expire_time,
            'path' => K_COOKIE_PATH,
            'domain' => K_COOKIE_DOMAIN,
            'secure' => K_COOKIE_SECURE,
            'httponly' => K_COOKIE_HTTPONLY,
            'samesite' => K_COOKIE_SAMESITE,
        ]);
        setcookie('PHPSESSID', $PHPSESSID, [
            'expires' => $cookie_expire_time,
            'path' => K_COOKIE_PATH,
            'domain' => K_COOKIE_DOMAIN,
            'secure' => K_COOKIE_SECURE,
            'httponly' => K_COOKIE_HTTPONLY,
            'samesite' => K_COOKIE_SAMESITE,
        ]);
        // track when user request logout
        if (isset($_REQUEST['logout'])) {
            $_SESSION['logout'] = true;
            $logout_url = openvsosh_authorization_string(K_LOGOUT_URL);
            if ($logout_url !== '') {
                $htmlredir = '<!DOCTYPE html>' . K_NEWLINE;
                $htmlredir .= '<html lang="' . $l['a_meta_language'] . '" dir="' . $l['a_meta_dir'] . '">' . K_NEWLINE;
                $htmlredir .= '<head>' . K_NEWLINE;
                $htmlredir .= '<meta charset="' . $l['a_meta_charset'] . '" />' . K_NEWLINE;
                $htmlredir .=
                    '<title>'
                    . htmlspecialchars($l['w_logout'], ENT_COMPAT, $l['a_meta_charset'])
                    . '</title>'
                    . K_NEWLINE;
                $htmlredir .= '<meta http-equiv="refresh" content="0;url=' . $logout_url . '" />' . K_NEWLINE;
                $htmlredir .= '</head>' . K_NEWLINE;
                $htmlredir .= '<body>' . K_NEWLINE;
                $htmlredir .= '<main id="maincontent">' . K_NEWLINE;
                $htmlredir .= '<a href="' . $logout_url . '">' . $l['w_logout'] . '...</a>' . K_NEWLINE;
                $htmlredir .= '</main>' . K_NEWLINE;
                $htmlredir .= '</body>' . K_NEWLINE;
                $htmlredir .= '</html>' . K_NEWLINE;
                header('Location: ' . $logout_url);
                echo $htmlredir;
                exit();
            }
        }
    }
} else {
    F_display_db_error();
}

// Apply database-backed defaults after the connection is available. The language cookie +
// one safe GET reload also upgrades installations whose preserved local tce_config.php predates
// the bootstrap JSON reader; timezone changes take effect for the remainder of this request.
require_once __DIR__ . '/tce_functions_openvsosh_settings.php';
$openvsosh_runtime = openvsosh_get_runtime_settings();
/** @var array{default_timezone:string,default_language:string} $openvsosh_runtime */
date_default_timezone_set($openvsosh_runtime['default_timezone']);
if (
        $server['REQUEST_METHOD'] === 'GET'
    && !isset($_GET['lang'], $_COOKIE['SessionUserLang'])
    && $openvsosh_runtime['default_language'] !== K_USER_LANG
) {
    setcookie('SessionUserLang', $openvsosh_runtime['default_language'], [
        'expires' => time() + openvsosh_authorization_int(K_COOKIE_EXPIRE),
        'path' => K_COOKIE_PATH,
        'domain' => K_COOKIE_DOMAIN,
        'secure' => K_COOKIE_SECURE,
        'httponly' => K_COOKIE_HTTPONLY,
        'samesite' => K_COOKIE_SAMESITE,
    ]);
    $request_uri = $server['REQUEST_URI'] ?? '';
    if (f_is_safe_local_redirect_uri($request_uri)) {
        header('Location: ' . $request_uri);
        exit();
    }
}
unset($openvsosh_runtime, $request_uri);

// try other login systems
// (HTTP-BASIC, CAS, SHIBBOLETH, RADIUS, LDAP)
require_once '../../shared/code/tce_altauth.php';
$altusr = openvsosh_authorization_alt_user(f_alt_login());

// --- check if login information has been submitted
if (
    isset($_POST['logaction'])
    && f_legacy_literal_equals($_POST['logaction'], 'login')
    && isset($_POST['xuser_name'])
    && isset($_POST['xuser_password'])
) {
    $submitted_password = is_string($_POST['xuser_password']) ? $_POST['xuser_password'] : '';
    $submitted_username = is_string($_POST['xuser_name']) ? $_POST['xuser_name'] : '';
    $submitted_otpcode = is_string($_POST['xuser_otpcode'] ?? null) ? $_POST['xuser_otpcode'] : '';
    $bruteforce = false;
    $wait = 1;
    $brute_force_delay_ratio = openvsosh_authorization_int(K_BRUTE_FORCE_DELAY_RATIO);
    if ($brute_force_delay_ratio > 0) {
        // check login attempt from the current client device to avoid brute force attack
        $bruteforce = true;
        // we are using another entry in the session table to keep track of the login attempts
        $sqlt = 'SELECT * FROM ' . K_TABLE_SESSIONS . " WHERE cpsession_id='" . $fingerprintkey . "' LIMIT 1";
        /** @var object|resource|bool $rt */
        $rt = F_db_query($sqlt, $db);
        if ($rt) {
            $mt = openvsosh_authorization_row(F_db_fetch_array($rt));
            if ($mt) {
                /** @var array{cpsession_expiry:string,cpsession_data:int|string} $mt */
                // check the expiration time
                if ((int) strtotime($mt['cpsession_expiry']) < time()) {
                    $bruteforce = false;
                }

                // update wait time
                $wait = (int) $mt['cpsession_data'];
                if ($wait < openvsosh_authorization_int(K_SECONDS_IN_HOUR)) {
                    $wait *= $brute_force_delay_ratio;
                }

                $sqlup =
                    'UPDATE '
                    . K_TABLE_SESSIONS
                    . ' SET
					cpsession_expiry=\''
                    . date(K_TIMESTAMP_FORMAT, time() + $wait)
                    . '\',
					cpsession_data=\''
                    . $wait
                    . '\'
					WHERE cpsession_id=\''
                    . $fingerprintkey
                    . "'";
                /** @var object|resource|bool $updated_attempt */
                $updated_attempt = F_db_query($sqlup, $db);
                if (!$updated_attempt) {
                    F_display_db_error();
                }
            } else {
                // add new record
                $wait = 1; // number of seconds to wait for the second attempt
                $sqls =
                    'INSERT INTO '
                    . K_TABLE_SESSIONS
                    . ' (
					cpsession_id,
					cpsession_expiry,
					cpsession_data
					) VALUES (
					\''
                    . $fingerprintkey
                    . '\',
					\''
                    . date(K_TIMESTAMP_FORMAT, time() + $wait)
                    . '\',
					\''
                    . $wait
                    . '\'
					)';
                /** @var object|resource|bool $inserted_attempt */
                $inserted_attempt = F_db_query($sqls, $db);
                if (!$inserted_attempt) {
                    F_display_db_error();
                }

                $bruteforce = false;
            }
        }
    }

    if ($bruteforce) {
        F_print_error('WARNING', $l['m_login_brute_force'] . ' ' . $wait);
    } else {
        // encode password
        $xuser_password = get_password_hash($submitted_password);
        // check One-Time-Password if enabled
        $otp = false;
        $otp_login = openvsosh_authorization_bool(K_OTP_LOGIN);
        if ($otp_login) {
            $mtime = microtime(true);
            $otp_key = (string) ($m['user_otpkey'] ?? '');
            if (
                $submitted_otpcode !== ''
                && (
                    hash_equals((string) f_get_otp($otp_key, $mtime), $submitted_otpcode)
                    || hash_equals((string) f_get_otp($otp_key, $mtime - 30), $submitted_otpcode)
                    || hash_equals((string) f_get_otp($otp_key, $mtime + 30), $submitted_otpcode)
                )
            ) {
                $xuser_otpcode = openvsosh_authorization_string(F_escape_sql($db, $submitted_otpcode));
                // check if this OTP token has been alredy used
                $sqlt =
                    'SELECT cpsession_id FROM '
                    . K_TABLE_SESSIONS
                    . " WHERE cpsession_id='"
                    . $xuser_otpcode
                    . "' LIMIT 1";
                /** @var object|resource|bool $rt */
                $rt = F_db_query($sqlt, $db);
                if ($rt && !openvsosh_authorization_row(F_db_fetch_array($rt))) {
                    // Store this token on the session table to mark it as invalid for 5 minute (300 seconds)
                    $sqltu =
                        'INSERT INTO '
                        . K_TABLE_SESSIONS
                        . ' (
							cpsession_id,
							cpsession_expiry,
							cpsession_data
							) VALUES (
							\''
                        . $xuser_otpcode
                        . '\',
							\''
                        . date(K_TIMESTAMP_FORMAT, time() + 300)
                        . '\',
							\'300\'
							)';
                    /** @var object|resource|bool $stored_otp */
                    $stored_otp = F_db_query($sqltu, $db);
                    if (!$stored_otp) {
                        F_display_db_error();
                    }

                    $otp = true;
                }
            }
        }

        if (!$otp_login || $otp) {
            // check if submitted login information are correct
            $sql =
                'SELECT * FROM '
                . K_TABLE_USERS
                . " WHERE user_name='"
                . openvsosh_authorization_string(F_escape_sql($db, $submitted_username))
                . "'";
            /** @var object|resource|bool $r */
            $r = F_db_query($sql, $db);
            if ($r) {
                $m = openvsosh_authorization_row(F_db_fetch_array($r));
                if (
                    $m
                    && check_password($submitted_password, (string) ($m['user_password'] ?? ''))
                ) {
                    /** @var array{user_id:int|string,user_name:string,user_password?:mixed,user_level:int|string,user_firstname:mixed,user_lastname:mixed} $m */
                    // sets some user's session data
                    $_SESSION['session_user_id'] = $m['user_id'];
                    $_SESSION['session_user_name'] = $m['user_name'];
                    $_SESSION['session_user_ip'] = get_normalized_ip($server['REMOTE_ADDR']);
                    $_SESSION['session_user_level'] = $m['user_level'];
                    $_SESSION['session_user_firstname'] = urlencode((string) $m['user_firstname']);
                    $_SESSION['session_user_lastname'] = urlencode((string) $m['user_lastname']);
                    $_SESSION['session_test_login'] = '';
                    // read client cookie
                    $_SESSION['session_last_visit'] = isset($_COOKIE['LastVisit']) ? (int) $_COOKIE['LastVisit'] : 0;

                    $logged = true;
                    if (openvsosh_authorization_bool(K_USER_GROUP_RSYNC) && $altusr !== false) {
                        // sync user groups
                        f_sync_user_groups($m['user_id'], $altusr['usrgrp_group_id']);
                    }
                } elseif (!F_check_unique(
                    K_TABLE_USERS,
                    "user_name='" . openvsosh_authorization_string(F_escape_sql($db, $submitted_username)) . "'",
                )) {
                    // the user name exist but the password is wrong
                    if ($altusr !== false) {
                        // resync the password
                        $sqlu =
                            'UPDATE '
                            . K_TABLE_USERS
                            . ' SET
								user_password=\''
                            . F_escape_sql($db, $xuser_password)
                            . '\'
								WHERE user_name=\''
                            . openvsosh_authorization_string(F_escape_sql($db, $submitted_username))
                            . "'";
                        /** @var object|resource|bool $ru */
                        $ru = F_db_query($sqlu, $db);
                        if (!$ru) {
                            F_display_db_error();
                        }

                        // get user data
                        $sqld =
                            'SELECT * FROM '
                            . K_TABLE_USERS
                            . " WHERE user_name='"
                            . openvsosh_authorization_string(F_escape_sql($db, $submitted_username))
                            . "' AND user_password='"
                            . F_escape_sql($db, $xuser_password)
                            . "'";
                        /** @var object|resource|bool $rd */
                        $rd = F_db_query($sqld, $db);
                        if ($rd) {
                            $md = openvsosh_authorization_row(F_db_fetch_array($rd));
                            if ($md) {
                                /** @var array{user_id:int|string,user_name:string,user_level:int|string,user_firstname:mixed,user_lastname:mixed} $md */
                                // sets some user's session data
                                $_SESSION['session_user_id'] = $md['user_id'];
                                $_SESSION['session_user_name'] = $md['user_name'];
                                $_SESSION['session_user_ip'] = get_normalized_ip($server['REMOTE_ADDR']);
                                $_SESSION['session_user_level'] = $md['user_level'];
                                $_SESSION['session_user_firstname'] = urlencode((string) $md['user_firstname']);
                                $_SESSION['session_user_lastname'] = urlencode((string) $md['user_lastname']);
                                $_SESSION['session_last_visit'] = 0;
                                $_SESSION['session_test_login'] = '';
                                $logged = true;
                                if (openvsosh_authorization_bool(K_USER_GROUP_RSYNC)) {
                                    // sync user groups
                                    f_sync_user_groups($md['user_id'], $altusr['usrgrp_group_id']);
                                }
                            }
                        } else {
                            F_display_db_error();
                        }
                    } else {
                        // the password is wrong
                        F_print_error('WARNING', $l['m_login_wrong']);
                    }
                } elseif ($altusr !== false) {
                    // this user do not exist on TCExam database
                    // replicate external user account on TCExam local database
                    $sql =
                        'INSERT INTO '
                        . K_TABLE_USERS
                        . ' (
							user_regdate,
							user_ip,
							user_name,
							user_email,
							user_password,
							user_regnumber,
							user_firstname,
							user_lastname,
							user_birthdate,
							user_birthplace,
							user_ssn,
							user_level
							) VALUES (
							\''
                        . F_escape_sql($db, date(K_TIMESTAMP_FORMAT))
                        . '\',
							\''
                        . openvsosh_authorization_string(F_escape_sql($db, get_normalized_ip($server['REMOTE_ADDR'])))
                        . '\',
							\''
                        . openvsosh_authorization_string(F_escape_sql($db, $submitted_username))
                        . '\',
							'
                        . f_empty_to_null($altusr['user_email'])
                        . ',
							\''
                        . F_escape_sql($db, $xuser_password)
                        . '\',
							'
                        . f_empty_to_null($altusr['user_regnumber'])
                        . ',
							'
                        . f_empty_to_null($altusr['user_firstname'])
                        . ',
							'
                        . f_empty_to_null($altusr['user_lastname'])
                        . ',
							'
                        . f_empty_to_null($altusr['user_birthdate'])
                        . ',
							'
                        . f_empty_to_null($altusr['user_birthplace'])
                        . ',
							'
                        . f_empty_to_null($altusr['user_ssn'])
                        . ',
							\''
                        . (int) $altusr['user_level']
                        . '\'
							)';
                    /** @var object|resource|bool $r */
                    $r = F_db_query($sql, $db);
                    if (!$r) {
                        F_display_db_error();
                    } else {
                        /** @var int|numeric-string $user_id */
                        $user_id = F_db_insert_id($db, K_TABLE_USERS, 'user_id');
                        // sets some user's session data
                        $_SESSION['session_user_id'] = $user_id;
                        $_SESSION['session_user_name'] = openvsosh_authorization_string(
                            F_escape_sql($db, $submitted_username),
                        );
                        $_SESSION['session_user_ip'] = get_normalized_ip($server['REMOTE_ADDR']);
                        $_SESSION['session_user_level'] = $altusr['user_level'];
                        $_SESSION['session_user_firstname'] = urlencode($altusr['user_firstname']);
                        $_SESSION['session_user_lastname'] = urlencode($altusr['user_lastname']);
                        $_SESSION['session_last_visit'] = 0;
                        $_SESSION['session_test_login'] = '';
                        $logged = true;
                        // sync user groups
                        f_sync_user_groups($user_id, $altusr['usrgrp_group_id']);
                    }
                } else {
                    $login_error = true;
                }
            } else {
                F_display_db_error();
            }
        } else {
            $login_error = true;
        }
    } // end of brute-force check
}

$pagelevel = isset($pagelevel) ? (int) $pagelevel : 0;
$requested_script = str_replace('\\', '/', $server['SCRIPT_NAME'] ?? '');
if (str_contains($requested_script, '/admin/code/')) {
    $pagelevel = openvsosh_admin_required_level(basename($requested_script), (int) $pagelevel);
}

// check client SSL certificate if required
$auth_ssl_level = openvsosh_authorization_int(K_AUTH_SSL_LEVEL);
if ($auth_ssl_level > 0 && $auth_ssl_level <= $pagelevel) {
    $sslids = preg_replace('/[^0-9,]*/', '', openvsosh_authorization_string(K_AUTH_SSLIDS));
    if (!empty($sslids)) {
        $client_hash = f_get_ssl_client_hash();
        $valid_ssl = F_count_rows(
            K_TABLE_SSLCERTS,
            "WHERE ssl_hash='" . $client_hash . "' AND ssl_id IN (" . $sslids . ')',
        );
        if (f_legacy_int_equals($valid_ssl, 0)) {
            $thispage_title = $l['t_login_form']; //set page title
            require_once '../code/tce_page_header.php';
            F_print_error('ERROR', $l['m_ssl_certificate_required']);
            require_once '../code/tce_page_footer.php';
            exit(); //break page here
        }
    }
}

// check user's level
// pagelevel=0 means access to anonymous user
// pagelevel >= 1
$session_user_level = (int) ($_SESSION['session_user_level'] ?? 0);
$session_user_id = (int) ($_SESSION['session_user_id'] ?? 0);
$session_user_ip = openvsosh_authorization_string($_SESSION['session_user_ip'] ?? '');
if ($pagelevel > 0 && $session_user_level < $pagelevel) {
    //check user level
    // To gain access to a specific resource, the user's level must be equal or greater to the one specified for the requested resource.
    F_login_form();

    //display login form
}

if (
    $logged
    && $session_user_level >= openvsosh_authorization_int(K_AUTH_ADMINISTRATOR)
) {
    require_once __DIR__ . '/tce_functions_roles.php';
    openvsosh_ensure_admin_default_group($session_user_id);
}

if ($logged) { //if user is just logged in: reloads page
    $redirect_page = $server['SCRIPT_NAME'] ?? '';
    $stored_redirect = isset($_SESSION['session_login_redirect']) && is_string($_SESSION['session_login_redirect'])
        ? $_SESSION['session_login_redirect']
        : null;
    unset($_SESSION['session_login_redirect']);

    // Only operators and administrators may return to an admin page. The stored value comes
    // from REQUEST_URI, but validate it again before using it as a Location header.
    $operator_level = defined('K_ADMIN_LINK') ? (int) K_ADMIN_LINK : 5;
    if (is_string($stored_redirect) && $session_user_level >= $operator_level) {
        $stored_parts = parse_url($stored_redirect);
        $current_script = $server['SCRIPT_NAME'] ?? '';
        $public_code_pos = strpos($current_script, '/public/code/');
        $admin_prefix = $public_code_pos === false
            ? ''
            : substr($current_script, 0, $public_code_pos) . '/admin/code/';
        if (
            is_array($stored_parts)
            && !isset($stored_parts['scheme'])
            && !isset($stored_parts['host'])
            && !isset($stored_parts['user'])
            && !isset($stored_parts['pass'])
            && isset($stored_parts['path'])
            && $admin_prefix !== ''
            && str_starts_with(openvsosh_authorization_string($stored_parts['path']), $admin_prefix)
        ) {
            $redirect_page = $stored_redirect;
        }
    }

    // html redirect
    $htmlredir = '<!DOCTYPE html>' . K_NEWLINE;
    $htmlredir .= '<html lang="' . $l['a_meta_language'] . '" dir="' . $l['a_meta_dir'] . '">' . K_NEWLINE;
    $htmlredir .= '<head>' . K_NEWLINE;
    $htmlredir .= '<meta charset="' . $l['a_meta_charset'] . '" />' . K_NEWLINE;
    $htmlredir .=
        '<title>' . htmlspecialchars($l['w_index'], ENT_COMPAT, $l['a_meta_charset']) . '</title>' . K_NEWLINE;
    $htmlredir .= '<meta http-equiv="refresh" content="0;url='
        . htmlspecialchars($redirect_page, ENT_QUOTES, $l['a_meta_charset']) . '" />' . K_NEWLINE;
    $htmlredir .= '</head>' . K_NEWLINE;
    $htmlredir .= '<body>' . K_NEWLINE;
    $htmlredir .= '<main id="maincontent">' . K_NEWLINE;
    $htmlredir .=
        '<a href="' . htmlspecialchars($redirect_page, ENT_QUOTES, $l['a_meta_charset']) . '">'
        . $l['w_index'] . '</a>' . K_NEWLINE;
    $htmlredir .= '</main>' . K_NEWLINE;
    $htmlredir .= '</body>' . K_NEWLINE;
    $htmlredir .= '</html>' . K_NEWLINE;
    switch (K_REDIRECT_LOGIN_MODE) {
        case 1:
                // relative redirect
                header('Location: ' . $redirect_page);
                break;
        case 2:
                // absolute redirect
                header('Location: ' . K_PATH_HOST . $redirect_page);
                break;
        case 3:
                // html redirect
                echo $htmlredir;
                break;
        case 4:
        default:
                // full redirect
                header('Location: ' . K_PATH_HOST . $redirect_page);
                echo $htmlredir;
                break;
    }

    exit();
}

// check for test password
/** @var array{m_wrong_test_password:string} $l */
if (
    isset($_POST['testpswaction'])
    && f_legacy_literal_equals($_POST['testpswaction'], 'login')
    && isset($_POST['xtest_password'])
    && isset($_POST['testid'])
) {
    require_once '../../shared/code/tce_functions_test.php';
    $submitted_test_id = openvsosh_authorization_string($_POST['testid']);
    $test_id = (int) $submitted_test_id;
    $tph = f_get_test_password($submitted_test_id);
    /** @var string $tph Test login forms are rendered only for tests with a non-empty password. */
    $submitted_test_password = openvsosh_authorization_submitted_password($_POST['xtest_password'] ?? null);
    if (check_password($submitted_test_password, $tph)) {
        // test password is correct, save status on a session variable
        $_SESSION['session_test_login'] = get_password_hash(
            $tph . $submitted_test_id . $session_user_id . $session_user_ip,
        );
        F_tmf_test_session_unlock($test_id);
    } else {
        F_print_error('WARNING', $l['m_wrong_test_password']);
    }
}

function openvsosh_authorization_string(mixed $value): string
{
    return is_array($value) ? 'Array' : (string) $value;
}

function openvsosh_authorization_submitted_password(mixed $value): string
{
    return is_string($value) ? $value : '';
}

function openvsosh_authorization_bool(bool $value): bool
{
    return $value;
}

function openvsosh_authorization_int(mixed $value): int
{
    return (int) $value;
}

/**
 * @return false|array{
 *     user_email:string,user_regnumber:string,user_firstname:string,user_lastname:string,
 *     user_birthdate:string,user_birthplace:string,user_ssn:string,user_level:int,
 *     usrgrp_group_id:int|string
 * }
 */
function openvsosh_authorization_alt_user(mixed $user): array|false
{
    if (!is_array($user)) {
        return false;
    }

    return [
        'user_email' => openvsosh_authorization_string($user['user_email'] ?? ''),
        'user_regnumber' => openvsosh_authorization_string($user['user_regnumber'] ?? ''),
        'user_firstname' => openvsosh_authorization_string($user['user_firstname'] ?? ''),
        'user_lastname' => openvsosh_authorization_string($user['user_lastname'] ?? ''),
        'user_birthdate' => openvsosh_authorization_string($user['user_birthdate'] ?? ''),
        'user_birthplace' => openvsosh_authorization_string($user['user_birthplace'] ?? ''),
        'user_ssn' => openvsosh_authorization_string($user['user_ssn'] ?? ''),
        'user_level' => openvsosh_authorization_int($user['user_level'] ?? 0),
        'usrgrp_group_id' => is_int($user['usrgrp_group_id'] ?? null)
            ? $user['usrgrp_group_id']
            : openvsosh_authorization_string($user['usrgrp_group_id'] ?? ''),
    ];
}

/** @return array<array-key,mixed>|null */
function openvsosh_authorization_row(mixed $row): ?array
{
    return is_array($row) ? $row : null;
}
