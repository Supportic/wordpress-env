<?php

declare(strict_types=1);

/*
Plugin Name:  WP Theme Updates Disabler
Version:      1.0.0
Author:       Supportic
Text Domain:  wpdev-disable-theme-updates
License:      MIT License
*/

// Exit if accessed directly outside wordpress context.
defined('ABSPATH') || exit;

function wpdev_disable_theme_update_notices() {
    // Disable theme version checks
    remove_action( 'wp_update_themes', 'wp_update_themes' );
    remove_action( 'admin_init', '_maybe_update_themes' );
    wp_clear_scheduled_hook( 'wp_update_themes' );

    remove_action( 'load-themes.php', 'wp_update_themes' );
    remove_action( 'load-update.php', 'wp_update_themes' );
    remove_action( 'load-update-core.php', 'wp_update_themes' );
}
add_action( 'admin_init', 'wpdev_disable_theme_update_notices' );

if(!function_exists('wpdev_override_version_check_info')){
    /**
     * Override version check info stored in transients named update_core, update_plugins, update_themes.
     * Fake last checked time (using __return_null makes the dashboard slow)
     */
    function wpdev_override_version_check_info() {
        include( ABSPATH . WPINC . '/version.php' ); // get $wp_version from here
        global $wp_version;

        return ( object ) array (
            'updates' => array (),
            'response' => array (),
            'version_checked' => $wp_version,
            'last_checked' => time(),
        );
    }
}

// Disable theme updates
add_filter( 'pre_transient_update_themes', 'wpdev_override_version_check_info' );
add_filter( 'pre_site_transient_update_themes', 'wpdev_override_version_check_info' );
add_action( 'pre_set_site_transient_update_themes', 'wpdev_override_version_check_info', 20 );
add_filter( 'auto_update_theme', '__return_false' );
