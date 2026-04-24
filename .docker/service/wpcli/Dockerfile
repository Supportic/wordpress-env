ARG PHP_VERSION='8.4'
# always alpine
FROM wordpress:cli-2-php${PHP_VERSION}
LABEL autodelete="true"

USER root

RUN apk add --no-cache shadow bash sudo nano tzdata zip unzip

##########################################################################
## set the container timezone
##########################################################################

ENV TZ=${TZ:-"Europe/Berlin"}
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime

##########################################################################
## setup user
##########################################################################

ARG UID="${UID}"
ARG GID="${GID}"
ARG UNAME="${UNAME}"
ARG GNAME="${GNAME}"
ARG PASS="${PASS}"

ENV USER=${UNAME}

RUN set -ex \
  # add group
  && addgroup --gid "${GID}" "${GNAME}" \
  # add user (-D no password => set afterwards)
  && adduser -D --gecos "" --uid "${UID}" -G "${GNAME}" --shell /bin/bash "${UNAME}" \
  # add more groups
  # &&  addgroup "${UNAME}" sudo \
  # set user PW
  && echo "${UNAME}:${PASS}" | chpasswd \
  # shell prompt (no default .bashrc without includes)
  && printf "\nPS1=\"\\\u@\\\h:\\\w# \"\nalias ll=\"ls -la\"\n" > "/home/${UNAME}/.bashrc" \
  && chown "${UNAME}:${GNAME}" "/home/${UNAME}/.bashrc"

## allow user to use sudo commands without password
# RUN set -ex \
#   && echo "${UNAME} ALL=(ALL) ALL" > /etc/sudoers.d/${UNAME} \
#   && chmod 0440 /etc/sudoers.d/${UNAME}

COPY install-plugins.txt /install-plugins.txt
COPY install-themes.txt /install-themes.txt
COPY --chown=${UID}:${GID} --chmod=0755 setup-wordpress.sh /usr/local/bin/setup-wordpress
