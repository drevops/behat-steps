<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend;

use DrevOps\BehatSteps\Backend\Alias\CreationAliasRegistryTrait;
use DrevOps\BehatSteps\Backend\Alias\RolesAlias;
use DrevOps\BehatSteps\Backend\Capability\CreationAliasCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drush\DrushResult;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Backend\Exception\BootstrapException;
use Drupal\Component\Utility\Random;
use Symfony\Component\Process\Process;

/**
 * Drives a Drupal site via the Drush CLI.
 */
class DrushBackend implements DrushBackendInterface, CreationAliasCapabilityInterface {

  use CreationAliasRegistryTrait;

  /**
   * The drush alias.
   */
  public string $alias;

  /**
   * Stores the root path to a Drupal installation.
   *
   * This is an alternative to using drush aliases.
   */
  public string $root;

  /**
   * The path to the drush binary.
   */
  public string $binary;

  /**
   * Whether the backend has been bootstrapped.
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
   * Sets the drush alias or root path.
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
   * @throws \DrevOps\BehatSteps\Backend\Exception\BootstrapException
   *   Thrown when a required parameter is missing.
   */
  public function __construct(?string $alias = NULL, ?string $root_path = NULL, string $binary = 'drush', ?Random $random = NULL) {
    if (($alias === NULL || $alias === '') && ($root_path === NULL || $root_path === '')) {
      throw new BootstrapException('A drush alias or root path is required.');
    }

    if ($alias !== NULL && $alias !== '') {
      $this->alias = ltrim($alias, '@');
    }
    else {
      $resolved = realpath($root_path);

      if ($resolved === FALSE) {
        throw new BootstrapException(sprintf('No Drupal installation found at %s.', $root_path));
      }

      $this->root = $resolved;
    }

    if ($binary === 'drush') {
      $binary = $this->resolveProjectDrush($binary);
    }

    $this->binary = $binary;
    $this->random = $random ?? new Random();

    $this->registerDefaultCreationAliases();
  }

  /**
   * Populates the creation-alias registry with the backend's default aliases.
   *
   * A subclass that adds custom aliases should override this method and
   * call 'parent::registerDefaultCreationAliases()' first.
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
    // Drush is expected to handle batch processing internally.
  }

  /**
   * {@inheritdoc}
   */
  public function cacheClear(?string $type = 'all'): void {
    if ($type === 'drush') {
      $this->drush('cache-clear', ['drush'], []);
      return;
    }

    if ($type === 'all') {
      $this->drush('cache-clear', ['drush'], []);
    }

    $this->drush('cache:rebuild');
  }

  /**
   * {@inheritdoc}
   */
  public function cacheClearStatic(): void {
    // The drush backend does each operation as a separate request;
    // therefore, 'cacheClearStatic' can be a no-op.
  }

  /**
   * {@inheritdoc}
   */
  public function configGet(string $name, string $key = ''): mixed {
    return $this->configRead($name, $key, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function configGetOriginal(string $name, string $key = ''): mixed {
    return $this->configRead($name, $key, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function configSet(string $name, string $key, mixed $value): void {
    $this->drush('config:set', [$name, $key, (string) json_encode($value)], [
      'yes' => NULL,
      'input-format' => 'yaml',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function configExists(string $name): bool {
    // 'config:get' exits non-zero for an object that does not exist, so the
    // exit code indicates existence without a second command.
    return $this->drushResult('config:get', [$name], ['format' => 'json'])->exitCode === 0;
  }

  /**
   * {@inheritdoc}
   */
  public function configGetData(string $name): array {
    $data = $this->configRead($name, '', FALSE);

    return is_array($data) ? $data : [];
  }

  /**
   * {@inheritdoc}
   */
  public function configSetData(string $name, array $data): void {
    // Drush exposes no whole-object replace and 'config:set' assigns only the
    // given keys, so the object is deleted first to drop its other keys. The
    // delete and the write are 2 commands, so the previous data is read first
    // and restored when the write fails.
    $previous = $this->configGetData($name);

    // An empty object has no keys to drop. 'config:set' rejects an empty
    // object, so deleting one would leave nothing to restore when the write
    // fails.
    if ($previous !== []) {
      $this->configDelete($name);
    }

    if ($data === []) {
      return;
    }

    try {
      $this->configWriteData($name, $data);
    }
    catch (\RuntimeException $e) {
      try {
        if ($previous !== []) {
          $this->configWriteData($name, $previous);
        }
      }
      // @codeCoverageIgnoreStart
      catch (\RuntimeException) {
        // Restoring failed as well. The write failure below is the error
        // reported, so the restore failure is discarded.
      }
      // @codeCoverageIgnoreEnd
      throw $e;
    }
  }

  /**
   * Assigns every key of a configuration object in 1 command.
   *
   * @param string $name
   *   The configuration object name.
   * @param array<int|string, mixed> $data
   *   The data to assign.
   *
   * @throws \RuntimeException
   *   When the command exits with a non-zero status.
   */
  protected function configWriteData(string $name, array $data): void {
    $this->drush('config:set', [$name, '?', (string) json_encode($data)], [
      'yes' => NULL,
      'input-format' => 'yaml',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function configDelete(string $name): void {
    // 'config:delete' fails on an object that does not exist, while Drupal's
    // own config API treats deleting one as a no-op.
    if (!$this->configExists($name)) {
      return;
    }

    $this->drush('config:delete', [$name], ['yes' => NULL]);
  }

  /**
   * Reads a configuration value through 'drush config:get'.
   *
   * @param string $name
   *   The configuration object name.
   * @param string $key
   *   The key within the object. Empty for the whole object.
   * @param bool $with_overrides
   *   Whether module and 'settings.php' overrides are applied. Without them
   *   the read returns the stored value, which is what a write replaces.
   *
   * @return mixed
   *   The value, or NULL when the object or key does not exist.
   */
  protected function configRead(string $name, string $key, bool $with_overrides): mixed {
    $options = ['format' => 'json'];

    if ($with_overrides) {
      $options['include-overridden'] = NULL;
    }

    $arguments = $key !== '' ? [$name, $key] : [$name];
    $result = $this->drushResult('config:get', $arguments, $options);

    // A missing object is an error to Drush but an absent value to Drupal's
    // config API, and the capability contract matches the API.
    if ($result->exitCode !== 0) {
      return NULL;
    }

    $decoded = json_decode(trim($result->output), TRUE);
    $envelope_key = $name . ':' . $key;

    // For a single key, 'config:get' returns a 1-entry map keyed
    // '<name>:<key>' instead of the bare value.
    if ($key !== '' && is_array($decoded) && array_key_exists($envelope_key, $decoded)) {
      return $decoded[$envelope_key];
    }

    return $decoded;
  }

  /**
   * {@inheritdoc}
   */
  public function stateGet(string $name): mixed {
    $result = $this->drushResult('state:get', [$name], ['format' => 'json']);

    if ($result->exitCode !== 0) {
      return NULL;
    }

    $decoded = json_decode(trim($result->output), TRUE);

    // 'state:get' returns a 1-entry map keyed by the state key.
    if (is_array($decoded) && array_key_exists($name, $decoded)) {
      return $decoded[$name];
    }

    return $decoded;
  }

  /**
   * {@inheritdoc}
   */
  public function stateSet(string $name, mixed $value): void {
    $this->drush('state:set', [$name, (string) json_encode($value)], [
      'yes' => NULL,
      'input-format' => 'json',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function stateDelete(string $name): void {
    $this->drush('state:delete', [$name], ['yes' => NULL]);
  }

  /**
   * {@inheritdoc}
   *
   * Drush exposes no existence check over the state key-value store. A key
   * holding NULL therefore reads the same as an absent one on this backend.
   */
  public function stateExists(string $name): bool {
    return $this->stateGet($name) !== NULL;
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
  public function moduleIsEnabled(string $module_name): bool {
    return $this->moduleIsListed($module_name, 'enabled');
  }

  /**
   * {@inheritdoc}
   */
  public function moduleIsPresent(string $module_name): bool {
    return $this->moduleIsListed($module_name, 'enabled,disabled');
  }

  /**
   * Whether 'drush pm:list' reports a module under any of the given statuses.
   *
   * @param string $module_name
   *   The module machine name.
   * @param string $status
   *   Comma-separated statuses to restrict the listing to.
   */
  protected function moduleIsListed(string $module_name, string $status): bool {
    $result = $this->drushResult('pm:list', [], [
      'format' => 'json',
      'type' => 'module',
      'status' => $status,
      // The filter matches any substring of a name, so it only narrows the
      // listing; the exact machine name is looked up in the result.
      'filter' => $module_name,
    ]);

    if ($result->exitCode !== 0) {
      return FALSE;
    }

    $modules = json_decode(trim($result->output), TRUE);

    return is_array($modules) && array_key_exists($module_name, $modules);
  }

  /**
   * {@inheritdoc}
   */
  public function createRole(array $permissions, ?string $id = NULL, ?string $label = NULL): EntityStubInterface {
    $random = $this->getRandom();
    $rid = $id ?? strtolower($random->name(8, TRUE));
    $role_label = $label ?? ($id ?? trim($random->name(8, TRUE)));

    $this->drush('role:create', [$rid, $role_label], []);

    foreach ($permissions as $permission) {
      $this->drush('role:perm:add', [$rid, $permission], []);
    }

    return new EntityStub('user_role', NULL, ['id' => $rid, 'label' => $role_label]);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRole(string $role_name): void {
    $this->drushDelete('role:delete', [$role_name], [], fn(): bool => $this->configExists('user.role.' . $role_name));
  }

  /**
   * {@inheritdoc}
   */
  public function createUser(EntityStubInterface $stub): EntityStubInterface {
    $arguments = [(string) $stub->getValue('name')];
    $options = [
      'password' => (string) $stub->getValue('pass'),
      'mail' => (string) $stub->getValue('mail'),
    ];

    $result = $this->drush('user-create', $arguments, $options);
    $uid = $this->parseUserId($result);

    if (!$uid) {
      throw new \RuntimeException(sprintf('Drush did not report a user id after creating "%s". Output: %s', $stub->getValue('name'), $result));
    }

    $stub->setValue('uid', $uid);

    $account = new \stdClass();
    $account->uid = $uid;

    $this->applyPostCreateAliases($stub, $account, 'user');

    return $stub;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteUser(EntityStubInterface $stub): void {
    $name = (string) $stub->getValue('name');
    $uid = (string) $stub->getValue('uid');

    if ($name === '' && $uid === '') {
      throw new \RuntimeException('Cannot delete a user from a stub without a "name" or "uid" value.');
    }

    $arguments = $name !== '' ? [$name] : [];
    $lookup = $name !== '' ? [] : ['uid' => $uid];

    $this->drushDelete('user-cancel', $arguments, ['yes' => NULL, 'delete-content' => NULL] + $lookup, fn(): bool => $this->drushResult('user:information', $arguments, $lookup)->exitCode === 0);
  }

  /**
   * Runs a Drush command that deletes a target, tolerating a missing target.
   *
   * A Drush delete command exits non-zero when its target does not exist,
   * while the capability contract treats deleting a missing target as a
   * no-op. The existence check runs only after a failure, so a successful
   * delete costs 1 Drush call.
   *
   * @param string $command
   *   The Drush command that deletes the target.
   * @param array<int, string> $arguments
   *   Positional arguments to pass to Drush.
   * @param array<string, string|bool|null> $options
   *   Options to pass to Drush.
   * @param \Closure(): bool $exists
   *   Reports whether the target still exists.
   *
   * @throws \RuntimeException
   *   When the command fails and the target still exists.
   */
  protected function drushDelete(string $command, array $arguments, array $options, \Closure $exists): void {
    try {
      $this->drush($command, $arguments, $options);
    }
    catch (\RuntimeException $exception) {
      if ($exists()) {
        throw $exception;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addUserRole(EntityStubInterface $stub, string $role): void {
    $arguments = [
      $role,
      (string) $stub->getValue('name'),
    ];
    $this->drush('user-add-role', $arguments);
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
   * Gets common drush arguments.
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
   * Executes a drush command, returning its result without throwing on failure.
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
   * @return \DrevOps\BehatSteps\Backend\Drush\DrushResult
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

    // A signalled process yields a NULL exit code, so it is classified as a
    // failure.
    return new DrushResult($process->getExitCode() ?? 1, $process->getOutput(), $process->getErrorOutput());
  }

  /**
   * Executes a drush command.
   *
   * @param string $command
   *   The Drush command to execute.
   * @param array<int, string> $arguments
   *   Positional arguments to pass to Drush.
   * @param array<string, string|bool|null> $options
   *   Options to pass to Drush.
   *
   * @return string
   *   The command's stdout, or its stderr when stdout is empty or '0'.
   *
   * @throws \RuntimeException
   *   When the command exits with a non-zero status.
   */
  public function drush(string $command, array $arguments = [], array $options = []): string {
    $result = $this->drushResult($command, $arguments, $options);

    if ($result->exitCode !== 0) {
      throw new \RuntimeException(sprintf('Drush command "%s" exited with code %d. %s', $command, $result->exitCode, $result->errorOutput));
    }

    // Some Drush commands write to stderr instead of stdout.
    if ($result->output === '' || $result->output === '0') {
      return $result->errorOutput;
    }

    return $result->output;
  }

  /**
   * Runs an undefined method call as a Drush command.
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
    $composer_bin = getenv('COMPOSER_BIN_DIR');
    if ($composer_bin && file_exists($composer_bin . '/drush')) {
      return $composer_bin . '/drush';
    }

    $cwd = getcwd();
    if ($cwd && file_exists($cwd . '/vendor/bin/drush')) {
      return $cwd . '/vendor/bin/drush';
    }

    return $fallback;
  }

  /**
   * Parses the user id from drush user-information output.
   *
   * Supports both the legacy key-value format ("User ID : 123") and the
   * Drush 12+ table format where the ID is the first numeric value in the
   * data row.
   */
  protected function parseUserId(string $info): ?int {
    if (preg_match('/User ID\s+:\s+(\d+)/', $info, $matches) === 1) {
      return (int) $matches[1];
    }

    if (preg_match('/User ID/', $info) === 1) {
      $lines = explode("\n", trim($info));

      foreach ($lines as $line) {
        $trimmed = trim($line, " \t\n\r\0\x0B-");

        if ($trimmed === '') {
          continue;
        }

        if (str_contains($trimmed, 'User ID')) {
          continue;
        }

        if (preg_match('/^\s*(\d+)\s/', $line, $matches) === 1) {
          return (int) $matches[1];
        }
      }
    }

    return NULL;
  }

  /**
   * Parses options into individual argv entries.
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
