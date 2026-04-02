<?php

declare(strict_types=1);

// Exit if accessed directly outside WordPress context.
defined('ABSPATH') || exit;

/*
Plugin Name:  Mailpit SMTP Settings
Description:  Setup SMTP communication with mailpit docker container.
Version:      1.0.0
Author:       Supportic
Text Domain:  wpdev-mailpit
License:      MIT License
*/

// show wp_mail() errors
function on_mail_error( $wp_error ) {
    echo "<pre>";
    print_r($wp_error);
    echo "</pre>";
}
add_action( 'wp_mail_failed', 'on_mail_error', 10, 1 );

// replaces the default initial localhost domain in the mail_from address
// wordpress@localhost vs wordpress@localhost.docker
function wporg_replace_user_mail_from( $from_email ) {
    $parts = explode( '@', $from_email );
    return $parts[0] . '@localhost.docker';
}

add_filter( 'wp_mail_from', 'wporg_replace_user_mail_from' );

// initiate mailer to be able to use wp_mail() function
function mailer_config($phpmailer){
    $phpmailer->IsSMTP();
    $phpmailer->Host = "mailpit"; // your SMTP server
    $phpmailer->Port = 1025;
    //   $phpmailer->SMTPDebug = 2; // write 0 if you don't want to see client/server communication in page
    $phpmailer->CharSet  = "utf-8";

    // define address here or use the default 'from_mail' address
    $phpmailer->From = "mailpit@wpenv.com";
    $phpmailer->FromName = "Admin";

    // $phpmailer->SMTPAuth = true;
    // $phpmailer->Username = 'yourusername';
    // $phpmailer->Password = 'yourpassword';
}
add_action( 'phpmailer_init', 'mailer_config', 10, 1);
