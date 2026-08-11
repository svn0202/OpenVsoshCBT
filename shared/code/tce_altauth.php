<?php

//============================================================+
// File name   : tce_altauth.php
// Begin       : 2008-03-28
// Last Update : 2023-11-30
//
// Description : Check user authorization against alternative
//               systems (SSL, HTTP-BASIC, CAS, SHIBBOLETH, RADIUS, LDAP)
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Check user authorization against alternative systems (HTTP-BASIC, CAS, SHIBBOLETH, RADIUS, LDAP)
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2008-03-28
 */

/**
 * Try various external Login Systems.
 * (SSL, HTTP-BASIC, CAS, SHIBBOLETH, RADIUS, LDAP, CUSTOM)
 * @return array<array-key,mixed>|false User data for successful login, false otherwise.
 * @since 2012-06-05
 */
function f_alt_login(): array|false
{
    global $l, $db;
    require_once '../config/tce_config.php';

    /** @var array<string,string> $server */
    $server = $_SERVER;
    /** @var array{logout?:mixed,session_user_name:string} $session */
    $session = $_SESSION;

    // TCExam tries to retrive the user login information from the following systems:

    // 1) SSL ----------------------------------------------------------
    require_once '../../shared/config/tce_ssl.php';
    /** @var bool $ssl_enabled */
    $ssl_enabled = K_SSL_ENABLED;
    if (
        $ssl_enabled
        && (!isset($session['logout']) || !$session['logout'])
        && (
            isset($server['SSL_CLIENT_M_SERIAL'])
            && isset($server['SSL_CLIENT_I_DN'])
            && isset($server['SSL_CLIENT_V_END'])
            && isset($server['SSL_CLIENT_VERIFY'])
            && $server['SSL_CLIENT_VERIFY'] === 'SUCCESS'
            && isset($server['SSL_CLIENT_V_REMAIN'])
            && $server['SSL_CLIENT_V_REMAIN'] <= 0
        )
    ) {
        $_POST['xuser_name'] = md5($server['SSL_CLIENT_M_SERIAL'] . $server['SSL_CLIENT_I_DN']);
        $_POST['xuser_password'] = get_password_hash(
            $server['SSL_CLIENT_M_SERIAL']
            . $server['SSL_CLIENT_I_DN']
            . K_RANDOM_SECURITY
            . $server['SSL_CLIENT_V_END'],
        );
        $_POST['logaction'] = 'login';
        $usr = [];
        $usr['user_email'] = $server['SSL_CLIENT_S_DN_Email'] ?? '';

        $usr['user_firstname'] = $server['SSL_CLIENT_S_DN_CN'] ?? '';

        $usr['user_lastname'] = '';
        $usr['user_birthdate'] = '';
        $usr['user_birthplace'] = '';
        $usr['user_regnumber'] = '';
        $usr['user_ssn'] = '';
        $usr['user_level'] = K_SSL_USER_LEVEL;
        $usr['usrgrp_group_id'] = K_SSL_USER_GROUP_ID;
        return $usr;
    }

    // -----------------------------------------------------------------

    // 2) HTTP BASIC ---------------------------------------------------
    require_once '../../shared/config/tce_httpbasic.php';
    /** @var bool $httpbasic_enabled */
    $httpbasic_enabled = K_HTTPBASIC_ENABLED;
    if (
        $httpbasic_enabled
        && (!isset($session['logout']) || !$session['logout'])
        && (
            isset($server['AUTH_TYPE'])
            && f_legacy_literal_equals($server['AUTH_TYPE'], 'Basic')
            && isset($server['PHP_AUTH_USER'])
            && isset($server['PHP_AUTH_PW'])
            && !f_legacy_equals($session['session_user_name'], $server['PHP_AUTH_USER'])
        )
    ) {
        $_POST['xuser_name'] = $server['PHP_AUTH_USER'];
        $_POST['xuser_password'] = $server['PHP_AUTH_PW'];
        $_POST['logaction'] = 'login';
        $usr = [];
        $usr['user_email'] = '';
        $usr['user_firstname'] = '';
        $usr['user_lastname'] = '';
        $usr['user_birthdate'] = '';
        $usr['user_birthplace'] = '';
        $usr['user_regnumber'] = '';
        $usr['user_ssn'] = '';
        $usr['user_level'] = K_HTTPBASIC_USER_LEVEL;
        $usr['usrgrp_group_id'] = K_HTTPBASIC_USER_GROUP_ID;
        return $usr;
    }

    // -----------------------------------------------------------------

    // 3) CAS - Central Authentication Service -------------------------
    require_once '../../shared/config/tce_cas.php';
    /** @var bool $cas_enabled */
    $cas_enabled = K_CAS_ENABLED;
    if ($cas_enabled) {
        require_once '../../vendor/autoload.php';
        phpCAS::client(K_CAS_VERSION, K_CAS_HOST, K_CAS_PORT, K_CAS_PATH, K_CAS_SERVICE_BASE_URL, false);
        phpCAS::setNoCasServerValidation();
        phpCAS::forceAuthentication();
        if (!f_legacy_equals($session['session_user_name'], phpCAS::getUser())) {
            $_POST['xuser_name'] = phpCAS::getUser();
            $_POST['xuser_password'] = get_password_hash($_POST['xuser_name'] . K_RANDOM_SECURITY);
            $_POST['logaction'] = 'login';
            $usr = [];
            $usr['user_email'] = '';
            $usr['user_firstname'] = '';
            $usr['user_lastname'] = '';
            $usr['user_birthdate'] = '';
            $usr['user_birthplace'] = '';
            $usr['user_regnumber'] = '';
            $usr['user_ssn'] = '';
            $usr['user_level'] = K_CAS_USER_LEVEL;
            $usr['usrgrp_group_id'] = K_CAS_USER_GROUP_ID;
            return $usr;
        }
    }

    // -----------------------------------------------------------------

    // 4) Shibboleth ---------------------------------------------------
    require_once '../../shared/config/tce_shibboleth.php';
    /** @var bool $shibboleth_enabled */
    $shibboleth_enabled = K_SHIBBOLETH_ENABLED;
    if (
        $shibboleth_enabled
        && (!isset($session['logout']) || !$session['logout'])
        && (
            isset($server['AUTH_TYPE'])
            && f_legacy_literal_equals($server['AUTH_TYPE'], 'shibboleth')
            && (
                isset($server['Shib_Session_ID']) && !empty($server['Shib_Session_ID'])
                || isset($server['HTTP_SHIB_IDENTITY_PROVIDER']) && !empty($server['HTTP_SHIB_IDENTITY_PROVIDER'])
            )
            && isset($server['eppn'])
            && !f_legacy_equals($session['session_user_name'], $server['eppn'])
        )
    ) {
        $_POST['xuser_name'] = $server['eppn'];
        $_POST['xuser_password'] = get_password_hash($_POST['xuser_name'] . K_RANDOM_SECURITY);
        $_POST['logaction'] = 'login';
        $usr = [];
        $usr['user_email'] = $server['eppn'];
        $usr['user_firstname'] = $server['givenName'] ?? '';

        $usr['user_lastname'] = $server['sn'] ?? '';

        $usr['user_birthdate'] = '';
        $usr['user_birthplace'] = '';
        $usr['user_regnumber'] = $server['employeeNumber'] ?? '';

        $usr['user_ssn'] = '';
        $usr['user_level'] = K_SHIBBOLETH_USER_LEVEL;
        $usr['usrgrp_group_id'] = K_SHIBBOLETH_USER_GROUP_ID;
        return $usr;
    }

    // -----------------------------------------------------------------

    if (
        isset($_POST['logaction'])
        && f_legacy_literal_equals($_POST['logaction'], 'login')
        && isset($_POST['xuser_name'])
        && isset($_POST['xuser_password'])
    ) {
        /** @var array{xuser_name:string,xuser_password:string,logaction:mixed} $login_post */
        $login_post = $_POST;
        // 5) RADIUS ---------------------------------------------------
        require_once '../../shared/config/tce_radius.php';
        /** @var bool $radius_enabled */
        $radius_enabled = K_RADIUS_ENABLED;
        if ($radius_enabled) {
            require_once '../../vendor/autoload.php';
            $radius = new Dapphp\Radius\Radius(
                K_RADIUS_SERVER_IP,
                K_RADIUS_SHARED_SECRET,
                K_RADIUS_SUFFIX,
                K_RADIUS_UDP_TIMEOUT,
                K_RADIUS_AUTHENTICATION_PORT,
                K_RADIUS_ACCOUNTING_PORT,
            );
            /** @var bool $radius_utf8 */
            $radius_utf8 = K_RADIUS_UTF8;
            if ($radius_utf8) {
                /** @var string $radusername */
                $radusername = mb_convert_encoding($login_post['xuser_name'], 'UTF-8', 'auto');
                /** @var string $radpassword */
                $radpassword = mb_convert_encoding($login_post['xuser_password'], 'UTF-8', 'auto');
            } else {
                $radusername = $login_post['xuser_name'];
                $radpassword = $login_post['xuser_password'];
            }

            if ($radius->AccessRequest($radusername, $radpassword)) {
                return [
                    'user_email' => '',
                    'user_firstname' => '',
                    'user_lastname' => '',
                    'user_birthdate' => '',
                    'user_birthplace' => '',
                    'user_regnumber' => '',
                    'user_ssn' => '',
                    'user_level' => K_RADIUS_USER_LEVEL,
                    'usrgrp_group_id' => K_RADIUS_USER_GROUP_ID,
                ];
            }
        }

        // -------------------------------------------------------------

        // 6) LDAP -----------------------------------------------------
        require_once '../../shared/config/tce_ldap.php';
        /** @var bool $ldap_enabled */
        $ldap_enabled = K_LDAP_ENABLED;
        /** @var array<string,string> $ldap_attr */
        if ($ldap_enabled) {
            // make ldap connection
            /** @var \LDAP\Connection $ldapconn */
            $ldapconn = ldap_connect(K_LDAP_HOST, K_LDAP_PORT);
            ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, K_LDAP_PROTOCOL_VERSION);
            ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0); // recommended for W2K3
            // bind anonymously and get dn for username.
            /** @var bool $ldap_utf8 */
            $ldap_utf8 = K_LDAP_UTF8;
            if ($ldap_utf8) {
                /** @var string $ldapusername */
                $ldapusername = mb_convert_encoding($login_post['xuser_name'], 'UTF-8', 'auto');
                /** @var string $ldappassword */
                $ldappassword = mb_convert_encoding($login_post['xuser_password'], 'UTF-8', 'auto');
            } else {
                $ldapusername = $login_post['xuser_name'];
                $ldappassword = $login_post['xuser_password'];
            }

            if ($lbind = ldap_bind($ldapconn, K_LDAP_ROOT_DN, K_LDAP_ROOT_PASS)) {
                // Search user on LDAP tree
                $ldap_filter = str_replace('#USERNAME#', $ldapusername, K_LDAP_FILTER);
                $sorted_ldap_attr = $ldap_attr;
                sort($sorted_ldap_attr);
                //var_export($rdn); // uncomment this to see the structure of the entries
                // @mago-expect lint:no-error-control-operator -- an unavailable LDAP directory falls through to the other login methods
                $search = f_tmf_alt_ldap_search(@ldap_search($ldapconn, K_LDAP_BASE_DN, $ldap_filter, $sorted_ldap_attr));
                $rdn = false;
                if ($search instanceof \LDAP\Result) {
                    // @mago-expect lint:no-error-control-operator -- a failed LDAP result lookup is treated as an authentication miss
                    $rdn = f_tmf_alt_ldap_entries(@ldap_get_entries($ldapconn, $search));
                }
                $ldap_entry = is_array($rdn) && isset($rdn[0]) && is_array($rdn[0]) ? $rdn[0] : [];
                /** @var string $ldap_dn */
                $ldap_dn = $ldap_entry['dn'] ?? '';
                if (
                    $ldap_dn !== ''
                    && f_tmf_alt_ldap_bind_silently($ldapconn, $ldap_dn, $ldappassword)
                ) {
                    f_tmf_alt_ldap_unbind_silently($ldapconn);
                    $usr = [];
                    foreach ($ldap_attr as $k => $v) {
                        if (!empty($v) && isset($ldap_entry[$v])) {
                            /** @var string|array{0:mixed,...<array-key,mixed>} $ldap_value */
                            $ldap_value = $ldap_entry[$v];
                            $usr[$k] = is_array($ldap_value) ? ($ldap_value[0] ?? '') : $ldap_value;
                        } else {
                            $usr[$k] = '';
                        }
                    }

                    $usr['user_level'] = K_LDAP_USER_LEVEL;
                    $usr['usrgrp_group_id'] = K_LDAP_USER_GROUP_ID;
                    return $usr;
                }
            }

            f_tmf_alt_ldap_unbind_silently($ldapconn);
        }

        // -------------------------------------------------------------
    }

    /**
     * Custom authentication methods hook.
     */
    /** @var string|false $custom_auth_methods */
    $custom_auth_methods = K_CUSTOM_AUTH_METHODS;
    if ($custom_auth_methods) {
        /** @var list<string>|false $methods */
        $methods = unserialize($custom_auth_methods);
        $methods = is_array($methods) ? $methods : [];
        foreach ($methods as $method) {
            $config_file = '../../shared/config/custom_auth/' . $method . '.php';
            if (file_exists($config_file)) {
                require_once $config_file;
            }

            $main_file = '../../shared/custom_auth/' . $method . '.php';
            if (file_exists($main_file)) {
                require_once $main_file;
            }

            $auth_function = 'custom_auth_' . $method . '_check_login';
            if (function_exists($auth_function)) {
                return f_tmf_alt_auth_result($auth_function());
            }
        }
    }

    return false;
}

/** @return array<array-key,mixed>|false */
function f_tmf_alt_auth_result(mixed $result): array|false
{
    return is_array($result) ? $result : false;
}

function f_tmf_alt_ldap_search(mixed $search): ?\LDAP\Result
{
    return $search instanceof \LDAP\Result ? $search : null;
}

/** @return array<array-key,mixed>|false */
function f_tmf_alt_ldap_entries(mixed $entries): array|false
{
    return is_array($entries) ? $entries : false;
}

function f_tmf_alt_ldap_unbind_silently(\LDAP\Connection $connection): bool
{
    set_error_handler(static fn(): bool => true);
    try {
        return ldap_unbind($connection);
    } finally {
        restore_error_handler();
    }
}

function f_tmf_alt_ldap_bind_silently(
    \LDAP\Connection $connection,
    string $dn,
    #[\SensitiveParameter] string $password,
): bool
{
    set_error_handler(static fn(): bool => true);
    try {
        return ldap_bind($connection, $dn, $password);
    } finally {
        restore_error_handler();
    }
}
