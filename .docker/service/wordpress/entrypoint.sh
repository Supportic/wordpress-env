#!/bin/bash
set -euo pipefail

# run the official WordPress entrypoint to initialize WordPress files and permissions
# passing the first param dictates what should run after the script but we just want to install WP
# usually apache2 or php-fpm is passed as argument but we want supervisor to take care of process handling
# shellcheck disable=SC1091
/usr/local/bin/docker-entrypoint.sh 'docker-ensure-installed.sh'

# find /var/www/html -type d -exec chmod 775 {} +
# find /var/www/html -type f -exec chmod 664 {} +

# now start supervisord in foreground mode
# this allows Docker to properly handle signals (SIGTERM) for graceful shutdown
# replace the current process (the current shell process is destroyed and entirely replaced by the new command)
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
