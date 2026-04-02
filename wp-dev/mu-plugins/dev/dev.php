<?php

declare(strict_types=1);

// Exit if accessed directly outside WordPress context.
defined('ABSPATH') || exit;

/**
 * Check if the current environment is 'local'.
 *
 * @return bool True if the environment is local, false otherwise.
 */
function is_local_environment()
{
    return defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local';
}

/**
 * Check if the current page is the login page.
 * Do not use is_login() here, because the SCRIPT_NAME can be different when plugins redefine the login URL.
 * https://developer.wordpress.org/reference/functions/is_login/
 *
 * @return bool True if the current page is the login page, false otherwise.
 */
function is_login_page()
{
    // global $pagenow;

    $login_pages = [
        'wp-login.php',
        'wp-register.php',  // keep for backward compatibility, now as action param ?action=register
        'wp-signup.php'
    ];

    return in_array($GLOBALS['pagenow'] ?? '', $login_pages, true);
}

include_once __DIR__.'/tools/disable-updates.php';
include_once __DIR__.'/tools/redirect-logged-in.php';
include_once __DIR__.'/tools/auto-login-user-switcher.php';
include_once __DIR__.'/tools/remove-comments.php';
include_once __DIR__.'/tools/admin-menu.php';
include_once __DIR__.'/tools/mailpit.php';
include_once __DIR__.'/tools/qof.php';
include_once __DIR__.'/tools/qm.php';
