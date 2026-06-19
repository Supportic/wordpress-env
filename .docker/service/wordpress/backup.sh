#!/usr/bin/env bash

set -Eeuo pipefail

# Configuration
DEV_DIR="${DEV_DIR:-${HOME}/wp-dev}" # Adjust default when needed
DEV_DIR="${DEV_DIR%/}" # Remove trailing slash

WP_ROOT_DIR="${WORDPRESS_DIR:-/var/www/html}" # Adjust default when needed
WP_ROOT_DIR="${WP_ROOT_DIR%/}" # Remove trailing slash

WP_CONTENT_DIR="${WP_ROOT_DIR}/wp-content"

BACKUP_DIR="${BACKUP_DIR:-/backups}" # Adjust default when needed
BACKUP_DIR="${BACKUP_DIR%/}" # Remove trailing slash

# $1 - backup mode: full, content, or database
function backup_create() {

  local backup_mode="${1:-full}"
  if [ -z "${1}" ]; then
    printf "Backup mode not specified. Defaulting to 'full'.\n"
  fi

  if [ "${backup_mode}" != "full" ] && [ "${backup_mode}" != "content" ] && [ "${backup_mode}" != "database" ]; then
    printf "Invalid backup mode: %s\n" "${backup_mode}"
    programname=$(basename "${0}")
    printf "Usage: %s create [full|content|database]\n" "${programname}"
    exit 22
  fi

  TIMESTAMP=$(date +%Y-%m-%d-%H%M)
  local db_file_path
  db_file_path="${BACKUP_DIR}/db-${TIMESTAMP}.sql"
  local sizeH

  if [ "${backup_mode}" == "database" ] || [ "${backup_mode}" == "full" ]; then
    printf "Exporting database...\n"
    if wp db export "${db_file_path}" --no-tablespaces=true --add-drop-table; then
      local sizeH
      sizeH=$(du -h "$db_file_path" | awk '{print $1}')

      printf "Database successfully exported to: %s (%s)\n" "${db_file_path}" "$sizeH"
      [ "${backup_mode}" == "database" ] && exit 0
    else
      msg="[Error] Something went wrong while exporting the database!"
      printf "\n%s\n\n" "$msg"
      exit 1
    fi
  fi

  local backup_name="backup-$TIMESTAMP.tar.gz"
  local backup_path="${BACKUP_DIR}/${backup_name}"

  printf "Creating backup archive...\n"

  # 1. Base tar command changing into the WordPress directory to grab wp-content
  local tar_cmd=(tar -czf "$backup_path" -C "$(dirname "$WP_CONTENT_DIR")" "$(basename "$WP_CONTENT_DIR")")

  # 2. If it's a full backup, append the database file from its location
  if [ "${backup_mode}" == "full" ]; then
    tar_cmd+=(-C "$(dirname "$db_file_path")" "$(basename "$db_file_path")")
  fi

  if "${tar_cmd[@]}"; then
    sizeH=$(du -h "$backup_path" | awk '{print $1}')
    printf "Backup archive successfully created at: %s (%s)\n" "${backup_path}" "$sizeH"
  else
    msg="[Error] Something went wrong while creating the backup archive!"
    printf "\n%s\n\n" "$msg"

    # Clean up the temporary SQL file so we don't leave it hanging on failure
    [ -f "${db_file_path}" ] && rm -f "${db_file_path}"
    exit 1
  fi

  # Clean up the SQL file on success
  [ -f "${db_file_path}" ] && rm -f "${db_file_path}"
  exit 0
}

# $1 - backup mode: full, content, or database
# $2 - path to the backup file (.tar.gz or .sql)
function backup_import() {
  local backup_mode="${1:-full}"
  local file_path="${2:-}"

  if [ -z "${1}" ] || [ -z "${file_path}" ]; then
    printf "Usage: %s import [full|content|database] /path/to/backup/file\n" "$(basename "${0}")"
    exit 22
  fi

  if [ "${backup_mode}" != "full" ] && [ "${backup_mode}" != "content" ] && [ "${backup_mode}" != "database" ]; then
    printf "Invalid backup mode: %s\n" "${backup_mode}"
    exit 22
  fi

  if [ ! -f "${file_path}" ]; then
    printf "[Error] Backup file not found: %s\n" "${file_path}"
    exit 1
  fi

  # ----------------------------------------------------
  # DATABASE ONLY MODE (.sql file expected)
  # ----------------------------------------------------
  if [ "${backup_mode}" == "database" ]; then
    printf "Importing database from %s...\n" "${file_path}"
    if wp db import "${file_path}"; then
      printf "Database successfully imported!\n"
      exit 0
    else
      printf "\n[Error] Something went wrong while importing the database!\n\n"
      exit 1
    fi
  fi

  # ----------------------------------------------------
  # ARCHIVE MODES (full or content - .tar.gz expected)
  # ----------------------------------------------------
  local temp_extract_dir
  temp_extract_dir=$(mktemp -d -t wp-import-XXXXXXXXXX)

  printf "Extracting archive to temporary directory...\n"
  if ! tar -xzf "${file_path}" -C "${temp_extract_dir}"; then
    printf "\n[Error] Failed to extract archive!\n\n"
    rm -rf "${temp_extract_dir}"
    exit 1
  fi

  # 1. Restore wp-content
  if [ "${backup_mode}" == "full" ] || [ "${backup_mode}" == "content" ]; then
    local extracted_content
    extracted_content="${temp_extract_dir}/$(basename "${WP_CONTENT_DIR}")"

    if [ -d "${extracted_content}" ]; then
      printf "Restoring wp-content directory...\n"
      # Clear existing directory or merge? Replacing is safer for pristine states:
      rm -rf "${WP_CONTENT_DIR}"
      mkdir -p "$(dirname "${WP_CONTENT_DIR}")"
      mv "${extracted_content}" "${WP_CONTENT_DIR}"
    else
      printf "[Warning] wp-content not found in the archive!\n"
    fi
  fi

  # 2. Restore database from full backup
  if [ "${backup_mode}" == "full" ]; then
    # Look for the .sql file unpacked at the root level of the temp directory
    local extracted_sql
    extracted_sql=$(find "${temp_extract_dir}" -maxdepth 1 -name "db-*.sql" | head -n 1)

    if [ -f "${extracted_sql}" ]; then
      printf "Importing database from archive (%s)...\n" "$(basename "${extracted_sql}")"
      if ! wp db import "${extracted_sql}"; then
        printf "\n[Error] Database import failed during full restoration!\n\n"
        rm -rf "${temp_extract_dir}"
        exit 1
      fi
    else
      printf "[Error] Database file missing from full backup archive!\n"
      rm -rf "${temp_extract_dir}"
      exit 1
    fi
  fi

  # Cleanup temp files
  rm -rf "${temp_extract_dir}"
  printf "Import completed successfully!\n"
  exit 0
}

function main() {
  local programname usage
  programname=$(basename "${0}")
  usage="Usage: ${programname} <create|import>"

  # check whether user had supplied -h or --help
  if [[ "$*" == "--help" || "$*" == "-h" ]]; then
    printf "This script creates and imports WordPress backups.\n\n"
    exit 0
  elif [ $# == 0 ] || [ -z "$1" ]; then
    printf "Insufficient amount of arguments!\n\n"
    echo "${usage}"
    exit 1
  fi

  if ! command -v wp >/dev/null 2>&1; then
    printf "WP-CLI is not installed. Please install WP-CLI to use this script.\n"
    exit 1
  fi

  case "$1" in

    "create")
      backup_create "${2:-}";
      ;;

    "import")
      backup_import "${2:-}" "${3:-}";
      ;;
    *)
      printf "Invalid argument: %s\n\n" "${1}"
      echo "${usage}"
      exit 22
      ;;
  esac
}

args=("${@:-}")

main "${args[@]}"
