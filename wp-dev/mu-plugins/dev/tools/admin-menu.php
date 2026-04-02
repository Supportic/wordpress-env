<?php

declare(strict_types=1);

/*
Plugin Name:  Admin Menu
Version:      1.0.0
Author:       Supportic
Text Domain:  wpdev-admin-menu
License:      MIT
*/

// Exit if accessed directly outside wordpress context.
defined('ABSPATH') || exit;

/**
 * Register a custom menu page.
 */
function wpdev_register_main_menu()
{
    $parentSlug = 'wpenv';
    add_menu_page(
        __('WPEnv', 'wpdev'),
        'WPEnv',
        'manage_options',
        $parentSlug,
        // 'wpdev_main_menu',
        '__return_null',
        'dashicons-hammer', // https://developer.wordpress.org/resource/dashicons
        99
    );

    // Remove the automatically added first submenu
    add_action('admin_head', function () use ($parentSlug) {
        remove_submenu_page($parentSlug, $parentSlug);
    });

    // Hook into admin_footer to ensure jQuery is loaded and the DOM is ready
    add_action('admin_footer', function () use ($parentSlug) {
?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // WordPress generates an ID like 'toplevel_page_SLUG'
                const $parent_menu_item = $('#toplevel_page_<?php echo esc_js($parentSlug); ?> > a');

                if ($parent_menu_item.length) {
                    // 1. Change the href attribute. This makes the link non-navigable.
                    $parent_menu_item.attr('href', 'javascript:void(0)');

                    // 2. Add a click handler to ensure the menu unfolds (toggles the 'wp-has-current-submenu' class).
                    $parent_menu_item.on('click', function(e) {
                        e.preventDefault(); // Stop any default link behavior
                        // Toggle the class that controls the menu folding/unfolding.
                        // This forces the menu to open when clicked.
                        $(this).closest('li').toggleClass('wp-not-current-submenu');
                        $(this).closest('li').toggleClass('wp-has-current-submenu');
                    });
                }
            });
        </script>
<?php
    });
}
add_action('admin_menu', 'wpdev_register_main_menu');

/**
 * Display a custom menu page
 */
// function wpdev_main_menu()
// {
    // echo '<div class="wrap"><h1>WPEnv Dashboard</h1></div>';
// }

include_once __DIR__ . '/admin-menu/redirect-adminer-item.php';
