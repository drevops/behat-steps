<?php

/**
 * @file
 * Fixture site provisioning.
 *
 * Builds the throwaway Drupal site under build/ that the Behat suite runs
 * against. DRUPAL_VERSION picks the core major and the fixture directory it
 * copies from, and DEPS switches Composer between its newest and its lowest
 * resolution.
 *
 * Run with: php scripts/provision.php
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
 * Packages that run the Behat suite rather than the code under test.
 *
 * They sit in the package's "require-dev" without a matching "suggest" entry,
 * so the suggest intersection does not reach them.
 */
const PROVISION_TEST_RUNNER_PACKAGES = [
  'drevops/behat-phpserver',
  'drevops/behat-screenshot',
  'dvdoug/behat-code-coverage',
];

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

// The entry function runs only when the script is run directly, not when it
// is included.
// @codeCoverageIgnoreStart
if (basename((string) $_SERVER['SCRIPT_FILENAME']) === 'provision.php') {
  try {
    provision();
  }
  catch (\Throwable $exception) {
    echo 'ERROR: ' . $exception->getMessage() . PHP_EOL;
    exit(1);
  }
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
  $deps = provision_env('DEPS', 'normal');

  $fixture_dir = PROVISION_PACKAGE_ROOT . '/tests/behat/fixtures_drupal/d' . $drupal_version;

  echo sprintf('==> Starting provisioning of fixture Drupal %s site.%s', $drupal_version, PHP_EOL);

  provision_step('Removing existing build assets.');
  provision_run('chmod -Rf 777 ' . PROVISION_BUILD_DIR, [], TRUE);
  provision_run('rm -Rf ' . PROVISION_BUILD_DIR . '/.*', [], TRUE);
  provision_run('rm -Rf ' . PROVISION_BUILD_DIR . '/*', [], TRUE);

  if (!is_dir(PROVISION_BUILD_DIR) && !mkdir(PROVISION_BUILD_DIR, 0777, TRUE)) {
    throw new \RuntimeException('Unable to create ' . PROVISION_BUILD_DIR);
  }

  if (!chdir(PROVISION_BUILD_DIR)) {
    throw new \RuntimeException('Unable to enter ' . PROVISION_BUILD_DIR);
  }

  provision_step('Copying fixture files to the build dir.');
  provision_run('cp -Rf ' . escapeshellarg($fixture_dir . '/.') . ' ./');

  provision_step('Validating fixture Composer configuration.');
  provision_run('composer validate --ansi --no-check-all');

  provision_step("Merging configuration from module's composer.json.");
  provision_write_merged_composer(PROVISION_PACKAGE_ROOT . '/composer.json', PROVISION_BUILD_DIR . '/composer.json');

  provision_step('Show compiled composer.json.');
  echo (string) file_get_contents(PROVISION_BUILD_DIR . '/composer.json');

  provision_step('Validating merged fixture Composer configuration.');
  provision_run('composer validate --ansi --no-check-all');

  provision_step('Creating GitHub authentication token if provided.');
  $github_token = provision_env('GITHUB_TOKEN', '');

  if ($github_token !== '') {
    $auth_file = PROVISION_BUILD_DIR . '/auth.json';
    file_put_contents($auth_file, (string) json_encode(['github-oauth' => ['github.com' => $github_token]]));
    // The file holds a credential, and the build directory is world-writable
    // for the duration of the next cleanup.
    chmod($auth_file, 0600);
  }

  provision_step('Installing Composer dependencies inside the build dir.');
  $install = $deps === 'lowest' ? 'composer update --prefer-lowest --prefer-stable' : 'composer install --prefer-dist';
  provision_run($install, ['COMPOSER_MEMORY_LIMIT' => '-1']);

  provision_step('Running post-install-cmd.');
  provision_run('composer run-script post-install-cmd');

  provision_step('Installing Drupal site.');
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

  provision_step('Appending fixture $config overrides to settings.php for ConfigOverrideTrait tests.');
  provision_append_settings(PROVISION_WEB_ROOT . '/sites/default/settings.php');

  provision_step('Running post-install commands defined in the composer.json for each specific fixture.');
  provision_run('composer run-script drupal-post-install');

  provision_step('Copying test fixtures.');
  provision_run('cp -Rf ' . PROVISION_PACKAGE_ROOT . '/tests/behat/fixtures/. ' . PROVISION_WEB_ROOT . '/sites/default/files/');

  provision_step('Bootstrapping site.');
  provision_confirm(PROVISION_DRUSH . ' -r ' . PROVISION_WEB_ROOT . ' --uri=' . PROVISION_SITE_URI . ' status --fields=bootstrap', 'Successful', 'Unable to bootstrap a site');

  if (!chdir(PROVISION_PACKAGE_ROOT)) {
    throw new \RuntimeException('Unable to return to ' . PROVISION_PACKAGE_ROOT);
  }

  echo sprintf('==> Finished provisioning of fixture Drupal %s site.%s', $drupal_version, PHP_EOL);
}

// @codeCoverageIgnoreEnd

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
 * Prints a step heading.
 *
 * @param string $message
 *   The heading text.
 *
 * @codeCoverageIgnore
 */
function provision_step(string $message): void {
  echo sprintf('  > %s%s', $message, PHP_EOL);
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
 * Appends the fixture config overrides to a settings file.
 *
 * Drupal leaves the installed settings.php read-only, so the write is opened
 * and closed around.
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

  if (file_put_contents($file, PROVISION_SETTINGS_OVERRIDES, FILE_APPEND) === FALSE) {
    throw new \RuntimeException('Unable to append the fixture config overrides to ' . $file);
  }

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

  $decoded = json_decode((string) file_get_contents($file), TRUE);

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

  if (file_put_contents($fixture_file, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === FALSE) {
    throw new \RuntimeException('Unable to write ' . $fixture_file);
  }
}

/**
 * Merges the package's Composer configuration into the fixture's.
 *
 * The fixture site exercises every trait at once, so what a consumer opts
 * into package by package is all required here: the package's own runtime
 * requirements, the "require-dev" entries that back a "suggest" entry, and
 * the packages that run the Behat suite.
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

  // Only the package's own paths are rebased. A path the fixture declares is
  // already relative to the build, so the merge runs after the rebase.
  $filtered = [
    'require-dev' => $merged_require_dev,
    'autoload' => provision_rebase_psr4(provision_section($package, 'autoload')),
    'autoload-dev' => ['psr-4' => ['DrevOps\\BehatSteps\\' => '../src/']],
  ];

  return array_replace_recursive($filtered, $fixture);
}

/**
 * Prefixes every PSR-4 path of an autoload section with "../".
 *
 * The build sits one level below the package root, so a path the package
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
