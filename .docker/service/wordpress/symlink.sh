#!/usr/bin/env bash

set -Eeuo pipefail
# set -x

# Configuration
DEV_DIR="${DEV_DIR:-${HOME}/wp-dev}" # Adjust default when needed
DEV_DIR="${DEV_DIR%/}" # Remove trailing slash

WP_ROOT_DIR="${WORDPRESS_DIR:-/var/www/html}" # Adjust default when needed
WP_ROOT_DIR="${WP_ROOT_DIR%/}" # Remove trailing slash

WP_CONTENT_DIR="${WP_ROOT_DIR}/wp-content"

# 0 = true, 1 = false
function dir_exists() {
  [ -d "$1" ] && return 0 || return 1
}
function dev_dir_exists() {
  if ! dir_exists "$DEV_DIR"; then
    echo "Development directory $DEV_DIR does not exist."
    return 1;
  fi

  return 0;
}
function trim() {
    local var="$*"
    # remove leading whitespace characters
    var="${var#"${var%%[![:space:]]*}"}"
    # remove trailing whitespace characters
    var="${var%"${var##*[![:space:]]}"}"
    printf '%s' "$var"
}

# @param $1 directories to look in, separated by space
function find_dirs(){
  local DIRS=""
  for DIR in "$@"; do
    if dir_exists "$DEV_DIR/$DIR"; then
      DIRS+=$(find "$DEV_DIR/$DIR" -maxdepth 1 -mindepth 1 -type d)" "
    fi
  done

  trim "$DIRS"
}

# @param $1 directories to look in, separated by space
function find_files() {
  local FILES=""
  for DIR in "$@"; do
    if dir_exists "$DEV_DIR/$DIR"; then
      FILES+=$(find "$DEV_DIR/$DIR" -maxdepth 1 -type f ! -name ".gitkeep" ! -name ".gitignore")" "
    fi
  done

  trim "$FILES"
}

function create_user() {
  dev_dir_exists || exit 1;

  local SYNC_DIRS SYNC_FILES COMBINED_LIST ALLOWED_DIRS

  # ALLOWED_DIRS=("mu-plugins" "plugins" "themes")
  # SYNC_DIRS=$(find_dirs "${ALLOWED_DIRS[@]}")

  ALLOWED_DIRS="mu-plugins plugins themes"
  SYNC_DIRS=$(find_dirs $ALLOWED_DIRS)

  ALLOWED_DIRS="mu-plugins plugins"
  SYNC_FILES=$(find_files $ALLOWED_DIRS)

  COMBINED_LIST="$SYNC_DIRS $SYNC_FILES"

	echo "Symlinking files and directories of user from ${DEV_DIR} to ${WP_CONTENT_DIR}"

  for SRC_ITEM_PATH in $COMBINED_LIST; do
    # themes, plugins or mu-plugins
    WP_DIR_TYPE=${SRC_ITEM_PATH#"${DEV_DIR}/"}
    WP_DIR_TYPE=${WP_DIR_TYPE%%/*}

    SRC_ITEM_NAME=$(basename "$SRC_ITEM_PATH")

    if [ ! -d "$WP_CONTENT_DIR/$WP_DIR_TYPE" ]; then
      echo "  Directory $WP_CONTENT_DIR/$WP_DIR_TYPE does not exist, creating $WP_DIR_TYPE."
      mkdir -p "$WP_CONTENT_DIR/$WP_DIR_TYPE"
    fi

    if [ -L "$WP_CONTENT_DIR/$WP_DIR_TYPE/$SRC_ITEM_NAME" ]; then
      echo "  Symlink $WP_CONTENT_DIR/$WP_DIR_TYPE/$SRC_ITEM_NAME already exists, force recreating"
      ln -sfn --relative "$SRC_ITEM_PATH" "$WP_CONTENT_DIR/$WP_DIR_TYPE"
      continue
    fi

    echo "  Symlinking $SRC_ITEM_PATH to $WP_CONTENT_DIR/$WP_DIR_TYPE/$SRC_ITEM_NAME"
    ln -sfn --relative "$SRC_ITEM_PATH" "$WP_CONTENT_DIR/$WP_DIR_TYPE"
  done

  echo "Symlinking complete."
}

function remove_user() {
  dev_dir_exists || exit 1;

  local SYNC_DIRS SYNC_FILES COMBINED_LIST ALLOWED_DIRS WP_DIR_TYPE SRC_ITEM_NAME TARGET_PATH

  ALLOWED_DIRS="mu-plugins plugins themes"
  SYNC_DIRS=$(find_dirs $ALLOWED_DIRS)

  ALLOWED_DIRS="mu-plugins plugins"
  SYNC_FILES=$(find_files $ALLOWED_DIRS)

  COMBINED_LIST="$SYNC_DIRS $SYNC_FILES"

  echo "Removing symlinks of user in $WP_CONTENT_DIR"

  for SRC_ITEM_PATH in $COMBINED_LIST; do
    # themes, plugins or mu-plugins
    WP_DIR_TYPE=${SRC_ITEM_PATH#"${DEV_DIR}/"}
    WP_DIR_TYPE=${WP_DIR_TYPE%%/*}

    SRC_ITEM_NAME=$(basename "$SRC_ITEM_PATH")
    TARGET_PATH="$WP_CONTENT_DIR/$WP_DIR_TYPE/$SRC_ITEM_NAME"

    if [ ! -L "$TARGET_PATH" ]; then
      echo "  Symlink $TARGET_PATH does not exist, skipping"
      continue
    fi

    echo "  Removing symlink $TARGET_PATH"
    unlink "$TARGET_PATH"
  done

  echo "Removing symlinks complete."
}

function remove_broken() {
  local BROKEN_LINKS

  # Find all broken symlinks in WP_CONTENT_DIR
  # symlinks not coming from inside the container are also captured here
  BROKEN_LINKS=$(find "$WP_CONTENT_DIR" -xtype l)

  echo "Removing broken symlinks in $WP_CONTENT_DIR"

  # Iterate over each broken symlink
  for BROKEN_LINK in $BROKEN_LINKS; do
    # Normalize the path to remove ../ from the path
    BROKEN_LINK_REALPATH=$(realpath -m "$BROKEN_LINK")

    # Skip symlinks that were not created inside the container (starts with DEV_DIR) The user might symlink items into the wordpress directory on his own locally, ignore those symlinks
    if [[ "$BROKEN_LINK_REALPATH" != "$DEV_DIR"* ]]; then
      continue
    fi

    # Remove the broken symlink
    echo "  Removing broken symlink $BROKEN_LINK"
    unlink "$BROKEN_LINK"
  done

  echo "Removing broken symlinks complete."
}

function main() {
  local programname usage
  programname=$(basename "${0}")
  usage="Usage: ${programname} <create|remove|remove-broken|recreate>"

  # check whether user had supplied -h or --help
  if [[ "$*" == "--help" || "$*" == "-h" ]]; then
    printf "This script creates, removes or receates symlinks between the wordpress installation and development directory of themes, plugins and mu-plugins.\n\n"
    exit 0
  elif [ $# == 0 ] || [ -z "$1" ]; then
    printf "Insufficient amount of arguments!\n\n"
    echo "${usage}"
    exit 1
  fi

  case "$1" in

    "create")
      create_user;
      ;;

    "remove")
      remove_user;
      remove_broken;
      ;;

    "remove-broken")
      remove_broken;
      ;;

    "recreate")
      remove_user;
      remove_broken;
      create_user;
      ;;

    *)
      printf "Invalid argument: %s\n\n" "${1}"
      echo "${usage}"
      exit 22
      ;;
  esac

  exit 0
}

args=("${@:-}")

main "${args[@]}"
