<?php

declare(strict_types=1);

// Exit if accessed directly outside WordPress context.
defined('ABSPATH') || exit;

/*
Plugin Name:  Quality of Life Features
Description:  Improve local development.
Version:      1.0.0
Author:       Supportic
Text Domain:  wpdev-qof
Requires at least: 6.0
Requires PHP: 8.3
License:      MIT License
*/

if (!function_exists('wpdev_remove_admin_bar_nodes')) {
    function wpdev_remove_admin_bar_nodes() {
        // Hide WP Logo from the admin bar
        global $wp_admin_bar;
        $wp_admin_bar->remove_node( 'wp-logo' );
    }
    add_action( 'admin_bar_menu', 'wpdev_remove_admin_bar_nodes', PHP_INT_MAX );
}

// disable-xmlrpc
add_filter( 'xmlrpc_enabled', '__return_false' );

// Disable RSS Feedlinks
remove_action( 'wp_head', 'feed_links', 2 );
remove_action( 'wp_head', 'feed_links_extra', 3 );
add_filter( 'feed_links_show_comments_feed', '__return_false' );

// Disable RSS Feed Message.
function wpdev_disable_rss_feed() {
    wp_die(
        __( 'No feed available, please visit the', 'wpdev' ) . '<a href="' . esc_url( home_url( '/' ) ) . '">' .
        __( 'homepage', 'wpdev' ) . '</a>!'
    );
}
// Disable RSS Feed.
add_action( 'do_feed', 'wpdev_disable_rss_feed', 1 );
add_action( 'do_feed_rdf', 'wpdev_disable_rss_feed', 1 );
add_action( 'do_feed_rss', 'wpdev_disable_rss_feed', 1 );
add_action( 'do_feed_rss2', 'wpdev_disable_rss_feed', 1 );
add_action( 'do_feed_atom', 'wpdev_disable_rss_feed', 1 );
add_action( 'do_feed_rss2_comments', 'wpdev_disable_rss_feed', 1 );
add_action( 'do_feed_atom_comments', 'wpdev_disable_rss_feed', 1 );

// hide the meta tag generator from head
// remove versions from styles and scripts (only for WP)
add_filter( 'the_generator', '__return_empty_string' );
if(!function_exists('wpdev_remove_version_from_assets')){
    /**
     * @param string|bool $url
     * @param string $handle - enqueued script/style handle
     */
    function wpdev_remove_version_from_assets($url, $handle){
        $wp_version = get_bloginfo('version');

        // tests for bool false since inline scripts/styles don't have href
        if (is_string($url) && strpos($url, 'ver='.$wp_version) !== false){
            $url = remove_query_arg('ver', $url);
        }

        return $url;
    }

    add_filter('style_loader_src', 'wpdev_remove_version_from_assets', PHP_INT_MAX, 2);
    add_filter('script_loader_src', 'wpdev_remove_version_from_assets', PHP_INT_MAX, 2);
}

// enable customizer
add_action( 'customize_register', '__return_true' );

// Disable WordPress image compression
add_filter('wp_editor_set_quality', function ($arg) {
    return 100;
});

// dequeue jQuery Migrate from frontend
if(!function_exists('wpdev_dequeue_jquery_migrate')){
    function wpdev_dequeue_jquery_migrate( $scripts ) {
        if (
            !is_admin()
            && !empty( $scripts->registered['jquery'])
        ) {
            $jquery_dependencies = $scripts->registered['jquery']->deps;
            $scripts->registered['jquery']->deps = array_diff(
                 $jquery_dependencies,
                 array( 'jquery-migrate' )
            );
        }
    }
    add_action( 'wp_default_scripts', 'wpdev_dequeue_jquery_migrate' );
}

if (!function_exists('action_plugins_loaded')){
    add_action('plugins_loaded', 'action_plugins_loaded' );

    /**
     * Fires once activated plugins have loaded.
     *
     */
    function action_plugins_loaded() : void {

    }
}
