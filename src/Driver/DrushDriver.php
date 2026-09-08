<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Driver;

use DrevOps\BehatSteps\Driver\Alias\CreationAliasRegistryTrait;
use DrevOps\BehatSteps\Driver\Alias\RolesAlias;
use DrevOps\BehatSteps\Driver\Capability\CreationAliasCapabilityInterface;
use DrevOps\BehatSteps\Driver\Drush\DrushResult;
use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Driver\Exception\BootstrapException;
use Drupal\Component\Utility\Random;
use Symfony\Component\Process\Process;

/**
 * Drives a Drupal site via the Drush CLI.
 */
class DrushDriver implements DrushDriverInterface, CreationAliasCapabilityInterface {

  use CreationAliasRegistryTrait;

  /**
   * Store a drush alias for tests requiring shell access.
   */
  public string $alias;

  /**
   * Stores the root path to a Drupal installation.
   *
   * This is an alternative to using drush aliases.
   */
  public string $root;

  /**
   * Store the path to drush binary.
   */
  public string $binary;

  /**
   * Track bootstrapping.
   */
  protected bool $bootstrapped = FALSE;

  /**
   * Random generator.
   */
  protected readonly Random $random;

  /**
   * Global arguments or options for drush commands.
   */
  protected string $arguments = '';

  /**
   * Set drush alias or root path.
   *
   * @param string $alias
   *   A drush alias.
   * @param string $root_path
   *   The root path of the Drupal install. This is an alternative to using
   *   aliases.
   * @param string $binary
   *   The path to the drush binary.
   * @param \Drupal\Component\Utility\Random $random
   *   Random generator.
   *
   * @throws \DrevOps\BehatSteps\Driver\Exception\BootstrapException
   *   Thrown when a required parameter is missing.
   */
  public function __construct(?string $alias = NULL, ?string $root_path = NULL, string $binary = 'drush', ?Random $random = NULL) {
    if (empty($alias) && empty($root_path)) {
      throw new BootstrapException('A drush alias or root path is required.');
    }

    if (!empty($alias)) {
      // Trim off the '@' symbol if it has been added.
      $this->alias = ltrim($alias, '@');
    }
    else {
      $resolved = realpath($root_path);

      if ($resolved === FALSE) {
        throw new BootstrapException(sprintf('No Drupal installation found at %s', $root_path));
      }

      $this->root = $resolved;
    }

    // When the default 'drush' binary is used, try to resolve the
    // project-level Drush binary first.
    if ($binary === 'drush') {
      $binary = $this->resolveProjectDrush($binary);
    }

    $this->binary = $binary;
    $this->random = $random ?? new Random();

    $this->registerDefaultCreationAliases();
  }

  /**
   * Populates the creation-alias registry with aliases this driver ships.
   *
   * A subclass that wants to add custom aliases should override this
   * method and call 'parent::registerDefaultCreationAliases()' first.
   */
  protected function registerDefaultCreationAliases(): void {
    $this->registerCreationAlias(new RolesAlias($this));
  }

  /**
   * {@inheritdoc}
   */
  public function getRandom(): Random {
    return $this->random;
  }

  /**
   * {@inheritdoc}
   */
  public function bootstrap(): void {
    $this->bootstrapped = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isBootstrapped(): bool {
    return $this->bootstrapped;
  }

  /**
   * {@inheritdoc}
   */
  public function processBatch(): void {
    // Do nothing. Drush should internally handle any needs for processing
    // batch ops.
  }

  /**
   * {@inheritdoc}
   */
  public function cacheClear(?string $type = 'all'): void {
    // Drush-only cache clear does not need a full rebuild.
    if ($type === 'drush') {
      $this->drush('cache-clear', ['drush'], []);
      return;
    }

    // Both 'all' and 'drush' clear the drush cache first.
    if ($type === 'all') {
      $this->drush('cache-clear', ['drush'], []);
    }

    $this->drush('cache:rebuild');
  }

  /**
   * {@inheritdoc}
   */
  public function cacheClearStatic(): void {
    // The drush driver does each operation as a separate request;
    // therefore, 'cacheClearStatic' can be a no-op.
  }

  /**
   * {@inheritdoc}
   */
  public function configGet(string $name, string $key = ''): mixed {
    $arguments = $key !== '' ? [$name, $key] : [$name];
    $output = trim($this->drush('config:get', $arguments, ['format' => 'json']));

    // 'drush config:get' returns whatever JSON shape the value has (object,
    // array, scalar). Decode objects to associative arrays so the return
    // shape matches 'Core::configGet()', which delegates to Drupal's config
    // API and hands back arrays.
    return json_decode($output, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function configGetOriginal(string $name, string $key = ''): mixed {
    // Drush persists every 'configSet' change to the active store; there is
    // no separate "original" layer to read, so this returns the same value
    // as 'configGet'.
    return $this->configGet($name, $key);
  }

  /**
   * {@inheritdoc}
   */
  public function configSet(string $name, string $key, mixed $value): void {
    $payload = json_encode($value);
    $this->drush('config:set', [$name, $key, (string) $payload], [
      'yes' => NULL,
      'input-format' => 'json',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function cronRun(): bool {
    $this->drush('cron');
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function moduleInstall(string $module_name): void {
    $this->drush('pm-enable', [$module_name], ['yes' => NULL]);
  }

  /**
   * {@inheritdoc}
   */
  public function moduleUninstall(string $module_name): void {
    $this->drush('pm-uninstall', [$module_name], ['yes' => NULL]);
  }

  /**
   * {@inheritdoc}
   */
  public function roleCreate(array $permissions, ?string $id = NULL, ?string $label = NULL): string {
    $random = $this->getRandom();
    $rid = $id ?? strtolower($random->name(8, TRUE));
    $role_label = $label ?? ($id ?? trim($random->name(8, TRUE)));

    $this->drush('role:create', [$rid, $role_label], []);

    foreach ($permissions as $permission) {
      $this->drush('role:perm:add', [$rid, $permission], []);
    }

    return $rid;
  }

  /**
   * {@inheritdoc}
   */
  public function roleDelete(string $role_name): void {
    $this->drush('role:delete', [$role_name], []);
  }

  /**
   * {@inheritdoc}
   */
  public function userCreate(EntityStubInterface $stub): void {
    $arguments = [(string) $stub->getValue('name')];
    $options = [
      'password' => (string) $stub->getValue('pass'),
      'mail' => (string) $stub->getValue('mail'),
    ];

    $result = $this->drush('user-create', $arguments, $options);
    $uid = $this->parseUserId($result);

    if (!$uid) {
      // Without an id the account cannot be referenced again, so post-create
      // aliases such as roles would silently never be applied.
      throw new \RuntimeException(sprintf("Drush did not report a user id after creating '%s'. Output: %s", $stub->getValue('name'), $result));
    }

    $stub->setValue('uid', $uid);

    $account = new \stdClass();
    $account->uid = $uid;

    $this->applyPostCreateAliases($stub, $account, 'user');
  }

  /**
   * {@inheritdoc}
   */
  public function userDelete(EntityStubInterface $stub): void {
    $arguments = [(string) $stub->getValue('name')];
    $options = [
      'yes' => NULL,
      'delete-content' => NULL,
    ];
    $this->drush('user-cancel', $arguments, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function userAddRole(EntityStubInterface $stub, string $role): void {
    $arguments = [
      $role,
      (string) $stub->getValue('name'),
    ];
    $this->drush('user-add-role', $arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function watchdogFetch(int $count = 10, ?string $type = NULL, ?string $severity = NULL): string {
    // parseArguments() maps NULL values to bare --flag, so only include
    // filters that have been explicitly set.
    $options = ['count' => (string) $count];

    if ($type !== NULL) {
      $options['type'] = $type;
    }

    if ($severity !== NULL) {
      $options['severity'] = $severity;
    }

    return $this->drush('watchdog-show', [], $options);
  }

  /**
   * Sets common drush arguments or options.
   *
   * @param string $arguments
   *   Global arguments to add to every drush command.
   */
  public function setArguments(string $arguments): void {
    $this->arguments = $arguments;
  }

  /**
   * Get common drush arguments.
   */
  public function getArguments(): string {
    return $this->arguments;
  }

  /**
   * Splits the common drush arguments into individual argv entries.
   *
   * @return array<int, string>
   *   One entry per whitespace-separated argument, empty when none are set.
   */
  protected function getArgumentList(): array {
    $arguments = trim($this->arguments);

    if ($arguments === '') {
      return [];
    }

    return explode(' ', (string) preg_replace('/\s+/', ' ', $arguments));
  }

  /**
   * Execute a drush command, returning its result without throwing on failure.
   *
   * The command runs without a shell, so no value passed here is subject to
   * shell interpretation.
   *
   * @param string $command
   *   The Drush command to execute.
   * @param array<int, string> $arguments
   *   Positional arguments to pass to Drush.
   * @param array<string, string|bool|null> $options
   *   Options to pass to Drush.
   *
   * @return \DrevOps\BehatSteps\Driver\Drush\DrushResult
   *   The exit code together with the captured stdout and stderr.
   */
  public function drushResult(string $command, array $arguments = [], array $options = []): DrushResult {
    $options['no-ansi'] = NULL;

    $argv = [
      $this->binary,
      isset($this->alias) ? '@' . $this->alias : '--root=' . $this->root,
      ...static::parseArguments($options),
      ...$this->getArgumentList(),
      $command,
      ...$arguments,
    ];

    $process = $this->runProcess($argv);

    // A signalled process yields a NULL exit code; classify that as a failure
    // rather than letting it read as success.
    return new DrushResult($process->getExitCode() ?? 1, $process->getOutput(), $process->getErrorOutput());
  }

  /**
   * Execute a drush command.
   *
   * @param string $command
   *   The Drush command to execute.
   * @param array<int, string> $arguments
   *   Positional arguments to pass to Drush.
   * @param array<string, string|bool|null> $options
   *   Options to pass to Drush.
   *
   * @return string
   *   The command's stdout, or its stderr when stdout is empty.
   *
   * @throws \RuntimeException
   *   When the command exits with a non-zero status.
   */
  public function drush(string $command, array $arguments = [], array $options = []): string {
    $result = $this->drushResult($command, $arguments, $options);

    if ($result->exitCode !== 0) {
      throw new \RuntimeException(sprintf("Drush command '%s' exited with code %d. %s", $command, $result->exitCode, $result->errorOutput));
    }

    // Some Drush commands write to stderr instead of stdout.
    if ($result->output === '' || $result->output === '0') {
      return $result->errorOutput;
    }

    return $result->output;
  }

  /**
   * Run Drush commands dynamically from a DrupalContext.
   *
   * @param string $name
   *   The method name, used as a Drush command.
   * @param array<int, string> $arguments
   *   The method arguments, forwarded to Drush.
   *
   * @return string
   *   The Drush command output.
   */
  public function __call(string $name, array $arguments): string {
    return $this->drush($name, $arguments);
  }

  /**
   * Builds, runs, and returns a process for the given argument vector.
   *
   * @param array<int, string> $argv
   *   The binary followed by its arguments, one entry each.
   *
   * @return \Symfony\Component\Process\Process
   *   The process after it has finished running.
   */
  protected function runProcess(array $argv): Process {
    $process = new Process($argv);
    $process->setTimeout(3600);
    $process->run();

    return $process;
  }

  /**
   * Resolves the project-level Drush binary path.
   *
   * @param string $fallback
   *   The fallback binary path if project-level Drush is not found.
   *
   * @return string
   *   The resolved binary path.
   */
  protected function resolveProjectDrush(string $fallback): string {
    // Try Composer's runtime bin directory.
    $composer_bin = getenv('COMPOSER_BIN_DIR');
    if ($composer_bin && file_exists($composer_bin . '/drush')) {
      return $composer_bin . '/drush';
    }

    // Try common vendor/bin location relative to working directory.
    $cwd = getcwd();
    if ($cwd && file_exists($cwd . '/vendor/bin/drush')) {
      return $cwd . '/vendor/bin/drush';
    }

    return $fallback;
  }

  /**
   * Parse user id from drush user-information output.
   *
   * Supports both the legacy key-value format ("User ID : 123") and the
   * Drush 12+ table format where the ID is the first numeric value in the
   * data row.
   */
  protected function parseUserId(string $info): ?int {
    // Legacy format: "User ID : 123".
    if (preg_match('/User ID\s+:\s+(\d+)/', $info, $matches)) {
      return (int) $matches[1];
    }

    // Drush 12+ table format: extract the first numeric value from the first
    // data row (the row after the header separator).
    if (preg_match('/User ID/', $info)) {
      $lines = explode("\n", trim($info));

      foreach ($lines as $line) {
        // Skip header, separator, and empty lines.
        $trimmed = trim($line, " \t\n\r\0\x0B-");

        if ($trimmed === '') {
          continue;
        }

        if (str_contains($trimmed, 'User ID')) {
          continue;
        }

        // The first column in the data row is the User ID.
        if (preg_match('/^\s*(\d+)\s/', $line, $matches)) {
          return (int) $matches[1];
        }
      }
    }

    return NULL;
  }

  /**
   * Parse options into individual argv entries.
   *
   * @param array<string, string|bool|null> $arguments
   *   An array of option names to values. A NULL value yields a bare flag.
   *
   * @return array<int, string>
   *   One entry per option.
   *
   * @throws \RuntimeException
   *   Thrown when an option name is not a bare long-option name.
   */
  protected static function parseArguments(array $arguments): array {
    $options = [];

    foreach ($arguments as $name => $value) {
      if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name) !== 1) {
        throw new \RuntimeException(sprintf('Invalid Drush option name: %s.', $name));
      }

      $options[] = $value === NULL ? '--' . $name : '--' . $name . '=' . $value;
    }

    return $options;
  }

}
