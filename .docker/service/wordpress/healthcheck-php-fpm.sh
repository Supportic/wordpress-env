#!/usr/bin/env bash

set -e

if supervisorctl status php-fpm | grep -q "RUNNING"; then
  # php-fpm is running
  exit 0
else
  exit 1
fi
