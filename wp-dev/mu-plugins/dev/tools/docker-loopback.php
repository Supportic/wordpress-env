<?php

declare(strict_types=1);

// Exit if accessed directly outside WordPress context.
defined('ABSPATH') || exit;

/*
Plugin Name:  Docker Loopback Fix
Description:  Forces localhost loopback requests to route to the Nginx container.
Version:      1.0.0
Author:       Supportic
Text Domain:  wpdev-docker-loopback
License:      MIT License
*/

/*
* This is only required when using php-fpm + nginx
* when opening the Site Health backend page, you get the hint: "Your site could not complete a loopback request"
* query monitor shows: "cURL error 7: Failed to connect to localhost port 80 after 0 ms: Could not connect to server"
* The problem is that inside the php-fpm docker container it cannot curl for localhost, because the webserver is inside the nginx container.
* That means we have to get the address of the nginx container and change the localhost requests.
* curl -L --resolve localhost:80:$(php -r "echo gethostbyname('nginx');") localhost
*/

add_action('http_api_curl', function ($handle, array $parsed_args, string $url): void {
    // only intercept if the host is 'localhost'
    if (parse_url($url, PHP_URL_HOST) !== 'localhost') {
        return;
    }

    // get the internal Docker IP of the nginx service (depends on your compose.yaml file)
    // Docker's internal DNS handles 'nginx' automatically
    $nginxIP = gethostbyname('nginx');

    // validate if we actually got an IP back
    if (rest_is_ip_address($nginxIP)) {
        $port = parse_url($url, PHP_URL_PORT) ?: 80;

        /**
         * CURLOPT_RESOLVE format: "DOMAIN:PORT:IP"
         * This effectively bypasses /etc/hosts and DNS for this specific handle.
         */
        curl_setopt($handle, CURLOPT_RESOLVE, ["localhost:$port:$nginxIP"]);
    }
}, 10, 3);
