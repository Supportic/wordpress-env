ARG PHP_VERSION='8.4'
ARG WP_VERSION='6.9.4'
FROM wordpress:${WP_VERSION}-php${PHP_VERSION}-fpm AS base

USER root

##########################################################################
## setup linux packages
##########################################################################

RUN set -ex &&\
  export DEBIAN_FRONTEND="noninteractive" &&\
  apt-get update &&\
  apt-get -y --no-install-recommends install \
  nginx supervisor cron \
  sudo nano \
  && apt-get clean &&\
  rm -rf /var/lib/apt/lists/*

ENV EDITOR="/usr/bin/nano"

##########################################################################
## set the container timezone
##########################################################################

ENV TZ=${TZ:-"Europe/Berlin"}
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime &&\
  dpkg-reconfigure -f noninteractive tzdata

##########################################################################
## configure supervisor
## we dont have to create .pid files on our own, it's done via supervisor
##########################################################################

RUN mkdir -p /var/log/supervisor
COPY supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY supervisor/conf.d /etc/supervisor/conf.d

FROM base AS wordpress

##########################################################################
## install PHP extensions
##########################################################################

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# https://make.wordpress.org/hosting/handbook/server-environment/#php-extensions
RUN install-php-extensions igbinary timezonedb

FROM wordpress AS develop
LABEL autodelete="true"

##########################################################################
## setup user
## we don't need a custom user for the server container, just change the UID and GID to match the host user
## changing to a custom user would require more steps regarding php-fpm ownership and WordPress init script
##########################################################################

ARG UID="${UID}"
ARG GID="${GID}"
ARG UNAME="www-data"
ARG GNAME="www-data"
ARG PASS="${PASS}"
ENV HOME="/home/${UNAME}"

RUN usermod -u "${UID}" --shell /bin/bash "${UNAME}" &&\
  groupmod -g "${UID}" "${GNAME}" &&\
  # set user PW
  echo "${UNAME}:${PASS}" | chpasswd &&\
  mkdir -p "/home/${UNAME}" &&\
  chown -R "${UNAME}:${GNAME}" "/home/${UNAME}" &&\
  # shell prompt (no default .bashrc without includes)
  printf "\nalias ll=\"ls -la\"\nPS1=\"\\\u@\\\h:\\\w# \"\n" >> "/home/${UNAME}/.bashrc" &&\
  chown "${UNAME}:${GNAME}" "/home/${UNAME}/.bashrc"

# installing sudo does not take care of existing users
RUN usermod -aG sudo "${UNAME}" &&\
  # && usermod -aG wheel "${UNAME}" \
  printf "${UNAME} ALL=(ALL:ALL) ALL" > "/etc/sudoers.d/${UNAME}" &&\
  chmod 0440 "/etc/sudoers.d/${UNAME}" &&\
  printf "Defaults lecture = never" > /etc/sudoers.d/privacy

COPY --chmod=0755 symlink.sh /usr/local/bin/symlink

##########################################################################
## configure nginx + php-fpm
##########################################################################

RUN mkdir -p /var/lib/nginx /var/log/nginx /var/cache/nginx

COPY nginx/nginx.conf /etc/nginx/nginx.conf
COPY nginx/conf.d /etc/nginx/conf.d

RUN mkdir -p /var/log/php-fpm

##########################################################################
## COPY custom.ini $PHP_INI_DIR/conf.d/
## /usr/local/etc/php/conf.d/php.ini
##########################################################################

# Use the default develop configuration
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY php/php.ini "$PHP_INI_DIR/conf.d/"

# first install xdebug which sets zend_extension=xdebug.so automatically for you
# enable opcache when needed, only the existance of the file will enable opcache
RUN install-php-extensions opcache
COPY php/opcache.ini "$PHP_INI_DIR/conf.d/"

# Use a custom entrypoint that:
# 1. Runs the official WordPress setup (file init, permissions)
# 2. Starts supervisor in foreground mode for proper signal handling
COPY --chmod=0755 entrypoint.sh /usr/local/bin/custom-entrypoint

COPY --chmod=0755 healthcheck-php-fpm.sh /usr/local/bin/healthcheck-php-fpm

# dont call frontend here since it floods into access log
HEALTHCHECK --interval=60s --timeout=10s --start-period=10s --retries=3 CMD [ "healthcheck-php-fpm" ]

# Use custom entrypoint that runs WordPress setup, then supervisor in foreground
ENTRYPOINT ["custom-entrypoint"]

FROM wordpress AS production
LABEL autodelete="true"

# Use the default production configuration
# RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
