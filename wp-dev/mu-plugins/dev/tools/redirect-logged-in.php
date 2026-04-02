<?php

declare(strict_types=1);

/*
Plugin Name:  Redirect logged in user on login page to dashboard
Version:      1.0.1
Author:       Supportic
Text Domain:  wpdev-redirect-logged-in
License:      MIT License
*/

// /wp-login.php
// /wp-login.php?action=register
function wpdev_redirect_logged_in()
{

    // check if we're on the login page and don't redirect if not logged in
    if (!is_login_page() || !is_user_logged_in()) {
        return;
    }

    $hasAction = isset($_GET['action']);
    $isRedirect = isset($_GET['redirect_to']);

    // providing an action or redirect query param should not automatically redirect the user because it means that the user should interact with the login page
    // https://developer.wordpress.org/reference/hooks/login_form_action/
    if ($hasAction || $isRedirect) {
        return;
    }

    // Redirect to admin dashboard
    wp_safe_redirect(admin_url());
    exit; // Prevents any further code execution after redirect
}

// Hook into login_init which runs only on login pages
add_action('login_init', 'wpdev_redirect_logged_in');
