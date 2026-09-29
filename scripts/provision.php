#!/usr/bin/env php
<?php

/**
 * @file
 * Fixture site provisioning.
 *
 * Builds the throwaway Drupal site under build/ that the Behat suite runs
 * against. The script takes no arguments; the build is shaped by environment
 * variables.
 *
 * Environment variables:
 * - DRUPAL_VERSION: Core major to install, and the fixture directory to copy
 *   from. Defaults to '11'.
 * - DEPS: 'lowest' resolves Composer to the lowest stable versions, any other
 *   value installs from the fixture's lock file. Defaults to 'normal'.
 * - GITHUB_TOKEN: Written to the build's auth.json when set.
 * - DREVOPS_DEBUG: Set to any value to print each command before it runs.
 * - SCRIPT_QUIET: Set to '1' to suppress verbose messages.
 * - SCRIPT_RUN_SKIP: Set to '1' to skip running of the script. Useful when
 *   unit-testing or requiring this file from other files.
 *
 * Usage:
 * @code
 * php scripts/provision.php
 * @endcode
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

/**
 * Main functionality.
 *
 * @param array<string> $argv
 *   Array of arguments.
 * @param int $argc
 *   Number of arguments.
 *
 * @throws \RuntimeException
 *   When an argument is passed, or when a provisioning step fails.
 */
function main(array $argv, int $argc): void {
  if (array_intersect(['help', '--help', '-h', '-?'], $argv)) {
    print_help();

    return;
  }

  if ($argc > 1) {
    throw new \RuntimeException('This script takes no arguments. Use environment variables to shape the build.');
  }

  // Installs a Drupal site over Composer and Drush, which a unit test cannot
  // call. The CI matrix runs it on every leg.
  // @codeCoverageIgnoreStart
  provision();
  // @codeCoverageIgnoreEnd
}

/**
 * Print help.
 */
function print_help(): void {
  $script_name = basename(__FILE__);
  $out = <<<EOF
Fixture site provisioning.
--------------------------

Builds the throwaway Drupal site under build/ that the Behat suite runs
against. Takes no arguments; the build is shaped by environment variables.

Environment variables:
  DRUPAL_VERSION        Core major to install, and the fixture directory to
                        copy from. Defaults to 11.
  DEPS                  'lowest' resolves Composer to the lowest stable
                        versions. Defaults to 'normal'.
  GITHUB_TOKEN          Written to the build's auth.json when set.
  DREVOPS_DEBUG         Print each command before it runs.

Options:
  --help                This help.

Examples:
  php {$script_name}
  DRUPAL_VERSION=10 DEPS=lowest php {$script_name}

EOF;
  verbose($out);
}

/**
 * Show a verbose message and record messages into internal buffer.
 *
 * @param string $string
 *   Message to print.
 * @param bool|float|int|string|null ...$args
 *   Arguments to sprintf() the message.
 *
 * @return array<string>
 *   Array of messages.
 */
function verbose(string $string, ...$args): array {
  $string = sprintf($string, ...$args);

  static $buffer = [];
  $buffer[] = $string;
  if (empty(getenv('SCRIPT_QUIET'))) {
    // @codeCoverageIgnoreStart
    print end($buffer);
    // @codeCoverageIgnoreEnd
  }

  return $buffer;
}

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

  verbose('==> Starting provisioning of fixture Drupal %s site.' . PHP_EOL, $drupal_version);

  provision_apply_patches();

  verbose('  > Removing existing build assets.' . PHP_EOL);
  provision_run('chmod -Rf 777 ' . PROVISION_BUILD_DIR, [], TRUE);
  provision_run('rm -Rf ' . PROVISION_BUILD_DIR . '/.*', [], TRUE);
  provision_run('rm -Rf ' . PROVISION_BUILD_DIR . '/*', [], TRUE);

  if (!is_dir(PROVISION_BUILD_DIR) && !mkdir(PROVISION_BUILD_DIR, 0777, TRUE)) {
    throw new \RuntimeException('Unable to create ' . PROVISION_BUILD_DIR);
  }

  if (!chdir(PROVISION_BUILD_DIR)) {
    throw new \RuntimeException('Unable to enter ' . PROVISION_BUILD_DIR);
  }

  verbose('  > Copying fixture files to the build dir.' . PHP_EOL);
  provision_run('cp -Rf ' . escapeshellarg($fixture_dir . '/.') . ' ./');

  verbose('  > Validating fixture Composer configuration.' . PHP_EOL);
  provision_run('composer validate --ansi --no-check-all');

  verbose("  > Merging configuration from module's composer.json." . PHP_EOL);
  provision_write_merged_composer(PROVISION_PACKAGE_ROOT . '/composer.json', PROVISION_BUILD_DIR . '/composer.json');

  verbose('  > Show compiled composer.json.' . PHP_EOL);
  verbose('%s', (string) file_get_contents(PROVISION_BUILD_DIR . '/composer.json'));

  verbose('  > Validating merged fixture Composer configuration.' . PHP_EOL);
  provision_run('composer validate --ansi --no-check-all');

  verbose('  > Creating GitHub authentication token if provided.' . PHP_EOL);
  $github_token = provision_env('GITHUB_TOKEN', '');

  if ($github_token !== '') {
    $auth_file = PROVISION_BUILD_DIR . '/auth.json';

    // The file holds a credential. A umask denies every other user from the
    // moment the file is created, where a chmod() after the write would leave
    // the token readable in between. The build directory was emptied above,
    // so the file cannot already exist with a mode of its own.
    $umask = umask(0077);
    $written = file_put_contents($auth_file, (string) json_encode(['github-oauth' => ['github.com' => $github_token]]));
    umask($umask);

    if ($written === FALSE) {
      throw new \RuntimeException('Unable to write ' . $auth_file);
    }
  }

  verbose('  > Installing Composer dependencies inside the build dir.' . PHP_EOL);
  $install = $deps === 'lowest' ? 'composer update --prefer-lowest --prefer-stable' : 'composer install --prefer-dist';
  provision_run($install, ['COMPOSER_MEMORY_LIMIT' => '-1']);

  verbose('  > Running post-install-cmd.' . PHP_EOL);
  provision_run('composer run-script post-install-cmd');

  verbose('  > Installing Drupal site.' . PHP_EOL);
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

  verbose('  > Appending fixture $config overrides to settings.php for ConfigOverrideTrait tests.' . PHP_EOL);
  provision_append_settings(PROVISION_WEB_ROOT . '/sites/default/settings.php');

  verbose('  > Running post-install commands defined in the composer.json for each specific fixture.' . PHP_EOL);
  provision_run('composer run-script drupal-post-install');

  verbose('  > Copying test fixtures.' . PHP_EOL);
  provision_run('cp -Rf ' . PROVISION_PACKAGE_ROOT . '/tests/behat/fixtures/. ' . PROVISION_WEB_ROOT . '/sites/default/files/');

  verbose('  > Bootstrapping site.' . PHP_EOL);
  provision_confirm(PROVISION_DRUSH . ' -r ' . PROVISION_WEB_ROOT . ' --uri=' . PROVISION_SITE_URI . ' status --fields=bootstrap', 'Successful', 'Unable to bootstrap a site');

  if (!chdir(PROVISION_PACKAGE_ROOT)) {
    throw new \RuntimeException('Unable to return to ' . PROVISION_PACKAGE_ROOT);
  }

  verbose('==> Finished provisioning of fixture Drupal %s site.' . PHP_EOL, $drupal_version);
}

// @codeCoverageIgnoreEnd

/**
 * Applies the package's own patches through Composer Patches.
 *
 * A patch declared in composer.json is read by the Composer Patches
 * "Dependencies" resolver in every project that requires this package, which
 * resolves the path against that project's own root. The declaration is
 * written here, over the package's own composer.json, and reverted once
 * Composer has applied it.
 *
 * The patches apply to this package's own vendor directory, not to the
 * fixture site: "ahoy lint" runs the root vendor/bin/phpstan, and
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

  verbose("  > Applying the package's own patches through Composer Patches." . PHP_EOL);

  $composer_file = PROVISION_PACKAGE_ROOT . '/composer.json';
  $declared = provision_read_json($composer_file);
  $original = (string) file_get_contents($composer_file);
  $declared['extra']['patches'] = $patches;

  try {
    if (file_put_contents($composer_file, json_encode($declared, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL) === FALSE) {
      throw new \RuntimeException('Unable to write ' . $composer_file);
    }

    foreach ($patches as $package => $entries) {
      verbose('    %s: %d patch(es)' . PHP_EOL, $package, count($entries));
    }

    // "install" applies a patch only while it installs the package the patch
    // belongs to, and the dependencies are in place by now, so the packages
    // carrying one are re-fetched and re-patched explicitly.
    $composer = 'composer --working-dir=' . PROVISION_PACKAGE_ROOT . ' --ansi --no-interaction ';
    provision_run($composer . 'patches-relock', ['COMPOSER_MEMORY_LIMIT' => '-1']);
    provision_run($composer . 'patches-repatch', ['COMPOSER_MEMORY_LIMIT' => '-1']);
  }
  finally {
    file_put_contents($composer_file, $original);
  }
}

// @codeCoverageIgnoreEnd

/**
 * Maps the patch files under a directory to the packages they apply to.
 *
 * A patch file lives at "patches/<vendor>/<package>/<name>.patch", so the
 * package it applies to is its own directory and the set is iterated rather
 * than listed.
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
    verbose('+ %s' . PHP_EOL, $prefixed);
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
    verbose('%s' . PHP_EOL, $text);

    throw new \RuntimeException($failure);
  }

  verbose('    Success' . PHP_EOL);
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

// Entrypoint.
//
// @codeCoverageIgnoreStart
ini_set('display_errors', 1);

if (PHP_SAPI !== 'cli' || !empty($_SERVER['REMOTE_ADDR'])) {
  die('This script can be only ran from the command line.');
}

// Allow to skip the script run.
if (getenv('SCRIPT_RUN_SKIP') != 1) {
  set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    // A severity above E_USER_WARNING is a notice, a deprecation or a strict
    // warning. Composer and Drupal raise those throughout a healthy build, and
    // the catch below only exits for E_USER_WARNING and lower, so throwing
    // here would abort provisioning and still report success.
    if ((error_reporting() & $severity) === 0 || $severity > E_USER_WARNING) {
      // This error code is not included in error_reporting - continue
      // execution with the normal error handler.
      return FALSE;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
  });

  try {
    $argv = is_array($_SERVER['argv'] ?? NULL) ? array_filter($_SERVER['argv'], is_string(...)) : [];
    $argc = is_scalar($_SERVER['argc'] ?? NULL) ? (int) $_SERVER['argc'] : 0;
    // The function should not provide an exit code but rather throw exceptions.
    main($argv, $argc);
  }
  catch (\ErrorException $exception) {
    if ($exception->getSeverity() <= E_USER_WARNING) {
      verbose(PHP_EOL . 'RUNTIME ERROR: ' . $exception->getMessage() . PHP_EOL);
      exit($exception->getCode() === 0 ? 1 : $exception->getCode());
    }
  }
  catch (\Exception $exception) {
    verbose(PHP_EOL . 'ERROR: ' . $exception->getMessage() . PHP_EOL);
    exit($exception->getCode() == 0 ? 1 : $exception->getCode());
  }
}
// @codeCoverageIgnoreEnd
