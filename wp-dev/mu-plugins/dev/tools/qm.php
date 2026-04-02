<?php

declare(strict_types=1);

// Exit if accessed directly outside WordPress context.
defined('ABSPATH') || exit;

/*
Plugin Name:  Query Monitor Settings
Description:  Prevent creating db.php symlink
Version:      1.0.0
Author:       Supportic
Text Domain:  wpdev-qm
License:      MIT License
*/

if (defined('WP_INSTALLING') && WP_INSTALLING) {
    return;
}

if (
    defined('WP_ENVIRONMENT_TYPE')
    && (
        WP_ENVIRONMENT_TYPE === 'local'
        || WP_ENVIRONMENT_TYPE === 'development'
    ) && file_exists(WP_CONTENT_DIR.'/db.php')
) {
    $realpath = realpath(WP_CONTENT_DIR.'/db.php');

    // other plugins may also create db.php
    if (
        false !== $realpath
        && str_contains($realpath, 'wp-content/plugins/query-monitor/')
    ) {
        @unlink(WP_CONTENT_DIR.'/db.php');
    }
}
