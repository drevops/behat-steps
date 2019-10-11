#!/usr/bin/env bash
##
# Build.
#
set -e

echo "==> Validate composer"
composer validate --ansi --strict

# Process Docker Compose configuration. This is used to avoid multiple
# docker-compose.yml files.
# Remove lines containing '###'.
sed -i -e "/###/d" docker-compose.yml
# Uncomment lines containing '##'.
sed -i -e "s/##//" docker-compose.yml

echo "==> Initialise Drupal site"
ahoy build
