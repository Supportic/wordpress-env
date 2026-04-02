<?php

declare(strict_types=1);

/*
Plugin Name:  WP Core Updates Disabler
Version:      1.0.0
Author:       Supportic
Text Domain:  wpdev-disable-core-updates
License:      MIT License
*/

// Exit if accessed directly outside wordpress context.
defined('ABSPATH') || exit;

function wpdev_disable_core_update_notices() {

    // Remove nags (admin messages)
    remove_action( 'admin_notices', 'update_nag', 3 );
    remove_action( 'admin_notices', 'maintenance_nag' );

    // Disable WP version check
    remove_action( 'wp_version_check', 'wp_version_check' );
    remove_action( 'admin_init', 'wp_version_check' );
    wp_clear_scheduled_hook( 'wp_version_check' );

    add_filter( 'pre_option_update_core', '__return_null' );

    // disable core, theme and plugin updates
	remove_action ( 'load-update-core.php', 'wp_update_core' );

    // Disable auto updates
    wp_clear_scheduled_hook( 'wp_maybe_auto_update' );

    remove_action( 'wp_maybe_auto_update', 'wp_maybe_auto_update' );
    remove_action( 'admin_init', 'wp_maybe_auto_update' );
    remove_action( 'admin_init', 'wp_auto_update_core' );
}
add_action( 'admin_init', 'wpdev_disable_core_update_notices' );

/**
 * Disable Background Updates and Auto-Updates tests in Site Health tests
 */
function wpdev_disable_update_checks_in_site_health( $tests ) {

    unset( $tests['async']['background_updates'] );
    unset( $tests['direct']['plugin_theme_auto_updates'] );

    return $tests;

}
// Disable Site Health checks
add_filter( 'site_status_tests', 'wpdev_disable_update_checks_in_site_health' );

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

// Disable core update
add_filter( 'pre_transient_update_core', 'wpdev_override_version_check_info' );
add_filter( 'pre_site_transient_update_core', 'wpdev_override_version_check_info' );

// Disable auto updates
add_filter( 'automatic_updater_disabled', '__return_true' );
if ( !defined( 'AUTOMATIC_UPDATER_DISABLED' ) ) {
    define( 'AUTOMATIC_UPDATER_DISABLED', true );
}
if ( !defined( 'WP_AUTO_UPDATE_CORE' ) ) {
    define( 'WP_AUTO_UPDATE_CORE', false );
}
add_filter( 'auto_update_core', '__return_false' );
add_filter( 'wp_auto_update_core', '__return_false' );
add_filter( 'allow_minor_auto_core_updates', '__return_false' );
add_filter( 'allow_major_auto_core_updates', '__return_false' );
add_filter( 'allow_dev_auto_core_updates', '__return_false' );
add_filter( 'auto_update_translation', '__return_false' );
remove_action( 'init', 'wp_schedule_update_checks' );
// Disable update emails
add_filter( 'auto_core_update_send_email', '__return_false' );
add_filter( 'send_core_update_notification_email', '__return_false' );
add_filter( 'automatic_updates_send_debug_email', '__return_false' );

/**
 * Remove the 'Updates' menu item from the admin interface
 */
function wpdev_remove_updates_menu() {
    global $submenu;
    remove_submenu_page( 'index.php', 'update-core.php' );
}
// Remove Dashboard >> Updates menu item
add_action( 'admin_menu', 'wpdev_remove_updates_menu' );
