<?php

declare(strict_types=1);

// Exit if accessed directly outside WordPress context.
defined('ABSPATH') || exit;

/**
 * @wordpress-plugin
 * Plugin Name:  Local Development MU-Plugin
 * Description:  Utility mu-plugin for local development.
 * Version:      1.0.0
 * Author:       Supportic
 * Text Domain:  local-wpdev
 * Requires at least: 6.2
 * Requires PHP: 8.3
 * License:      MIT License
 */

function wpdev_muplugin_load_textdomain(): void
{
    // The second argument is the path relative to the mu-plugins folder
    load_muplugin_textdomain('local-wpdev', 'local-wpdev/languages');
}
add_action('plugins_loaded', 'wpdev_muplugin_load_textdomain');

// add plugins here
require WPMU_PLUGIN_DIR . '/local-wpdev/index.php';
