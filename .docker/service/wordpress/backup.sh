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

VALID_MODES=("full" "content" "database")

function validate_mode() {
  local mode_to_check="${1:-}"

  for valid in "${VALID_MODES[@]}"; do
    if [ "${mode_to_check}" == "${valid}" ]; then
      return 0
    fi
  done

  # Join modes with comma and space
  local allowed_modes
  allowed_modes=$(IFS=', '; echo "${VALID_MODES[*]}")

  printf "Invalid mode: %s\n" "${mode_to_check}"
  printf "Allowed modes: %s\n" "${allowed_modes}"
  return 1
}

# $1 - backup mode: full, content, or database
function backup_create() {

  local backup_mode="${1:-full}"

  if [ -z "${1}" ]; then
    printf "Backup mode not specified. Defaulting to 'full'.\n"
  fi

  if ! validate_mode "${backup_mode}"; then
    programname=$(basename "${0}")
    printf "Usage: %s create [%s]\n" "${programname}" "$(IFS='|'; echo "${VALID_MODES[*]}")"
    exit 22
  fi

  printf "\nRunning: %s create %s\n\n" "${programname}" "${backup_mode}"

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

  # Create a isolated temporary directory
  local temp_dir
  temp_dir=$(mktemp -d -t wp-tmp-XXXXXXXXXX)

  # Ensure temp directory is removed on exit/interrupt
  trap 'rm -rf "${temp_dir}"' EXIT INT TERM

  # Copy wp-content to temp (preserving timestamps and attributes)
  cp -a "${WP_CONTENT_DIR}" "${temp_dir}/"

  # 1. Base tar command changing into the WordPress directory to grab wp-content
  local tar_cmd=(tar -czf "$backup_path" -C "$temp_dir" "$(basename "$WP_CONTENT_DIR")")

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
  local import_mode="${1:-full}"
  local file_path="${2:-}"
  local programname
  programname=$(basename "${0}")

  if [ -z "${1}" ]; then
    printf "Import mode not specified. Defaulting to 'full'.\n"
  fi

  if ! validate_mode "${import_mode}"; then
    printf "Usage: %s import [%s] [file_path]\n\n" "${programname}" "$(IFS='|'; echo "${VALID_MODES[*]}")"
    exit 22
  fi

  printf "\nRunning: %s import %s /backups/<filename>\n\n" "${programname}" "${import_mode}"

  # ----------------------------------------------------
  # DATABASE ONLY MODE (.sql file expected)
  # ----------------------------------------------------
  if [ "${import_mode}" == "database" ]; then
    if [ -z "${file_path}" ]; then
      read -e -r -p "Enter path to SQL file: " file_path || true
    fi

    ext="${file_path##*.}"
    if [ -z "${file_path}" ]; then
      printf "[Error] Valid SQL file path is required.\n"
      exit 1
    elif [ ! -f "${file_path}" ]; then
      printf "[Error] File not found: '%s'\n" "${file_path}"
      exit 1
    elif [ "${ext,,}" != "sql" ]; then
      printf "[Error] File: '%s' is not a valid SQL file.\n" "${file_path}"
      exit 1
    fi

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
  if [ -z "${file_path}" ]; then
    read -e -r -p "Enter path to backup file: " file_path || true
  fi

  ext="${file_path##*.}"
  if [ -z "${file_path}" ]; then
    printf "[Error] Valid backup file path is required.\n"
    exit 1
  elif [ ! -f "${file_path}" ]; then
    printf "[Error] File not found: '%s'\n" "${file_path}"
    exit 1
  elif [[ "${file_path,,}" != *.tar.gz ]]; then
    printf "[Error] File: '%s' is not a valid backup file.\n" "${file_path}"
    exit 1
  fi

  local temp_extract_dir
  temp_extract_dir=$(mktemp -d -t wp-import-XXXXXXXXXX)

  printf "Extracting archive to temporary directory...\n"
  if ! tar -xzf "${file_path}" -C "${temp_extract_dir}"; then
    printf "\n[Error] Failed to extract archive!\n\n"
    rm -rf "${temp_extract_dir}"
    exit 1
  fi

  # 1. Restore wp-content
  if [ "${import_mode}" == "full" ] || [ "${import_mode}" == "content" ]; then
    local extracted_content import_content_success import_database_success
    extracted_content="${temp_extract_dir}/$(basename "${WP_CONTENT_DIR}")"
    import_content_success=false
    import_database_success=false

    if [ -d "${extracted_content}" ]; then
      printf "Restoring wp-content directory...\n"
      # Clear existing directory or merge? Replacing is safer for pristine states:
      rm -rf "${WP_CONTENT_DIR}"
      mkdir -p "$(dirname "${WP_CONTENT_DIR}")"
      mv "${extracted_content}" "${WP_CONTENT_DIR}"
      import_content_success=true
      printf "wp-content directory restored!\n"
    else
      [ "${import_mode}" == "content" ] && printf "[Error] wp-content directory not found in the archive! Cannot restore content.\n" && exit 1
      printf "[Warning] wp-content directory not found in the archive! Skipping content restoration.\n"
    fi
  fi

  # 2. Restore database from full backup
  if [ "${import_mode}" == "full" ]; then
    # Look for the .sql file unpacked at the root level of the temp directory
    local extracted_sql
    extracted_sql=$(find "${temp_extract_dir}" -maxdepth 1 -name "db-*.sql" | head -n 1)

    if [ -f "${extracted_sql}" ]; then
      printf "Importing database (%s) from archive ...\n" "$(basename "${extracted_sql}")"
      if ! wp db import "${extracted_sql}"; then
        printf "\n[Error] Database import failed during full restoration!\n\n"
        rm -rf "${temp_extract_dir}"
        exit 1
      fi
      import_database_success=true
      printf "Database imported.\n"
    else
      printf "[Warning] Database SQL file missing in backup archive! Skipping database import.\n"
    fi
  fi

  # Cleanup temp files
  rm -rf "${temp_extract_dir}"

  if [ "${import_mode}" == "full" ]; then
    if [ "${import_content_success}" == true ] && [ "${import_database_success}" == true ]; then
      printf "Full backup import completed successfully!\n"
    else
      printf "[Warning] Full backup import completed with warnings.\n"
    fi
  elif [ "${import_mode}" == "content" ]; then
    if [ "${import_content_success}" == true ]; then
      printf "Content backup import completed successfully!\n"
    else
      printf "[Warning] Content backup import completed with warnings.\n"
    fi
  fi

  exit 0
}

function main() {
  local programname usage
  programname=$(basename "${0}")
  usage="Usage: ${programname} <create|import> [mode] [file_path]"

  # check whether user had supplied -h or --help
  if [[ "$*" == "--help" || "$*" == "-h" ]]; then
    printf "This script creates and imports WordPress backups.\n\n"
    printf "%s\n" "${usage}"
    exit 0
  elif [ $# == 0 ] || [ -z "$1" ]; then
    printf "Insufficient amount of arguments!\n\n"
    printf "%s\n" "${usage}"
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
      printf "Invalid argument: %s\n" "${1}"
      printf "%s\n\n" "${usage}"
      exit 22
      ;;
  esac
}

args=("${@:-}")

main "${args[@]}"
