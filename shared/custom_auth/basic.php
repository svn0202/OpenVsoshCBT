<?php

// This file name must match the name of your custom authentication method.

/**
 * Implement the custom authentication function.
 * Function name format "custom_auth_[auth method name]_check_login".
 *
 * @return array{
 *     user_email: string,
 *     user_firstname: string,
 *     user_lastname: string,
 *     user_birthdate: string,
 *     user_birthplace: string,
 *     user_regnumber: string,
 *     user_ssn: string,
 *     user_level: int,
 *     usrgrp_group_id: int
 * }|null
 */
function custom_auth_basic_check_login(): ?array
{
    // Do anything you want for the authentication here:
    // - It can be checking username and password from another database.
    // - Token from query string.
    // - etc.
    if (
        isset($_POST['logaction'])
        && $_POST['logaction'] == 'login'
        && isset($_POST['xuser_name'])
        && isset($_POST['xuser_password'])
    ) {
        $username = $_POST['xuser_name'];
        $password = $_POST['xuser_password'];

        if (
            is_string($username)
            && is_string($password)
            && $username == K_CUSTOM_AUTH_BASIC_USERNAME
            && password_verify($password, K_CUSTOM_AUTH_BASIC_PASSWORD_HASH)
        ) {
            // Return the user data at least with the following minimum format.
            return [
                'user_email' => '',
                'user_firstname' => '',
                'user_lastname' => '',
                'user_birthdate' => '',
                'user_birthplace' => '',
                'user_regnumber' => '',
                'user_ssn' => '',
                'user_level' => (int) K_CUSTOM_AUTH_BASIC_USER_LEVEL,
                'usrgrp_group_id' => (int) K_CUSTOM_AUTH_BASIC_USER_GROUP_ID,
            ];
        }
    }

    return null;
}
