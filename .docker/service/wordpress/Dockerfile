ARG PHP_VERSION='8.4'
ARG WP_VERSION='6.9.4'
FROM wordpress:${WP_VERSION}-php${PHP_VERSION}-fpm AS base

USER root

# Set the container timezone
ENV TZ=Europe/Berlin
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime \
  && echo $TZ > /etc/timezone  \
  && dpkg-reconfigure -f noninteractive tzdata

# setup User
ARG UID="${UID}"
ARG GID="${GID}"
ARG UNAME="${UNAME}"
ARG GNAME="${GNAME}"
ARG PASS="${PASS}"

RUN set -ex \
  # add group
  && groupadd --gid "${GID}" "${GNAME}" \
  # add user
  && useradd --uid "${UID}" --gid "${GID}" --groups www-data,sudo \
  --create-home --no-log-init --shell /bin/bash "${UNAME}" \
  # add more groups
  # && usermod -aG sudo "${UNAME}" \
  # set user PW
  && echo "${UNAME}:${PASS}" | chpasswd \
  # shell prompt
  && printf "\nPS1=\"\\\u@\\\h:\\\w# \"\nalias ll=\"ls -la\"" >> "/home/${UNAME}/.bashrc" \
  && chown "${UNAME}:${GNAME}" "/home/${UNAME}/.bashrc"

## allow user to use sudo commands without password
# RUN set -ex \
#   && echo "${UNAME} ALL=(ALL) ALL" > /etc/sudoers.d/${UNAME} \
#   && chmod 0440 /etc/sudoers.d/${UNAME}

FROM base AS wordpress

COPY --chown=${UID}:${GID} --chmod=0755 symlink.sh /usr/local/bin/symlink

# install PHP extensions
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# https://make.wordpress.org/hosting/handbook/server-environment/#php-extensions
RUN install-php-extensions opcache igbinary timezonedb

FROM wordpress AS develop
LABEL autodelete="true"

# first install xdebug which sets zend_extension=xdebug.so automatically for you
RUN install-php-extensions xdebug memcached

##########################################################################
## COPY custom.ini $PHP_INI_DIR/conf.d/
## /usr/local/etc/php/conf.d/php.ini
##########################################################################

# Use the default production configuration
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY config/php.ini "$PHP_INI_DIR/conf.d/php.ini"
COPY config/uploads.ini "$PHP_INI_DIR/conf.d/uploads.ini"

## enable opcache when needed, only the existance of the file will enable opcache, even if the config says enable=0
# COPY config/opcache.ini "$PHP_INI_DIR/conf.d/opcache.ini"

COPY config/xdebug.ini "$PHP_INI_DIR/conf.d/xdebug.ini"

FROM wordpress AS production

# Use the default production configuration
# RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
