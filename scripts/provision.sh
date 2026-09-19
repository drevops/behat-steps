#!/usr/bin/env bash
##
# Install site.
#
# shellcheck disable=SC2015,SC2094

set -e
[ -n "${DREVOPS_DEBUG}" ] && set -x

DRUPAL_VERSION="${DRUPAL_VERSION:-11}"
DEPS="${DEPS:-normal}"
BEHAT="${BEHAT:-3}"

echo "==> Starting provisioning of fixture Drupal ${DRUPAL_VERSION} site on Behat ${BEHAT}."

echo "  > Removing existing build assets."
chmod -Rf 777 /app/build || true; rm -Rf /app/build/.* || true; rm -Rf /app/build/* || true;

mkdir -p /app/build

pushd /app/build/ >/dev/null || exit 1

echo "  > Copying fixture files to the build dir."
cp -Rf "/app/tests/behat/fixtures_drupal/d${DRUPAL_VERSION}/." ./

echo "  > Validating fixture Composer configuration."
composer validate --ansi --no-check-all

echo "  > Merging configuration from module's composer.json."
php -r '
$package = json_decode(file_get_contents("/app/composer.json"), true);
$fixture = json_decode(file_get_contents("/app/build/composer.json"), true);

// Cherry-pick the required properties from the base composer.json.
$package_filtered["require-dev"] = $package["require"];

// Trait-specific runtime dependencies live in "require-dev" + "suggest" rather
// than "require", so the fixture site - which exercises every trait - must pull
// each suggested package back in to run the full Behat suite.
$package_filtered["require-dev"] = array_merge($package_filtered["require-dev"], array_intersect_key($package["require-dev"], $package["suggest"]));

// Deps required to run the Behat and PHPUnit suites, which both execute from
// the build so that Drupal classes resolve.
$package_filtered["require-dev"] = array_merge($package_filtered["require-dev"], array_filter($package["require-dev"], function ($ver, $name) {
  return in_array($name, [
    "alexskrypnyk/phpunit-helpers",
    "drevops/behat-phpserver",
    "drevops/behat-screenshot",
    "dvdoug/behat-code-coverage",
  ]);
}, ARRAY_FILTER_USE_BOTH));
unset($package_filtered["require-dev"]["php"]);

$package_filtered["autoload"] = $package["autoload"];

// The build sits one level below the package root, so every package-relative
// autoload path gains a "../" prefix. The driver test suites run from the build
// and resolve the package, its tests and their fixtures through these entries.
$package_filtered["autoload-dev"]["psr-4"]["DrevOps\\BehatSteps\\"] = "../src/";

foreach ($package["autoload-dev"]["psr-4"] as $namespace => $namespace_path) {
  $package_filtered["autoload-dev"]["psr-4"][$namespace] = "../" . $namespace_path;
}

// Drupal maps its own test namespaces from its PHPUnit bootstrap rather than
// from a Composer entry, so a tool that loads only the autoloader cannot
// resolve a class such as "KernelTestBase". Registering them here makes the
// site autoloader complete on its own.
foreach (["BuildTests", "FunctionalJavascriptTests", "FunctionalTests", "KernelTests", "TestSite", "Tests", "TestTools"] as $test_namespace) {
  $package_filtered["autoload-dev"]["psr-4"]["Drupal\\" . $test_namespace . "\\"] = "web/core/tests/Drupal/" . $test_namespace . "/";
}

$merged = array_replace_recursive($package_filtered, $fixture);

// A package named in both sections resolves to the lower of the two
// constraints under "--prefer-lowest", which can fall outside the range the
// fixture pins, so the fixture constraint is the one that survives.
$merged["require-dev"] = array_diff_key($merged["require-dev"], $merged["require"]);

echo json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
' > "/app/build/composer2.json" && mv -f "/app/build/composer2.json" "/app/build/composer.json"

echo "  > Updating relative paths in build composer.json."
sed_opts=(-i) && [ "$(uname)" == "Darwin" ] && sed_opts=(-i '')
sed "${sed_opts[@]}" 's|"DrevOps\\\\BehatSteps\\\\": "src/"|"DrevOps\\\\BehatSteps\\\\": "../src/"|' "composer.json" && sleep 2

echo "  > Show compiled composer.json."
cat composer.json
echo "  > Validating merged fixture Composer configuration."
composer validate --ansi --no-check-all

echo "  > Creating GitHub authentication token if provided."
[ -n "$GITHUB_TOKEN" ] && echo "{\"github-oauth\": {\"github.com\": \"$GITHUB_TOKEN\"}}" > /app/build/auth.json

if [ "${BEHAT}" = "4" ]; then
  echo "  > Removing packages that cannot be installed alongside Behat 4."
  # 'dmore/behat-chrome-extension' has no release that accepts Behat 4.
  composer remove --dev --no-update dmore/behat-chrome-extension

  if [ "${DRUPAL_VERSION}" -lt 12 ]; then
    # Every 'dvdoug/behat-code-coverage' release that accepts Behat 4 needs
    # 'phpunit/php-code-coverage' 12, which PHPUnit 11 rules out. Drupal 12
    # brings PHPUnit 12 in 'drupal/core-dev'.
    composer remove --dev --no-update dvdoug/behat-code-coverage
  fi
fi

if [ "${DRUPAL_VERSION}" -ge 12 ]; then
  # 'alexskrypnyk/phpunit-helpers' caps 'symfony/process' at 7, while Drupal
  # 12 requires 8. The PHPUnit suites need it, so they do not run here.
  echo "  > Removing packages that cannot be installed alongside Drupal 12."
  composer remove --dev --no-update alexskrypnyk/phpunit-helpers

  # A plugin only shapes a solve that it is already installed for, and the
  # fixture has no solution until this one relaxes the contrib core
  # constraints. Composer loads globally installed plugins for local projects,
  # so installing it outside the build breaks that circle.
  echo "  > Installing the Composer plugin that relaxes contrib core constraints."
  lenient_constraint="$(php -r 'echo json_decode(file_get_contents($argv[1]), TRUE)["require"]["mglaman/composer-drupal-lenient"];' "/app/tests/behat/fixtures_drupal/d${DRUPAL_VERSION}/composer.json")"
  composer global config --no-interaction allow-plugins.mglaman/composer-drupal-lenient true
  composer global require --no-interaction "mglaman/composer-drupal-lenient:${lenient_constraint}"
fi

# The constraint in composer.json allows both Behat majors, and '--with'
# narrows it to the one this build runs on.
echo "  > Installing Composer dependencies inside the build dir."
if [ "${DEPS}" = "lowest" ]; then
  COMPOSER_MEMORY_LIMIT=-1 composer update --prefer-lowest --prefer-stable --with="behat/behat:^${BEHAT}"
else
  COMPOSER_MEMORY_LIMIT=-1 composer update --prefer-dist --with="behat/behat:^${BEHAT}"
fi

if [ "${DRUPAL_VERSION}" -ge 12 ]; then
  # Composer installs the contrib code, but Drupal reads
  # 'core_version_requirement' from each extension and refuses to enable one
  # that excludes the running major. No contrib release declares Drupal 12, so
  # the fixture widens what it received.
  echo "  > Widening the core version requirement of the installed contrib extensions."
  php -r '
$widened = 0;
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("/app/build/web/modules/contrib", FilesystemIterator::SKIP_DOTS));

foreach ($files as $file) {
  if (!str_ends_with($file->getFilename(), ".info.yml")) {
    continue;
  }

  $text = file_get_contents($file->getPathname());

  $updated = preg_replace_callback("/^core_version_requirement: *([^#\n]*?) *(#.*)?$/m", function (array $matches): string {
    $constraint = trim($matches[1], " \"\x27");

    if ($constraint === "" || str_contains($constraint, "^12")) {
      return $matches[0];
    }

    $comment = ($matches[2] ?? "") === "" ? "" : " " . $matches[2];

    return "core_version_requirement: \x27" . $constraint . " || ^12\x27" . $comment;
  }, $text);

  if ($updated === $text) {
    continue;
  }

  file_put_contents($file->getPathname(), $updated);
  $widened++;
}

echo "    Widened " . $widened . " extension(s).\n";
'
fi

echo "  > Running post-install-cmd."
composer run-script post-install-cmd

echo "  > Installing Drupal site."
/usr/bin/env PHP_OPTIONS='-d sendmail_path=/bin/true' /app/build/vendor/bin/drush -r /app/build/web si standard -y --db-url=mysql://drupal:drupal@mariadb/drupal --account-name=admin --account-pass=admin install_configure_form.enable_update_status_module=NULL install_configure_form.enable_update_status_emails=NULL --uri=http://nginx

echo "  > Appending fixture \$config overrides to settings.php for ConfigOverrideTrait tests."
chmod 666 /app/build/web/sites/default/settings.php
cat >> /app/build/web/sites/default/settings.php <<'PHP'

// Fixture config overrides used by ConfigOverrideTrait tests. These mimic
// environment-specific overrides that a real site would set in settings.php,
// so tests can verify that @disable-config-override:<name> tags let the SUT
// read the stored (original) values via ImmutableConfig::getOriginal().
$config['system.site']['name'] = 'Overridden Site Name';
$config['system.site']['slogan'] = 'Overridden Slogan';
PHP
chmod 444 /app/build/web/sites/default/settings.php

echo "  > Running post-install commands defined in the composer.json for each specific fixture."
composer run-script drupal-post-install

# 'drush cim' can enable the modules, abort on a fatal raised while the config
# entities are being created, and still exit 0. The site then boots with none
# of the content types, fields or entity types the suite asserts on, so the
# import is confirmed against a config entity only the fixture defines.
echo "  > Verifying the fixture configuration was imported."
/app/build/vendor/bin/drush -r /app/build/web --uri=http://nginx config:get node.type.landing_page type --format=string >/dev/null 2>&1 && echo "    Success" || ( echo "ERROR: Fixture configuration was not imported" && exit 1 )

echo "  > Copying test fixtures."
cp -Rf /app/tests/behat/fixtures/. /app/build/web/sites/default/files/

echo "  > Bootstrapping site."
/app/build/vendor/bin/drush -r /app/build/web --uri=http://nginx status --fields=bootstrap | grep -q "Successful" && echo "    Success" || ( echo "ERROR: Unable to bootstrap a site" && exit 1 )

popd >/dev/null || exit 1

echo "==> Finished provisioning of fixture Drupal ${DRUPAL_VERSION} site."
