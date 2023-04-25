#!/usr/bin/env bash
##
# Install site.
#
# shellcheck disable=SC2015,SC2094

set -e
[ -n "${DREVOPS_DEBUG}" ] && set -x

DRUPAL_VERSION="${DRUPAL_VERSION:-9}"

echo "==> Starting installation of fixture Drupal ${DRUPAL_VERSION} site."

echo "  > Removing existing build assets."
chmod -Rf 777 /app/build || true; rm -Rf /app/build/.* || true; rm -Rf /app/build/* || true;

mkdir -p /app/build

pushd /app/build/ >/dev/null || exit 1

echo "  > Copying fixture files to the build dir."
cp -Rf "/app/tests/behat/fixtures/d${DRUPAL_VERSION}/." ./

echo "  > Validating fixture Composer configuration."
composer validate --ansi --strict --no-check-all

echo "  > Merging configuration from module's composer.json."
php -r "echo json_encode(array_replace_recursive(json_decode(file_get_contents('/app/composer.json'), true),json_decode(file_get_contents('/app/build/composer.json'), true)),JSON_PRETTY_PRINT);" > "/app/build/composer2.json" && mv -f "/app/build/composer2.json" "/app/build/composer.json"

echo "  > Updating relative paths in build composer.json."
sed_opts=(-i) && [ "$(uname)" == "Darwin" ] && sed_opts=(-i '')
sed "${sed_opts[@]}" 's|\"DrevOps\\\\BehatSteps\\\\": \"src\\\/\"|\"DrevOps\\\\BehatSteps\\\\": \"..\/src\/\"|' "composer.json" && sleep 2
sed "${sed_opts[@]}" 's|\"DrevOps\\\\BehatSteps\\\\D7\\\\": \"src\\\/D7\\\/\"|\"DrevOps\\\\BehatSteps\\\\D7\\\\": \"..\/src\/D7\/\"|' "composer.json" && sleep 2

echo "  > Show compiled composer.json."
cat composer.json

echo "  > Validating merged fixture Composer configuration."
composer validate --ansi --strict --no-check-all

echo "  > Creating GitHub authentication token if provided."
[ -n "$GITHUB_TOKEN" ] && echo "{\"github-oauth\": {\"github.com\": \"$GITHUB_TOKEN\"}}" > /app/build/auth.json

echo "  > Installing Composer dependencies inside the build dir."
COMPOSER_MEMORY_LIMIT=-1 composer install --prefer-dist

echo "  > Running post-install-cmd."
composer run-script post-install-cmd

echo "  > Installing Drupal site."
/usr/bin/env PHP_OPTIONS='-d sendmail_path=/bin/true' /app/build/vendor/bin/drush -r /app/build/web si standard -y --db-url=mysql://drupal:drupal@mariadb/drupal --account-name=admin --account-pass=admin install_configure_form.enable_update_status_module=NULL install_configure_form.enable_update_status_emails=NULL --uri=http://nginx

echo "  > Running post-install commands defined in the composer.json for each specific fixture."
composer run-script drupal-post-install

echo "  > Copying test fixture."
cp /app/tests/behat/fixtures/relative.html /app/build/web/sites/default/files/relative.html

echo "  > Bootstrapping site."
/app/build/vendor/bin/drush -r /app/build/web --uri=http://nginx status --fields=bootstrap | grep -q "Successful" && echo "    Success" || ( echo "ERROR: Unable to bootstrap a site" && exit 1 )

popd >/dev/null || exit 1

echo "==> Finished installation of fixture Drupal ${DRUPAL_VERSION} site."
