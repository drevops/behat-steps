<?php

/**
 * @file
 * Fixture site provisioning.
 *
 * Builds the throwaway Drupal site under build/ that the Behat and PHPUnit
 * suites run against. The script takes no arguments; the build is shaped by
 * environment variables.
 *
 * Environment variables:
 * - DRUPAL_VERSION: Core major to install, and the fixture directory to copy
 *   from. Defaults to '11'.
 * - BEHAT: Behat major the build resolves to. Defaults to '3'.
 * - DEPS: 'lowest' resolves Composer to the lowest stable versions, any other
 *   value resolves to the newest. Defaults to 'normal'.
 * - GITHUB_TOKEN: Written to the build's auth.json when set.
 * - DREVOPS_DEBUG: Set to any value to print each command before it runs.
 *
 * Run with `ahoy provision`.
 */

declare(strict_types=1);

/**
 * Absolute path to the package root inside the container.
 */
const PROVISION_PACKAGE_ROOT = '/app';

/**
 * Absolute path to the throwaway build the fixture site is installed into.
 */
const PROVISION_BUILD_DIR = PROVISION_PACKAGE_ROOT . '/build';

/**
 * Absolute path to the fixture site's docroot.
 */
const PROVISION_WEB_ROOT = PROVISION_BUILD_DIR . '/web';

/**
 * Absolute path to the Drush the build installs.
 */
const PROVISION_DRUSH = PROVISION_BUILD_DIR . '/vendor/bin/drush';

/**
 * The URI the fixture site answers on.
 */
const PROVISION_SITE_URI = 'http://nginx';

/**
 * The database the fixture site installs into.
 */
const PROVISION_DB_URL = 'mysql://drupal:drupal@mariadb/drupal';

/**
 * The Drupal major the fixture installs from prerelease.
 *
 * No contrib release declares it, so the solve resolves only once relaxed.
 * Its "drupal/core-dev" is also the first to require PHPUnit 12.
 */
const PROVISION_DRUPAL_NEXT_MAJOR = 12;

/**
 * The plugin that strips the core constraint from a contrib requirement.
 */
const PROVISION_LENIENT_PLUGIN = 'mglaman/composer-drupal-lenient';

/**
 * Packages that run the test suites rather than the code under test.
 *
 * They sit in the package's "require-dev" without a matching "suggest" entry,
 * so the suggest intersection does not reach them.
 */
const PROVISION_TEST_RUNNER_PACKAGES = [
  'alexskrypnyk/phpunit-helpers',
  'drevops/behat-phpserver',
  'drevops/behat-screenshot',
  'dvdoug/behat-code-coverage',
];

/**
 * Drupal's own test namespaces, and the docroot path each maps to.
 *
 * Drupal registers them from its PHPUnit bootstrap rather than from a
 * Composer entry. A tool that loads only the autoloader therefore cannot
 * resolve a class such as KernelTestBase.
 *
 * The list is maintained by hand: the merge runs before the install, so the
 * docroot cannot be scanned for it.
 */
const PROVISION_DRUPAL_TEST_NAMESPACES = [
  'BuildTests',
  'FunctionalJavascriptTests',
  'FunctionalTests',
  'KernelTests',
  'TestSite',
  'Tests',
  'TestTools',
];

/**
 * Path the Drupal test namespaces resolve against, relative to the build.
 */
const PROVISION_DRUPAL_TEST_NAMESPACE_ROOT = 'web/core/tests/Drupal/';

/**
 * A content type only the fixture defines.
 *
 * "drush cim" can enable the modules, abort on a fatal raised while it
 * creates config entities, and still exit 0. The import is therefore
 * confirmed against an entity that core does not ship.
 */
const PROVISION_FIXTURE_CONTENT_TYPE = 'landing_page';

/**
 * Config overrides appended to the fixture site's settings.php.
 *
 * They mimic the environment-specific overrides a real site sets, so
 * ConfigOverrideTrait has stored values to read back through
 * ImmutableConfig::getOriginal().
 */
const PROVISION_SETTINGS_OVERRIDES = <<<'PHP'

// Fixture config overrides used by ConfigOverrideTrait tests. These mimic
// environment-specific overrides that a real site would set in settings.php,
// so tests can verify that @disable-config-override:<name> tags let the SUT
// read the stored (original) values via ImmutableConfig::getOriginal().
$config['system.site']['name'] = 'Overridden Site Name';
$config['system.site']['slogan'] = 'Overridden Slogan';

PHP;

// The provisioning runs only when the script is run directly, not when it is
// included.
// @codeCoverageIgnoreStart
if (basename((string) $_SERVER['SCRIPT_FILENAME']) === 'provision.php') {
  provision();
}
// @codeCoverageIgnoreEnd

/**
 * Installs the fixture site.
 *
 * @throws \RuntimeException
 *   When a step fails.
 *
 * @codeCoverageIgnoreStart
 */
function provision(): void {
  $drupal_version = provision_env('DRUPAL_VERSION', '11');
  $behat = provision_env('BEHAT', '3');
  $deps = provision_env('DEPS', 'normal');

  $fixture_dir = PROVISION_PACKAGE_ROOT . '/tests/behat/fixtures_drupal/d' . $drupal_version;
  $drush = PROVISION_DRUSH . ' -r ' . PROVISION_WEB_ROOT . ' --uri=' . PROVISION_SITE_URI;

  echo sprintf('==> Starting provisioning of fixture Drupal %s site on Behat %s.%s', $drupal_version, $behat, PHP_EOL);

  provision_apply_patches();

  echo '  > Removing existing build assets.' . PHP_EOL;
  provision_run('chmod -Rf 777 ' . PROVISION_BUILD_DIR, [], TRUE);
  provision_run('rm -Rf ' . PROVISION_BUILD_DIR . '/.*', [], TRUE);
  provision_run('rm -Rf ' . PROVISION_BUILD_DIR . '/*', [], TRUE);

  if (!is_dir(PROVISION_BUILD_DIR) && !mkdir(PROVISION_BUILD_DIR, 0777, TRUE)) {
    throw new \RuntimeException('Unable to create ' . PROVISION_BUILD_DIR);
  }

  if (!chdir(PROVISION_BUILD_DIR)) {
    throw new \RuntimeException('Unable to enter ' . PROVISION_BUILD_DIR);
  }

  echo '  > Copying fixture files to the build dir.' . PHP_EOL;
  provision_run('cp -Rf ' . escapeshellarg($fixture_dir . '/.') . ' ./');

  echo '  > Validating fixture Composer configuration.' . PHP_EOL;
  provision_run('composer validate --ansi --no-check-all');

  echo "  > Merging configuration from module's composer.json." . PHP_EOL;
  provision_write_merged_composer(PROVISION_PACKAGE_ROOT . '/composer.json', PROVISION_BUILD_DIR . '/composer.json');

  echo '  > Show compiled composer.json.' . PHP_EOL;
  echo (string) file_get_contents(PROVISION_BUILD_DIR . '/composer.json');

  echo '  > Validating merged fixture Composer configuration.' . PHP_EOL;
  provision_run('composer validate --ansi --no-check-all');

  echo '  > Creating GitHub authentication token if provided.' . PHP_EOL;
  provision_write_auth(provision_env('GITHUB_TOKEN', ''), PROVISION_BUILD_DIR . '/auth.json');

  $removals = provision_behat_packages($behat, $drupal_version);

  if ($removals !== []) {
    echo '  > Removing packages that cannot be installed alongside Behat ' . $behat . '.' . PHP_EOL;

    foreach ($removals as $package) {
      provision_run('composer remove --dev --no-update ' . escapeshellarg($package));
    }
  }

  if (provision_is_lenient($drupal_version)) {
    // A plugin takes part in a solve only once installed, and the fixture has
    // no solution until this one relaxes the contrib core constraints.
    // Composer loads globally installed plugins for local projects, so a
    // global install breaks the circular dependency.
    echo '  > Installing the Composer plugin that relaxes contrib core constraints.' . PHP_EOL;
    $constraint = provision_lenient_constraint($fixture_dir . '/composer.json');
    provision_run('composer global config --no-interaction allow-plugins.' . PROVISION_LENIENT_PLUGIN . ' true');
    provision_run('composer global require --no-interaction ' . escapeshellarg(PROVISION_LENIENT_PLUGIN . ':' . $constraint));
  }

  echo '  > Installing Composer dependencies inside the build dir.' . PHP_EOL;
  provision_run(provision_install_command($deps, $behat), ['COMPOSER_MEMORY_LIMIT' => '-1']);

  if (provision_is_lenient($drupal_version)) {
    echo '  > Widening the core version requirement of the installed contrib extensions.' . PHP_EOL;
    $widened = provision_widen_contrib(PROVISION_WEB_ROOT . '/modules/contrib', $drupal_version);
    echo sprintf('    Widened %d extension(s).%s', $widened, PHP_EOL);
  }

  echo '  > Running post-install-cmd.' . PHP_EOL;
  provision_run('composer run-script post-install-cmd');

  echo '  > Installing Drupal site.' . PHP_EOL;
  $install_arguments = [
    'si standard -y',
    '--db-url=' . PROVISION_DB_URL,
    '--account-name=admin',
    '--account-pass=admin',
    'install_configure_form.enable_update_status_module=NULL',
    'install_configure_form.enable_update_status_emails=NULL',
    '--uri=' . PROVISION_SITE_URI,
  ];
  provision_run(PROVISION_DRUSH . ' -r ' . PROVISION_WEB_ROOT . ' ' . implode(' ', $install_arguments), ['PHP_OPTIONS' => '-d sendmail_path=/bin/true']);

  echo '  > Appending fixture $config overrides to settings.php for ConfigOverrideTrait tests.' . PHP_EOL;
  provision_append_settings(PROVISION_WEB_ROOT . '/sites/default/settings.php');

  echo '  > Running post-install commands defined in the composer.json for each specific fixture.' . PHP_EOL;
  provision_run('composer run-script drupal-post-install');

  echo '  > Verifying the fixture configuration was imported.' . PHP_EOL;
  provision_confirm($drush . ' config:get node.type.' . PROVISION_FIXTURE_CONTENT_TYPE . ' type --format=string', PROVISION_FIXTURE_CONTENT_TYPE, 'Fixture configuration was not imported');

  echo '  > Copying test fixtures.' . PHP_EOL;
  provision_run('cp -Rf ' . PROVISION_PACKAGE_ROOT . '/tests/behat/fixtures/. ' . PROVISION_WEB_ROOT . '/sites/default/files/');

  echo '  > Bootstrapping site.' . PHP_EOL;
  provision_confirm($drush . ' status --fields=bootstrap', 'Successful', 'Unable to bootstrap a site');

  if (!chdir(PROVISION_PACKAGE_ROOT)) {
    throw new \RuntimeException('Unable to return to ' . PROVISION_PACKAGE_ROOT);
  }

  echo sprintf('==> Finished provisioning of fixture Drupal %s site.%s', $drupal_version, PHP_EOL);
}

// @codeCoverageIgnoreEnd

/**
 * Applies the package's own patches through Composer Patches.
 *
 * The Composer Patches "Dependencies" resolver reads a patch declared in
 * composer.json in every project that requires this package. It resolves the
 * path against that project's own root.
 *
 * The declaration is therefore written here, over the package's own
 * composer.json, and reverted once Composer has applied it.
 *
 * The patches apply to this package's own vendor directory, not to the
 * fixture site. "ahoy lint" runs the root vendor/bin/phpstan, and
 * mglaman/phpstan-drupal reads DRUPAL_ROOT and DRUPAL_VENDOR_ROOT only once
 * patched.
 *
 * @throws \RuntimeException
 *   When composer.json cannot be read or written.
 *
 * @codeCoverageIgnoreStart
 */
function provision_apply_patches(): void {
  $patches = provision_patches(PROVISION_PACKAGE_ROOT . '/patches', PROVISION_PACKAGE_ROOT);

  if ($patches === []) {
    return;
  }

  echo "  > Applying the package's own patches through Composer Patches." . PHP_EOL;

  // The finally writes $original back, so a read that returned FALSE is
  // rejected here rather than written over the package's own manifest.
  $composer_file = PROVISION_PACKAGE_ROOT . '/composer.json';
  $original = file_get_contents($composer_file);

  if ($original === FALSE) {
    throw new \RuntimeException('Unable to read ' . $composer_file);
  }

  $declared = provision_decode_json($original, $composer_file);
  $declared['extra']['patches'] = $patches;

  try {
    provision_write($composer_file, json_encode($declared, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

    foreach ($patches as $package => $entries) {
      echo sprintf('    %s: %d patch(es)%s', $package, count($entries), PHP_EOL);
    }

    // "install" applies a patch only while installing the patched package,
    // and the dependencies are already in place. The patched packages are
    // therefore re-fetched and re-patched explicitly.
    $composer = 'composer --working-dir=' . PROVISION_PACKAGE_ROOT . ' --ansi --no-interaction ';
    provision_run($composer . 'patches-relock', ['COMPOSER_MEMORY_LIMIT' => '-1']);
    provision_run($composer . 'patches-repatch', ['COMPOSER_MEMORY_LIMIT' => '-1']);
  }
  finally {
    provision_write($composer_file, $original);
  }
}

// @codeCoverageIgnoreEnd

/**
 * Maps the patch files under a directory to the packages they apply to.
 *
 * A patch file sits at "patches/<vendor>/<package>/<name>.patch", so its
 * directory names the package it applies to. The set is iterated rather than
 * listed.
 *
 * @param string $directory
 *   Absolute path to the directory holding the patches.
 * @param string $relative_to
 *   Absolute path that the returned patch paths are relative to.
 *
 * @return array<string, array<string, string>>
 *   Patch paths, keyed by package and then by description.
 */
function provision_patches(string $directory, string $relative_to): array {
  $patches = [];
  $files = glob($directory . '/*/*/*.patch');

  foreach ($files === FALSE ? [] : $files as $file) {
    $package = basename(dirname($file, 2)) . '/' . basename(dirname($file));
    $description = str_replace('-', ' ', basename($file, '.patch'));
    $patches[$package][$description] = substr($file, strlen($relative_to) + 1);
  }

  ksort($patches);

  return $patches;
}

/**
 * Reads an environment variable, falling back when it is unset or empty.
 *
 * @param string $name
 *   The variable to read.
 * @param string $default
 *   The value to use when the variable carries none.
 *
 * @return string
 *   The resolved value.
 */
function provision_env(string $name, string $default): string {
  $value = getenv($name);

  return is_string($value) && $value !== '' ? $value : $default;
}

/**
 * Prefixes a command with per-command environment variables.
 *
 * @param string $command
 *   The command to run.
 * @param array<string, string> $env
 *   Variables to set for this command alone.
 *
 * @return string
 *   The command, prefixed when there are variables to set.
 */
function provision_with_env(string $command, array $env): string {
  if ($env === []) {
    return $command;
  }

  $assignments = [];

  foreach ($env as $name => $value) {
    $assignments[] = $name . '=' . escapeshellarg($value);
  }

  return '/usr/bin/env ' . implode(' ', $assignments) . ' ' . $command;
}

/**
 * Runs a command, streaming its output.
 *
 * Every external binary the script calls goes through here.
 *
 * @param string $command
 *   The command to run.
 * @param array<string, string> $env
 *   Variables to set for this command alone.
 * @param bool $tolerate_failure
 *   Whether a non-zero exit leaves provisioning running.
 *
 * @throws \RuntimeException
 *   When the command exits non-zero and the failure is not tolerated.
 *
 * @codeCoverageIgnore
 */
function provision_run(string $command, array $env = [], bool $tolerate_failure = FALSE): void {
  $prefixed = provision_with_env($command, $env);

  if (provision_env('DREVOPS_DEBUG', '') !== '') {
    echo '+ ' . $prefixed . PHP_EOL;
  }

  $exit_code = 0;
  passthru($prefixed, $exit_code);

  if ($exit_code !== 0 && !$tolerate_failure) {
    throw new \RuntimeException(sprintf('Command exited with code %d: %s', $exit_code, $prefixed));
  }
}

/**
 * Confirms a command succeeds and that its output carries a value.
 *
 * @param string $command
 *   The command to run.
 * @param string $expected
 *   Text the output has to contain.
 * @param string $failure
 *   The message to fail with.
 *
 * @throws \RuntimeException
 *   When the command exits non-zero or its output lacks the expected text.
 *
 * @codeCoverageIgnore
 */
function provision_confirm(string $command, string $expected, string $failure): void {
  $output = [];
  $exit_code = 0;
  exec($command . ' 2>&1', $output, $exit_code);
  $text = implode(PHP_EOL, $output);

  if ($exit_code !== 0 || !str_contains($text, $expected)) {
    echo $text . PHP_EOL;

    throw new \RuntimeException($failure);
  }

  echo '    Success' . PHP_EOL;
}

/**
 * Writes the Composer authentication file when a token is available.
 *
 * @param string $token
 *   The GitHub token, empty when none was provided.
 * @param string $file
 *   Absolute path to the auth file.
 *
 * @throws \RuntimeException
 *   When the file cannot be written.
 */
function provision_write_auth(string $token, string $file): void {
  if ($token === '') {
    return;
  }

  // A umask denies every other user from the moment the file is created,
  // where a chmod() after the write would leave the token readable in
  // between. The build directory was emptied above, so the file cannot
  // already exist with a mode of its own.
  $umask = umask(0077);

  try {
    provision_write($file, (string) json_encode(['github-oauth' => ['github.com' => $token]]));
  }
  finally {
    umask($umask);
  }
}

/**
 * Writes a file, reporting a write that failed.
 *
 * Every write the script makes goes through here.
 *
 * @param string $file
 *   Absolute path to the file.
 * @param string $contents
 *   The contents to write.
 * @param int $flags
 *   Flags passed to file_put_contents().
 *
 * @throws \RuntimeException
 *   When the file cannot be written.
 */
function provision_write(string $file, string $contents, int $flags = 0): void {
  if (file_put_contents($file, $contents, $flags) === FALSE) {
    throw new \RuntimeException('Unable to write ' . $file);
  }
}

/**
 * Lists the packages that cannot be installed alongside the Behat major.
 *
 * @param string $behat
 *   The Behat major the build resolves to.
 * @param string $drupal_version
 *   The Drupal major the build installs.
 *
 * @return array<int, string>
 *   The packages to remove before the install.
 */
function provision_behat_packages(string $behat, string $drupal_version): array {
  if ($behat !== '4') {
    return [];
  }

  // 'dmore/behat-chrome-extension' has no release that accepts Behat 4.
  $packages = ['dmore/behat-chrome-extension'];

  // Every 'dvdoug/behat-code-coverage' release that accepts Behat 4 needs
  // 'phpunit/php-code-coverage' 12, which PHPUnit 11 rules out.
  if ((int) $drupal_version < PROVISION_DRUPAL_NEXT_MAJOR) {
    $packages[] = 'dvdoug/behat-code-coverage';
  }

  return $packages;
}

/**
 * Indicates whether the Drupal major needs its contrib set relaxed.
 *
 * @param string $drupal_version
 *   The Drupal major the build installs.
 */
function provision_is_lenient(string $drupal_version): bool {
  return (int) $drupal_version >= PROVISION_DRUPAL_NEXT_MAJOR;
}

/**
 * Reads the constraint the fixture pins the lenient plugin at.
 *
 * The plugin is installed globally, outside the build, so it has no
 * constraint of its own to resolve against.
 *
 * @param string $file
 *   Absolute path to the fixture's composer.json.
 *
 * @return string
 *   The constraint.
 *
 * @throws \RuntimeException
 *   When the fixture does not require the plugin.
 */
function provision_lenient_constraint(string $file): string {
  $require = provision_section(provision_read_json($file), 'require');
  $constraint = $require[PROVISION_LENIENT_PLUGIN] ?? NULL;

  if (!is_string($constraint) || $constraint === '') {
    throw new \RuntimeException(PROVISION_LENIENT_PLUGIN . ' is not required by ' . $file);
  }

  return $constraint;
}

/**
 * Builds the Composer command that resolves the build's dependencies.
 *
 * The package constraint allows both Behat majors, and "--with" narrows it
 * to the one this build runs on.
 *
 * The command passes no '--prefer-dist': that flag overrides the per-package
 * 'preferred-install' setting a fixture declares.
 *
 * @param string $deps
 *   Set to 'lowest' to resolve to the lowest stable versions.
 * @param string $behat
 *   The Behat major the build resolves to.
 *
 * @return string
 *   The command.
 */
function provision_install_command(string $deps, string $behat): string {
  $flags = $deps === 'lowest' ? ' --prefer-lowest --prefer-stable' : '';

  return 'composer update' . $flags . ' --with=' . escapeshellarg('behat/behat:^' . $behat);
}

/**
 * Widens the core version requirement across the installed contrib tree.
 *
 * @param string $directory
 *   Absolute path to the contrib directory.
 * @param string $major
 *   The Drupal major to admit.
 *
 * @return int
 *   The number of extensions rewritten.
 *
 * @throws \RuntimeException
 *   When an extension cannot be written.
 */
function provision_widen_contrib(string $directory, string $major): int {
  if (!is_dir($directory)) {
    return 0;
  }

  $widened = 0;
  $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

  foreach ($files as $file) {
    if (!$file instanceof \SplFileInfo || !str_ends_with($file->getFilename(), '.info.yml')) {
      continue;
    }

    $text = (string) file_get_contents($file->getPathname());
    $updated = provision_widen_core_requirement($text, $major);

    if ($updated === $text) {
      continue;
    }

    provision_write($file->getPathname(), $updated);

    $widened++;
  }

  return $widened;
}

/**
 * Admits a Drupal major into an extension's core version requirement.
 *
 * Composer installs the contrib code, but Drupal reads
 * core_version_requirement from each extension and does not enable one that
 * excludes the running major. No contrib release declares Drupal 12, so the
 * fixture widens what it received.
 *
 * @param string $text
 *   The contents of an info file.
 * @param string $major
 *   The Drupal major to admit.
 *
 * @return string
 *   The contents, with the major admitted where it was missing.
 */
function provision_widen_core_requirement(string $text, string $major): string {
  $admitted = '^' . $major;

  $updated = preg_replace_callback('/^core_version_requirement: *([^#\n]*?) *(#.*)?$/m', static function (array $matches) use ($admitted): string {
    $constraint = trim($matches[1], " \"'");

    if ($constraint === '' || str_contains($constraint, $admitted)) {
      return $matches[0];
    }

    $comment = ($matches[2] ?? '') === '' ? '' : ' ' . $matches[2];

    return "core_version_requirement: '" . $constraint . ' || ' . $admitted . "'" . $comment;
  }, $text);

  return $updated ?? $text;
}

/**
 * Appends the fixture config overrides to a settings file.
 *
 * Drupal leaves the installed settings.php read-only, so the file is made
 * writable for the write and read-only again after it.
 *
 * @param string $file
 *   Absolute path to the settings file.
 *
 * @throws \RuntimeException
 *   When the file cannot be written.
 */
function provision_append_settings(string $file): void {
  if (!is_file($file) || !chmod($file, 0666)) {
    throw new \RuntimeException('Unable to open ' . $file . ' for writing');
  }

  provision_write($file, PROVISION_SETTINGS_OVERRIDES, FILE_APPEND);

  chmod($file, 0444);
}

/**
 * Reads and decodes a JSON file.
 *
 * @param string $file
 *   Absolute path to the file.
 *
 * @return array<array-key, mixed>
 *   The decoded contents.
 *
 * @throws \RuntimeException
 *   When the file is missing or does not decode to an object.
 */
function provision_read_json(string $file): array {
  if (!is_file($file)) {
    throw new \RuntimeException('Unable to read ' . $file);
  }

  return provision_decode_json((string) file_get_contents($file), $file);
}

/**
 * Decodes JSON that was read from a file.
 *
 * @param string $contents
 *   The contents to decode.
 * @param string $file
 *   Absolute path the contents came from, named when they do not decode.
 *
 * @return array<array-key, mixed>
 *   The decoded contents.
 *
 * @throws \RuntimeException
 *   When the contents do not decode to an object.
 */
function provision_decode_json(string $contents, string $file): array {
  $decoded = json_decode($contents, TRUE);

  if (!is_array($decoded)) {
    throw new \RuntimeException('Unable to decode ' . $file);
  }

  return $decoded;
}

/**
 * Reads a top-level section of a decoded composer.json.
 *
 * @param array<array-key, mixed> $config
 *   Decoded composer.json contents.
 * @param string $key
 *   The section to read.
 *
 * @return array<array-key, mixed>
 *   The section, empty when it is absent or is not an object.
 */
function provision_section(array $config, string $key): array {
  $section = $config[$key] ?? NULL;

  return is_array($section) ? $section : [];
}

/**
 * Merges the package's Composer configuration into the fixture's, and writes.
 *
 * @param string $package_file
 *   Absolute path to the package's composer.json.
 * @param string $fixture_file
 *   Absolute path to the fixture's composer.json, which is overwritten.
 *
 * @throws \RuntimeException
 *   When either file cannot be read, or the result cannot be written.
 */
function provision_write_merged_composer(string $package_file, string $fixture_file): void {
  $merged = provision_merge_composer(provision_read_json($package_file), provision_read_json($fixture_file));

  provision_write($fixture_file, (string) json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/**
 * Merges the package's Composer configuration into the fixture's.
 *
 * The fixture site exercises every trait at once, so everything a consumer
 * opts into package by package is required here. This covers the package's
 * own runtime requirements, the "require-dev" entries that back a "suggest"
 * entry, and the packages that run the test suites.
 *
 * @param array<array-key, mixed> $package
 *   Decoded contents of the package's composer.json.
 * @param array<array-key, mixed> $fixture
 *   Decoded contents of the fixture's composer.json.
 *
 * @return array<array-key, mixed>
 *   The merged configuration.
 */
function provision_merge_composer(array $package, array $fixture): array {
  $require = provision_section($package, 'require');
  $require_dev = provision_section($package, 'require-dev');
  $suggest = provision_section($package, 'suggest');

  $merged_require_dev = array_merge($require, array_intersect_key($require_dev, $suggest));
  $merged_require_dev = array_merge($merged_require_dev, array_intersect_key($require_dev, array_flip(PROVISION_TEST_RUNNER_PACKAGES)));
  unset($merged_require_dev['php']);

  $rebased_dev = provision_rebase_psr4(provision_section($package, 'autoload-dev'));
  $psr4 = array_merge(['DrevOps\\BehatSteps\\' => '../src/'], provision_section($rebased_dev, 'psr-4'));

  foreach (PROVISION_DRUPAL_TEST_NAMESPACES as $namespace) {
    $psr4['Drupal\\' . $namespace . '\\'] = PROVISION_DRUPAL_TEST_NAMESPACE_ROOT . $namespace . '/';
  }

  // Only the package's own paths are rebased. A path the fixture declares is
  // already relative to the build, so the merge runs after the rebase.
  $filtered = [
    'require-dev' => $merged_require_dev,
    'autoload' => provision_rebase_psr4(provision_section($package, 'autoload')),
    'autoload-dev' => ['psr-4' => $psr4],
  ];

  $merged = array_replace_recursive($filtered, $fixture);

  // Under "--prefer-lowest", a package in both sections resolves to the lower
  // constraint, which can fall outside the range the fixture pins. The
  // fixture constraint is therefore kept.
  $merged['require-dev'] = array_diff_key(provision_section($merged, 'require-dev'), provision_section($merged, 'require'));

  return $merged;
}

/**
 * Prefixes every PSR-4 path of an autoload section with "../".
 *
 * The build sits 1 level below the package root, so a path the package
 * declares relative to itself only resolves from the build with the prefix.
 * A namespace may map to a list of directories rather than to one.
 *
 * @param array<array-key, mixed> $autoload
 *   An "autoload" section.
 *
 * @return array<array-key, mixed>
 *   The section, with its PSR-4 paths rebased on the package root.
 */
function provision_rebase_psr4(array $autoload): array {
  $psr4 = provision_section($autoload, 'psr-4');

  if ($psr4 === []) {
    return $autoload;
  }

  foreach ($psr4 as $namespace => $path) {
    if (!is_array($path)) {
      $psr4[$namespace] = provision_rebase_path($path);

      continue;
    }

    $directories = [];

    foreach ($path as $key => $directory) {
      $directories[$key] = provision_rebase_path($directory);
    }

    $psr4[$namespace] = $directories;
  }

  $autoload['psr-4'] = $psr4;

  return $autoload;
}

/**
 * Rebases one autoload path on the package root.
 *
 * @param mixed $path
 *   A path declared relative to the package root.
 *
 * @return string
 *   The path, relative to the build directory.
 */
function provision_rebase_path(mixed $path): string {
  return '../' . (is_string($path) ? $path : '');
}
