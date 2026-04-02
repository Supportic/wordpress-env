#!/usr/bin/env bash

set -Eeuo pipefail

# echo "Running as: $(id -un)"

is_multisite=false
language_packs="de_DE"
url="http://localhost"

if [ -n "$WORDPRESS_PORT_HOST" ] && [ "$WORDPRESS_PORT_HOST" -ne 80 ]; then
  url="http://localhost:${WORDPRESS_PORT_HOST}"
fi

# add url before command because env HTTP_HOST is not set yet
if ! wp --url="$url" core is-installed; then

  common_install_flags=(
    "--url=$url"
    "--title=WPEnv"
    "--admin_user=admin"
    "--admin_password=admin"
    "--admin_email=admin@wpenv.com"
    "--skip-email"
    "--quiet"
  )

  if [ "$is_multisite" = true ]; then
    echo >&2 "Installing WordPress Multisite"
    wp core multisite-install "${common_install_flags[@]}" "--skip-config"
  else
    echo >&2 "Installing WordPress"
    wp core install "${common_install_flags[@]}" "--locale=en_US"
  fi

  wp option update timezone_string "Europe/Berlin"
  wp option update time_format "H:i"
  wp option update date_format "d.m.Y"
  wp rewrite structure '/%postname%/'
  wp language core install "$language_packs" > /dev/null 2>&1

  # create user member
  wp user create member member@wpenv.com --role=subscriber --user_pass=member --quiet

  # hide welcome panel on dashboard
  wp user meta update "$(wp user list --field=ID --role=administrator)" show_welcome_panel 0
  wp user meta update "$(wp user list --field=ID --role=subscriber)" show_welcome_panel 0

  # setup WordPress editor view
  wp user meta update "$(wp user list --field=ID --role=administrator)" wp_persisted_preferences "{\"core/edit-post\":{\"welcomeGuide\": false,\"fullscreenMode\": false}}" --format=json
  wp user meta update "$(wp user list --field=ID --role=subscriber)" wp_persisted_preferences "{\"core/edit-post\":{\"welcomeGuide\": false,\"fullscreenMode\": false}}" --format=json

  # delete existing pages and posts (and connected comments)
  wp post delete $(wp post list --post_type='page,post' --format=ids) --force

  wp theme delete --all
  wp plugin uninstall --deactivate --all 2>/dev/null
  # wp plugin delete --all
fi

if wp --url="$url" core is-installed; then
  echo >&2 "Preparing Themes"

  # wp theme update --all

  grep -v '^ *#' /install-themes.txt | while IFS= read -r theme; do
    if ! wp theme is-installed "$theme"; then
      wp theme install "$theme" --activate
    else
      wp theme update "$theme"
    fi
    wp language theme install "$theme" "$language_packs" >/dev/null 2>&1
  done

  echo >&2 "Updating Theme Translations"
  wp language theme update --all
fi

if wp --url="$url" core is-installed; then
  echo >&2 "Preparing Plugins"

  # wp plugin install --activate $(cat /plugins.txt)

  grep -v '^ *#' /install-plugins.txt | while IFS= read -r plugin; do
    if ! wp plugin is-installed "$plugin"; then
      wp plugin install "$plugin" --activate
    else
      wp plugin update "$plugin"
    fi
    wp language plugin install "$plugin" "$language_packs" >/dev/null 2>&1
  done

  echo >&2 "Updating Plugin Translations"
  wp language plugin update --all

  echo >&2 "Configuring Plugins"

  # wp-content/w3tc-config/master.php
  if wp plugin is-installed w3-total-cache && [ "$(wp w3tc option get objectcache.engine)" != "memcached" ]; then
    wp w3tc option set pgcache.enabled 0 --type=boolean

    wp w3tc option set dbcache.enabled 1 --type=boolean
    wp w3tc option set dbcache.engine memcached
    wp w3tc option set dbcache.memcached.servers memcached:11211 --type=array

    wp w3tc option set objectcache.enabled 1 --type=boolean
    wp w3tc option set objectcache.engine memcached
    wp w3tc option set objectcache.memcached.servers memcached:11211 --type=array
  fi
fi

# for apache2-foreground
exec "$@"
