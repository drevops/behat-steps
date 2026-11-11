<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Drush;

use DrevOps\BehatSteps\Backend\Alias\CreationAliasRegistryTrait;
use DrevOps\BehatSteps\Backend\Alias\RolesAlias;
use DrevOps\BehatSteps\Backend\Drupal\Capability\DrushCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException;
use DrevOps\BehatSteps\Backend\Experimental\ExperimentalAwareTrait;
use DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface;

/**
 * The Drush commands of Drush 13, and the base of every later major's.
 *
 * A later major's commands extend this class to change what its Drush major
 * changed, and are named by 'DrushBackend::VERSION_COMMANDS_CLASS'.
 */
class Commands implements CommandsInterface {

  use CreationAliasRegistryTrait;
  use ExperimentalAwareTrait;

  /**
   * The most log entries 1 read asks Drush for.
   */
  protected const int LOG_READ_COUNT = 100000;

  /**
   * The level of each severity label 'watchdog:show' prints.
   *
   * @var array<string, int>
   */
  protected const array LOG_SEVERITIES = [
    'emergency' => self::SEVERITY_EMERGENCY,
    'alert' => self::SEVERITY_ALERT,
    'critical' => self::SEVERITY_CRITICAL,
    'error' => self::SEVERITY_ERROR,
    'warning' => self::SEVERITY_WARNING,
    'notice' => self::SEVERITY_NOTICE,
    'info' => self::SEVERITY_INFO,
    'debug' => self::SEVERITY_DEBUG,
  ];

  /**
   * Sets the runner and the random generator, and registers default aliases.
   *
   * @param \DrevOps\BehatSteps\Backend\Drupal\Capability\DrushCapabilityInterface $runner
   *   Runs each Drush command.
   * @param \DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface $random
   *   The random generator role names and labels are drawn from.
   */
  public function __construct(protected readonly DrushCapabilityInterface $runner, protected readonly RandomGeneratorInterface $random) {
    $this->registerDefaultCreationAliases();
  }

  /**
   * Populates the creation-alias registry with the default aliases.
   */
  protected function registerDefaultCreationAliases(): void {
    $this->registerCreationAlias(new RolesAlias($this));
  }

  /**
   * {@inheritdoc}
   */
  public function cacheClear(): void {
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
  public function configGet(string $name, ?string $key = NULL): mixed {
    return $this->configRead($name, $key, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function configGetOriginal(string $name, ?string $key = NULL): mixed {
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
    $data = $this->configRead($name, NULL, FALSE);

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
    $original = $this->configGetData($name);

    // An empty object has no keys to drop. 'config:set' rejects an empty
    // object, so deleting one would leave nothing to restore when the write
    // fails.
    if ($original !== []) {
      $this->configDelete($name);
    }

    if ($data === []) {
      return;
    }

    try {
      $this->configWriteData($name, $data);
    }
    catch (\RuntimeException $exception) {
      try {
        if ($original !== []) {
          $this->configWriteData($name, $original);
        }
      }
      // @codeCoverageIgnoreStart
      catch (\RuntimeException) {
        // Restoring failed as well. The write failure below is the error
        // reported, so the restore failure is discarded.
      }

      // @codeCoverageIgnoreEnd
      throw $exception;
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
   * @param string|null $key
   *   The key within the object, or NULL for the whole object.
   * @param bool $with_overrides
   *   Whether module and 'settings.php' overrides are applied. Without them
   *   the read returns the stored value a write replaces.
   *
   * @return mixed
   *   The value, or NULL when the object or key does not exist.
   */
  protected function configRead(string $name, ?string $key, bool $with_overrides): mixed {
    $options = ['format' => 'json'];

    if ($with_overrides) {
      $options['include-overridden'] = NULL;
    }

    $arguments = $key !== NULL ? [$name, $key] : [$name];
    $result = $this->drushResult('config:get', $arguments, $options);

    if ($result->exitCode !== 0) {
      return NULL;
    }

    $decoded = json_decode(trim($result->output), TRUE);
    $envelope_key = $name . ':' . $key;

    // For a single key, 'config:get' returns a 1-entry map keyed
    // '<name>:<key>' instead of the bare value.
    if ($key !== NULL && is_array($decoded) && array_key_exists($envelope_key, $decoded)) {
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
    $this->drush('core:cron');
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function moduleInstall(string $module_name): void {
    $this->drush('pm:install', [$module_name], ['yes' => NULL]);
  }

  /**
   * {@inheritdoc}
   */
  public function moduleUninstall(string $module_name): void {
    $this->drush('pm:uninstall', [$module_name], ['yes' => NULL]);
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
    $rid = $id ?? strtolower($this->random->name(8, TRUE));
    $role_label = $label ?? ($id ?? trim($this->random->name(8, TRUE)));

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
   *
   * 'user:create' takes only a name, a password and an email. A 'status' of
   * 0 runs 'user:block' after it, and a value named after a registered user
   * alias is left to the alias. Any other value is rejected before an
   * account is created.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException
   *   When the stub carries a value no Drush command sets.
   * @throws \RuntimeException
   *   When the stub carries a 'uid' value, or Drush reports no user id.
   */
  public function createUser(EntityStubInterface $stub): EntityStubInterface {
    $this->applyPreCreateAliases($stub, 'user');

    if ($stub->hasValue('uid')) {
      throw new \RuntimeException(sprintf('Cannot create the user "%s" with a "uid" value: "drush user:create" assigns the id.', $stub->getValue('name')));
    }

    $unsupported = array_keys(array_diff_key($stub->getValues(), array_flip(['name', 'pass', 'mail']), $this->getCreationAliases('user')));
    $status = $stub->getValue('status');

    // 'user:create' makes an active account, and 'user:block' is the only
    // command that changes it.
    if (in_array($status, [0, 1, '0', '1', FALSE, TRUE], TRUE)) {
      $unsupported = array_values(array_diff($unsupported, ['status']));
    }

    if ($unsupported !== []) {
      throw new UnsupportedBackendActionException(sprintf('The Drush backend cannot set "%s" on a user: "drush user:create" takes only a name, a password and an email, and "status" only as 0 or 1. Remove the values, or run the scenario on the "drupal" backend.', implode('", "', $unsupported)));
    }

    $arguments = [(string) $stub->getValue('name')];
    $options = [
      'password' => (string) $stub->getValue('pass'),
      'mail' => (string) $stub->getValue('mail'),
    ];

    $result = $this->drush('user:create', $arguments, $options);
    $uid = $this->parseUserId($result);

    if (!$uid) {
      throw new \RuntimeException(sprintf('Drush did not report a user id after creating "%s". Output: %s', $stub->getValue('name'), $result));
    }

    $stub->setValue('uid', $uid);

    if (in_array($status, [0, '0', FALSE], TRUE)) {
      $this->blockCreatedUser($stub);
    }

    // The stub stays unsaved, because Drush runs in another process and
    // returns no account object. The placeholder carries only the id the
    // post-create aliases read.
    $account = new \stdClass();
    $account->uid = $uid;

    $this->applyPostCreateAliases($stub, $account, 'user');

    return $stub;
  }

  /**
   * Blocks a user 'createUser()' has just created.
   *
   * A failed create returns no stub, so the account is deleted here when the
   * block fails.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub of the new user, carrying its 'name' and 'uid' values.
   *
   * @throws \RuntimeException
   *   When 'user:block' fails. The message names the delete failure as well
   *   when the account could not be deleted.
   */
  protected function blockCreatedUser(EntityStubInterface $stub): void {
    try {
      $this->drush('user:block', [(string) $stub->getValue('name')]);
    }
    catch (\RuntimeException $exception) {
      try {
        $this->deleteUser($stub);
      }
      catch (\RuntimeException $cleanup_exception) {
        throw new \RuntimeException(sprintf('Drush could not block the user "%s", and could not delete it either. %s %s', $stub->getValue('name'), $exception->getMessage(), $cleanup_exception->getMessage()), 0, $exception);
      }

      throw $exception;
    }
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

    $this->cancelUser($stub, $arguments, $lookup);
  }

  /**
   * Cancels a user account and deletes its content.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The user stub, carrying a 'name' or a 'uid' value.
   * @param array<int, string> $arguments
   *   The name 'user:cancel' takes as its argument, or none.
   * @param array<string, string> $lookup
   *   The 'uid' option 'user:cancel' takes when no name is given, or none.
   *
   * @throws \RuntimeException
   *   When the command fails and the user still exists.
   */
  protected function cancelUser(EntityStubInterface $stub, array $arguments, array $lookup): void {
    $this->drushDelete('user:cancel', $arguments, ['yes' => NULL, 'delete-content' => NULL] + $lookup, fn(): bool => $this->drushResult('user:information', $arguments, $lookup)->exitCode === 0);
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
      $this->resolveRoleId($role),
      (string) $stub->getValue('name'),
    ];
    $this->drush('user:role:add', $arguments);
  }

  /**
   * Resolves a role machine name or label to the role's machine name.
   *
   * 'user:role:add' takes only a machine name, so a label is looked up in the
   * roles 'role:list' reports. Both match regardless of case, and a machine
   * name matches before a label.
   *
   * @param string $role
   *   The role machine name or label.
   *
   * @return string
   *   The role machine name.
   *
   * @throws \RuntimeException
   *   When no role has the machine name or the label.
   */
  protected function resolveRoleId(string $role): string {
    $roles = json_decode(trim($this->drush('role:list', [], ['format' => 'json'])), TRUE);
    $roles = is_array($roles) ? $roles : [];
    $needle = mb_strtolower($role);

    foreach (array_keys($roles) as $rid) {
      if (mb_strtolower((string) $rid) === $needle) {
        return (string) $rid;
      }
    }

    foreach ($roles as $rid => $row) {
      $label = is_array($row) ? ($row['label'] ?? NULL) : NULL;

      if (is_string($label) && mb_strtolower($label) === $needle) {
        return (string) $rid;
      }
    }

    throw new \RuntimeException(sprintf('No role "%s" exists.', $role));
  }

  /**
   * {@inheritdoc}
   *
   * 'user:login' prints the link that logs the user in without a click, so
   * its trailing '/login' segment is dropped to reach the one-time login page.
   * A stub without an id is resolved by its 'name' value.
   */
  public function getOneTimeLoginUrl(EntityStubInterface $stub): string {
    return $this->readOneTimeLoginPath($stub);
  }

  /**
   * {@inheritdoc}
   *
   * A stub without an id is resolved by its 'name' value.
   */
  public function getOneTimeLoginDirectUrl(EntityStubInterface $stub): string {
    return $this->readOneTimeLoginPath($stub) . '/login';
  }

  /**
   * Reads the path of a user's one-time login page from 'user:login'.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The user stub.
   *
   * @return string
   *   The path of the link Drush prints, without a trailing '/login' segment.
   *
   * @throws \RuntimeException
   *   When Drush prints no one-time login link.
   */
  protected function readOneTimeLoginPath(EntityStubInterface $stub): string {
    $link = trim($this->drush('user:login', [], $this->getOneTimeLoginOptions($stub)));
    $path = parse_url($link, PHP_URL_PATH);

    if (!is_string($path) || preg_match('#^(.*/user/reset/[^/]+/[^/]+/[^/]+)(?:/login)?$#', $path, $matches) !== 1) {
      throw new \RuntimeException(sprintf('Drush did not print a one-time login link. Output: %s', $link));
    }

    return $matches[1];
  }

  /**
   * Returns the 'user:login' options that select a user and print the link.
   *
   * Drush 13 opens the link in a browser unless told not to.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The user stub.
   *
   * @return array<string, string|null>
   *   The options keyed by name.
   */
  protected function getOneTimeLoginOptions(EntityStubInterface $stub): array {
    [$key, $value] = $this->resolveUserIdentifier($stub);

    return [$key => $value, 'no-browser' => NULL];
  }

  /**
   * {@inheritdoc}
   *
   * A stub without an id is resolved by its 'name' value.
   */
  public function getUserRoles(EntityStubInterface $stub): array {
    $roles = $this->readUserInformation($stub)['roles'] ?? [];

    return array_values(array_map(strval(...), is_array($roles) ? $roles : []));
  }

  /**
   * {@inheritdoc}
   *
   * 'user:password' takes only a name, so a stub without a 'name' value is
   * looked up by its id first. An account without a name, such as the
   * anonymous one, is rejected.
   */
  public function setUserPassword(EntityStubInterface $stub, string $password): void {
    $name = $stub->getValue('name');

    if (!is_string($name) || $name === '') {
      $name = (string) ($this->readUserInformation($stub)['name'] ?? '');
    }

    if ($name === '') {
      throw new \RuntimeException(sprintf('Cannot set the password of the user "%s": Drush printed no name for the account.', $this->resolveUserIdentifier($stub)[1]));
    }

    $this->drush('user:password', [$name, $password]);
  }

  /**
   * Reads the account a user stub stands for through 'user:information'.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The user stub.
   *
   * @return array<array-key, mixed>
   *   The fields 'user:information' prints for the account.
   *
   * @throws \RuntimeException
   *   When the stub carries neither an id nor a name, no user has it, or Drush
   *   prints no account.
   */
  protected function readUserInformation(EntityStubInterface $stub): array {
    [$key, $value] = $this->resolveUserIdentifier($stub);

    // The command takes names as its argument and ids as an option.
    $arguments = $key === 'name' ? [$value] : [];
    $options = $key === 'uid' ? ['uid' => $value, 'format' => 'json'] : ['format' => 'json'];

    $output = trim($this->drush('user:information', $arguments, $options));
    $accounts = json_decode($output, TRUE);
    $account = is_array($accounts) ? reset($accounts) : FALSE;

    if (!is_array($account)) {
      throw new \RuntimeException(sprintf('Drush did not print the account of the user "%s". Output: %s', $value, $output));
    }

    return $account;
  }

  /**
   * Resolves what Drush identifies the account of a user stub by.
   *
   * The id is read first, from the saved entity or the 'uid' value, and the
   * 'name' value is the fallback.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The user stub.
   *
   * @return array{0: 'uid'|'name', 1: string}
   *   The kind of identifier and its value.
   *
   * @throws \RuntimeException
   *   When the stub carries neither an id nor a name.
   */
  protected function resolveUserIdentifier(EntityStubInterface $stub): array {
    $uid = $stub->getId() ?? $stub->getValue('uid');

    if ($uid !== NULL && $uid !== '') {
      return ['uid', (string) $uid];
    }

    $name = $stub->getValue('name');

    if (is_string($name) && $name !== '') {
      return ['name', $name];
    }

    throw new \RuntimeException('Cannot resolve a user from the stub: neither the saved entity, a "uid" value nor a "name" value is set.');
  }

  /**
   * Parses the user id from drush 'user:create' output.
   *
   * Supports both the legacy key-value format ("User ID : 123") and the
   * Drush 12+ table format. In the table format, the ID is the first numeric
   * value in the data row.
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
   * {@inheritdoc}
   */
  public function cacheClearBin(string $bin): void {
    $this->drush('cache:clear', ['bin', $bin]);
  }

  /**
   * {@inheritdoc}
   */
  public function cacheInvalidateTags(array $tags): void {
    if ($tags === []) {
      return;
    }

    $this->drush('cache:tags', [implode(',', $tags)]);
  }

  /**
   * {@inheritdoc}
   *
   * Drush cannot list the entries of a bin, so the bin is emptied whole.
   */
  public function cacheDeletePagePath(string $bin, string $path, bool $is_pattern = FALSE): void {
    $this->cacheClearBin($bin);
  }

  /**
   * {@inheritdoc}
   *
   * 'queue:list' reports only the queues a worker processes, so a queue
   * without a worker reports 0 items.
   */
  public function queueCountItems(string $queue): int {
    $row = $this->listQueues()[$queue] ?? NULL;
    $items = is_array($row) ? $row['items'] ?? 0 : 0;

    return is_numeric($items) ? (int) $items : 0;
  }

  /**
   * {@inheritdoc}
   */
  public function queueDelete(string $queue): void {
    $this->drushDelete('queue:delete', [$queue], [], fn(): bool => array_key_exists($queue, $this->listQueues()));
  }

  /**
   * {@inheritdoc}
   */
  public function queueProcess(string $queue, int $limit, int $lease_time): int {
    if ($limit === 0) {
      return 0;
    }

    $options = ['items-limit' => (string) $limit, 'lease-time' => (string) $lease_time];
    $result = $this->drushChecked('queue:run', [$queue], $options);

    // The count is logged, so it lands on stderr.
    if (preg_match('/Processed (\d+) items? from the/', $result->output . $result->errorOutput, $matches) !== 1) {
      throw new \RuntimeException(sprintf('Drush did not report how many items of the queue "%s" it processed. Output: %s', $queue, $result->output . $result->errorOutput));
    }

    return (int) $matches[1];
  }

  /**
   * Lists the queues Drush reports, keyed by queue name.
   *
   * @return array<array-key, mixed>
   *   The rows of 'queue:list'.
   */
  protected function listQueues(): array {
    $queues = json_decode(trim($this->drush('queue:list', [], ['format' => 'json'])), TRUE);

    return is_array($queues) ? $queues : [];
  }

  /**
   * {@inheritdoc}
   */
  public function logGetLastId(): int {
    $entries = $this->readLogEntries(1);

    return $entries === [] ? 0 : max(array_keys($entries));
  }

  /**
   * {@inheritdoc}
   *
   * 'watchdog:show' renders each message with its placeholders, so an entry
   * carries the rendered message and no variables.
   */
  public function logRead(array $types, int $after_id, int $severity): array {
    if ($types === []) {
      return [];
    }

    $rows = $this->readLogEntries(static::LOG_READ_COUNT, $severity);

    // A full read whose oldest entry is still newer than the watermark has
    // left entries unread.
    if ($rows !== [] && count($rows) >= static::LOG_READ_COUNT && min(array_keys($rows)) > $after_id) {
      throw new \RuntimeException(sprintf('The log holds more than %d entries newer than the id %d, which is more than 1 read fetches.', static::LOG_READ_COUNT, $after_id));
    }

    $entries = [];

    foreach ($rows as $wid => $row) {
      if ($wid <= $after_id || !in_array($row['type'] ?? NULL, $types, TRUE)) {
        continue;
      }

      $entries[$wid] = $this->normalizeLogEntry($wid, $row);
    }

    ksort($entries);

    return array_values($entries);
  }

  /**
   * {@inheritdoc}
   */
  public function logDelete(array $ids): void {
    foreach ($ids as $id) {
      $this->drushDelete('watchdog:delete', [(string) $id], ['yes' => NULL], fn(): bool => $this->drushResult('watchdog:show-one', [(string) $id])->exitCode === 0);
    }
  }

  /**
   * Reads the newest log entries, keyed by id.
   *
   * @param int $count
   *   The most entries to read.
   * @param int|null $severity
   *   The least severe level to include, or NULL for every level.
   *
   * @return array<int, array<string, mixed>>
   *   The entries 'watchdog:show' prints, keyed by id.
   */
  protected function readLogEntries(int $count, ?int $severity = NULL): array {
    $options = ['format' => 'json', 'count' => (string) $count, 'extended' => NULL];

    if ($severity !== NULL) {
      $options['severity-min'] = (string) $severity;
    }

    $decoded = json_decode(trim($this->drush('watchdog:show', [], $options)), TRUE);
    $entries = [];

    foreach (is_array($decoded) ? $decoded : [] as $wid => $row) {
      if (is_numeric($wid) && is_array($row)) {
        $entries[(int) $wid] = $row;
      }
    }

    return $entries;
  }

  /**
   * Shapes a 'watchdog:show' row like a log entry.
   *
   * @param int $wid
   *   The entry id.
   * @param array<string, mixed> $row
   *   The row, with the severity as the label Drush prints.
   *
   * @return array<string, mixed>
   *   The entry, with the severity as a level where the label is known.
   */
  protected function normalizeLogEntry(int $wid, array $row): array {
    $severity = $row['severity'] ?? NULL;

    if (is_string($severity)) {
      $severity = static::LOG_SEVERITIES[strtolower($severity)] ?? $severity;
    }

    return ['wid' => $wid, 'severity' => $severity, 'variables' => []] + $row;
  }

  /**
   * {@inheritdoc}
   */
  public function searchIndexItems(int $limit): void {
    if ($limit === 0) {
      return;
    }

    $this->drush('search-api:index', [], ['limit' => (string) $limit]);
  }

  /**
   * Runs a Drush command through the runner.
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
  protected function drush(string $command, array $arguments = [], array $options = []): string {
    return $this->runner->drush($command, $arguments, $options);
  }

  /**
   * Runs a Drush command through the runner, returning its result.
   *
   * @param string $command
   *   The Drush command to execute.
   * @param array<int, string> $arguments
   *   Positional arguments to pass to Drush.
   * @param array<string, string|bool|null> $options
   *   Options to pass to Drush.
   *
   * @return \DrevOps\BehatSteps\Backend\Drupal\Drush\DrushResult
   *   The exit code together with the captured stdout and stderr.
   */
  protected function drushResult(string $command, array $arguments = [], array $options = []): DrushResult {
    return $this->runner->drushResult($command, $arguments, $options);
  }

  /**
   * Runs a Drush command through the runner, failing on a non-zero exit.
   *
   * @param string $command
   *   The Drush command to execute.
   * @param array<int, string> $arguments
   *   Positional arguments to pass to Drush.
   * @param array<string, string|bool|null> $options
   *   Options to pass to Drush.
   *
   * @return \DrevOps\BehatSteps\Backend\Drupal\Drush\DrushResult
   *   The exit code together with the captured stdout and stderr.
   *
   * @throws \RuntimeException
   *   When the command exits with a non-zero status.
   */
  protected function drushChecked(string $command, array $arguments = [], array $options = []): DrushResult {
    $result = $this->drushResult($command, $arguments, $options);

    if ($result->exitCode !== 0) {
      throw new \RuntimeException(sprintf('Drush command "%s" exited with code %d. %s', $command, $result->exitCode, $result->errorOutput));
    }

    return $result;
  }

}
