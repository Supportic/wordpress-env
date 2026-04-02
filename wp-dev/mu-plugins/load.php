<?php

declare(strict_types=1);

// Exit if accessed directly outside WordPress context.
defined('ABSPATH') || exit;

/*
Plugin Name:  MU-Plugins Loader
Description:  Loads all mu-plugins in the dev directory.
Version:      1.0.0
Author:       Supportic
Text Domain:  wpdev-loader
License:      MIT License
*/

// add plugins here
require WPMU_PLUGIN_DIR.'/dev/dev.php';
