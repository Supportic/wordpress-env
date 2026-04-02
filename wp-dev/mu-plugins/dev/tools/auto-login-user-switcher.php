<?php

declare(strict_types=1);

/*
Plugin Name:  Auto Login User Switcher
Version:      1.0.1
Author:       Supportic
Text Domain:  wpdev-auto-login-user-switcher
License:      MIT
*/

/**
 * dont use add_filter('login_form_middle', ... ) here
 * https://developer.wordpress.org/reference/functions/wp_login_form/#hooks
 * This filter is applied when using the wp_login_form() function to display a login form.
 *
 * However, it is not applied on the /wp-login.php form because it utelizes another function to render.
 * https://developer.wordpress.org/reference/hooks/login_form/
 *
 * It is not possible to retrieve the current user_id in login_init action, because the user is not logged in yet.
 */

/**
 * Test: when user loses session (wp-login.php?interim-login=1)
 * remove all cookies in devtools and call wp.heartbeat.connectNow() function in devtools console
 */

/**
 * Adjust form template and add auto-login-user-switcher on the login page.
 */
function wpdev_add_auto_login_user_switcher()
{
    // Show the switcher if user is logged out OR in a reauth scenario
    $isReauth = isset($_GET['reauth']) && $_GET['reauth'] === '1';
    $isLoggedIn = is_user_logged_in() && !$isReauth;

    if (!is_local_environment() || !is_login_page() || $isLoggedIn) {
        return;
    }

    /**
     * Get all users.
     * @var WP_USER[] $users
     */
    $users = get_users([
        'fields' => ['ID', 'display_name'],
        'orderby' => 'display_name',
        'order' => 'ASC'
    ]);

    if (empty($users)) {
        return;
    }

    // first entry is empty, to enable username/paassword login
    $selectOptions = sprintf(
        '<option value="" disabled selected>%s</option>',
        esc_html__('-- select a user --', 'wpdev')
    );
    foreach ($users as $user) {
        $selectOptions .= sprintf(
            '<option value="%d">%s</option>',
            esc_attr($user->ID),
            esc_html($user->display_name)
        );
    }

?>
    <div class="auto-login-user-switcher-wrap" style="margin-bottom: 20px;">
        <label for="auto-login-user-switcher">
            <?php esc_html_e('Auto Login User:', 'wpdev'); ?>
        </label>
        <select name="auto_login_user_switcher_user_id" id="auto-login-user-switcher" style="width: 100%;">
            <?php echo $selectOptions ?>
        </select>
        <?php wp_nonce_field('auto_login_user_switcher_login', 'auto_login_user_switcher_nonce'); ?>
        <input type="hidden" name="auto_login_user_switcher_action" value="auto_login_user">
    </div>
<?php
}
add_action('login_form', 'wpdev_add_auto_login_user_switcher');

// auto submit the form when a user is selected
function wpdev_add_auto_login_user_switcher_script()
{
    // Render the script if user is logged out OR in a reauth scenario
    $isReauth = isset($_GET['reauth']) && $_GET['reauth'] === '1';
    $isLoggedIn = is_user_logged_in() && !$isReauth;

    if (!is_local_environment() || !is_login_page() || $isLoggedIn) {
        return;
    }

?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', () => {
            const userSwitcher = document.getElementById('auto-login-user-switcher');
            if (!userSwitcher) {
                return;
            }

            userSwitcher.addEventListener('change', (evt) => {
                const $form = document.getElementById('loginform');
                if (evt.currentTarget.value && $form) {
                    $form.submit();
                }
            });
        }, false);
    </script>
<?php
}
add_action('login_enqueue_scripts', 'wpdev_add_auto_login_user_switcher_script');

/**
 * Hijack the authentication process to auto-login the selected user from the dropdown.
 *
 * @param null|WP_User|WP_Error $user     The user object or error.
 * @param string                $username The username/email submitted.
 * @param string                $password The password submitted.
 * @return null|WP_User|WP_Error The user object or error.
 */
function wpdev_bypass_authenticate_for_auto_login_user_switcher($user, $username, $password)
{
    // If we are NOT local OR NOT on the login page, bail.
    if (!is_local_environment() || !is_login_page() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        return $user;
    }

    $isAutoLoginAction = isset($_POST['auto_login_user_switcher_action']) && $_POST['auto_login_user_switcher_action'] === 'auto_login_user';

    $hasAutoLoginUserId = isset($_POST['auto_login_user_switcher_user_id']) && !empty($_POST['auto_login_user_switcher_user_id']);

    // If it's not the auto-login action or no user ID provided, bail.
    if (!$isAutoLoginAction || !$hasAutoLoginUserId) {
        return $user;
    }

    if (!isset($_POST['auto_login_user_switcher_nonce'])) {
        return new WP_Error(
            'invalid_auto_login_nonce',
            __('Nonce token is missing. Your session may have expired. Please refresh and try again.', 'wpdev')
        );
    }

    $isVerifiedNonce = wp_verify_nonce($_POST['auto_login_user_switcher_nonce'], 'auto_login_user_switcher_login');

    // skip nonce verification for reauth
    $referer = wp_get_referer();
    $isReauth = false;
    if ($referer !== false) {
        $query_string = wp_parse_url($referer, PHP_URL_QUERY);
        $params = [];
        parse_str($query_string, $params);
        $isReauth = isset($params['reauth']) && $params['reauth'] === '1';

        if ($isReauth) {
            $isVerifiedNonce = true;
        }
    }

    if ($isVerifiedNonce === false) {
        return new WP_Error(
            'invalid_auto_login_nonce',
            __('Invalid or expired nonce token. Cookies may have been cleared. Please reload the login page.', 'wpdev')
        );
    }

    $autoLoginUserId = absint($_POST['auto_login_user_switcher_user_id']);

    $user = get_user_by('id', $autoLoginUserId);

    if (!$user instanceof WP_User) {
        return new WP_Error('invalid_user', __('Login failed. Invalid user ID.', 'wpdev'));
    }

    // user has switched during reauth
    if ($isReauth && $username !== '' && $user->user_login !== $username) {
        // User is switching to a different user - mark this for redirect handling
        set_transient('auto_login_user_switcher_user_changed', true, 30);
    }

    return $user;
}
add_filter('authenticate', 'wpdev_bypass_authenticate_for_auto_login_user_switcher', 10, 3);

/**
 * Handle login redirect when user has switched during reauth
 */
function wpdev_handle_login_redirect_on_user_switch($redirect_to, $requested_redirect_to, $user)
{
    // Only handle if we detected a user switch
    if (!get_transient('auto_login_user_switcher_user_changed')) {
        return $redirect_to;
    }

    delete_transient('auto_login_user_switcher_user_changed');

    // Always redirect to admin when switching users during reauth
    return admin_url();
}
add_filter('login_redirect', 'wpdev_handle_login_redirect_on_user_switch', 10, 3);

/**
 * Legacy code, not used anymore, kept for reference.
 */
function wpdev_handle_auto_login_user_switcher($user_login, $user)
{
    // Check if the auto-login form was submitted with the correct action and nonce.
    $is_auto_login_action = isset($_POST['auto_login_user_switcher_action']) && $_POST['auto_login_user_switcher_action'] === 'auto_login_user';

    $has_user_id_in_request = !empty($_POST['auto_login_user_switcher_user_id']);

    if (! $has_user_id_in_request || !$is_auto_login_action) {
        return;
    }

    $is_verified_nonce = isset($_POST['auto_login_user_switcher_nonce']) && wp_verify_nonce($_POST['auto_login_user_switcher_nonce'], 'auto_login_user_switcher_login');

    // 1 if the nonce is valid and generated between 0-12 hours ago, 2 if the nonce is valid and generated between 12-24 hours ago. False if the nonce is invalid

    if ($is_verified_nonce === false) {
        wp_clear_auth_cookie();
        wp_destroy_current_session();
        wp_die(esc_html__('You do not have permissions to perform this action.', 'wpdev'), esc_html__('Permission Denied', 'wpdev'), ['response' => 403, 'back_link' => true]);
    }

    // Log the new user in by clearing old cookies and setting new ones.

    /**
     * Client-Side Cleanup
     * Prevents the user's device from accessing the logged-in state.
     */
    wp_clear_auth_cookie();

    /**
     * Server-Side Cleanup
     * Removes the current session from the database, invalidating the session token immediately.
     */
    wp_destroy_current_session();
    wp_set_current_user($user->ID, $user->user_login);
    wp_set_auth_cookie($user->ID, true, is_ssl());

    // default: redirect the user to the admin dashboard.
    $redirect_to = admin_url();

    if (!empty($_REQUEST['redirect_to'])) {
        $redirect_to = wp_sanitize_redirect($_REQUEST['redirect_to']);
    }

    $is_interim_login = isset($_REQUEST['interim-login']) && '1' === $_REQUEST['interim-login'];

    // if it's not a session timeout login, do a regular redirect
    if (!$is_interim_login) {
        wp_safe_redirect($redirect_to);
        exit();
    }

    // Handle interim login (overlay/popup after session timeout)
    $message = '<p class="message">' . esc_html__('You have logged in successfully.', 'wpdev') . '</p>';
    login_header('', $message);

?>
    <script type="text/javascript">
        // Use a self-invoking function to avoid global variable pollution.
        (function() {
            const parent = window.parent;

            if (!parent) {
                return;
            }

            const $ = parent.jQuery,
                windowParent = parent.window;
            const {
                adminpage,
                wp
            } = parent;

            setTimeout(function() {
                // Remove the beforeunload event handler first
                $(windowParent).off('beforeunload.wp-auth-check');

                // When on the Edit Post screen, speed up heartbeat
                // after the user logs in to quickly refresh nonces.
                if ((adminpage === 'post-php' || adminpage === 'post-new-php') && wp && wp.heartbeat) {
                    wp.heartbeat.connectNow();
                }

                /**
                 * Improve this when the previous user is the same as the new logged in user just close the modal and remove the iframe instead of a reload.
                 *
                 */
                // $('#wp-auth-check-wrap').fadeOut(200, function() {
                //     $('#wp-auth-check-wrap').addClass('hidden').css('display', '');
                //     $('#wp-auth-check-frame').remove();
                //     $('body', parent.document).removeClass('modal-open');
                // });

                if (parent.document) {
                    parent.location.reload();
                } else {
                    window.opener.location.reload();
                    window.close();
                }
            }, 300);
        })();
    </script>
<?php

    // important to prevent the normal login to continue
    exit();
}
// add_action('wp_login', 'wpdev_handle_auto_login_user_switcher', 10, 2);
