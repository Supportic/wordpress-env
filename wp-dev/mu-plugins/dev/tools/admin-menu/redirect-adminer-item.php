<?php

declare(strict_types=1);

// Exit if accessed directly outside wordpress context.
defined('ABSPATH') || exit;

/**
 * Adds a submenu page under a custom post type parent.
 */
function wpdev_register_adminer_menu()
{
    add_submenu_page(
        'wpenv',
        __('Books Shortcode Reference', 'wpdev'),
        __('Open Adminer', 'wpdev'),
        'manage_options',
        'wpdev_redirect_adminer',
        // 'wpdev_redirect_adminer_item'
        '__return_null'
    );
}
add_action('admin_menu', 'wpdev_register_adminer_menu');

// function wpdev_redirect_adminer_item()
// {
// echo '<div class="wrap"><h1>Open Adminer</h1></div>';
// wp_safe_redirect('http://localhost:8080');
// }

function wpdev_add_target_to_link()
{
?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $("ul#adminmenu li#toplevel_page_wpenv a[href$='wpdev_redirect_adminer']")
                .attr('target', '_blank')
                .attr('rel', 'noopener noreferrer nofollow')
                .attr('href', 'http://localhost:8080');
        });
    </script>
<?php
}
add_action('admin_footer', 'wpdev_add_target_to_link');
