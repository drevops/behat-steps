<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal;

use DrevOps\BehatSteps\Backend\Alias\CreationAliasInterface;
use DrevOps\BehatSteps\Backend\Capability\CreationAliasCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Drush\Commands;
use DrevOps\BehatSteps\Backend\Drupal\Drush\CommandsInterface;
use DrevOps\BehatSteps\Backend\Drupal\Drush\DrushResult;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Backend\Exception\BootstrapException;
use DrevOps\BehatSteps\Backend\Experimental\ExperimentalAwareInterface;
use DrevOps\BehatSteps\Backend\Experimental\ExperimentalRegistryInterface;
use DrevOps\BehatSteps\Backend\Random\RandomGenerator;
use DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface;
use Symfony\Component\Process\Process;

/**
 * Drives a Drupal site via the Drush CLI.
 *
 * The backend runs each Drush command, and forwards every capability call to
 * the commands of the Drush major it detects.
 */
class DrushBackend implements DrushBackendInterface, CreationAliasCapabilityInterface, ExperimentalAwareInterface {

  /**
   * Lowest Drush major the backend supports.
   */
  protected const int MINIMUM_VERSION = 13;

  /**
   * Class name of the commands for a Drush major, as a sprintf() format.
   */
  protected const string VERSION_COMMANDS_CLASS = 'DrevOps\\BehatSteps\\Backend\\Drupal\\Drush%d\\Commands';

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
   * The major version of the Drush binary, read on first use.
   */
  protected ?int $drushMajorVersion = NULL;

  /**
   * The commands the backend delegates to, built on first use.
   */
  protected ?CommandsInterface $commands = NULL;

  /**
   * The experimental registry handed to the commands.
   */
  protected ?ExperimentalRegistryInterface $experimentalRegistry = NULL;

  /**
   * Whether the backend has been bootstrapped.
   */
  protected bool $bootstrapped = FALSE;

  /**
   * Random generator.
   */
  protected readonly RandomGeneratorInterface $random;

  /**
   * Global arguments or options for drush commands.
   */
  protected string $arguments = '';

  /**
   * Environment variables every drush command runs with, keyed by name.
   *
   * @var array<string, string>
   */
  protected array $environment = [];

  /**
   * Sets the drush alias or root path.
   *
   * @param string|null $alias
   *   A drush alias, or NULL to reach the site through the root path.
   * @param string|null $root_path
   *   The root path of the Drupal install, or NULL when an alias is given.
   *   This is an alternative to using aliases.
   * @param string $binary
   *   The path to the drush binary.
   * @param \DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface|null $random
   *   Random generator, or NULL for the package default.
   * @param string|null $commandsClass
   *   The class of the commands to build, or NULL for the commands this
   *   package ships for the detected major.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\BootstrapException
   *   Thrown when neither an alias nor a root path is given, when either is
   *   empty, or when the root path cannot be resolved.
   */
  public function __construct(?string $alias = NULL, ?string $root_path = NULL, string $binary = 'drush', ?RandomGeneratorInterface $random = NULL, protected readonly ?string $commandsClass = NULL) {
    if ($alias !== NULL && trim(ltrim($alias, '@')) === '') {
      throw new BootstrapException(sprintf('The drush alias "%s" names no site. Pass NULL to leave it out.', $alias));
    }

    // 'realpath()' resolves an empty path to the working directory.
    if ($root_path === '') {
      throw new BootstrapException('The root path is empty. Pass NULL to leave it out.');
    }

    if ($alias !== NULL) {
      $this->alias = ltrim($alias, '@');
    }
    elseif ($root_path !== NULL) {
      $resolved = realpath($root_path);

      if ($resolved === FALSE) {
        throw new BootstrapException(sprintf('No Drupal installation found at %s.', $root_path));
      }

      $this->root = $resolved;
    }
    else {
      throw new BootstrapException('A drush alias or root path is required.');
    }

    if ($binary === 'drush') {
      $binary = self::resolveProjectDrush();
    }

    $this->binary = $binary;
    $this->random = $random ?? new RandomGenerator();
  }

  /**
   * {@inheritdoc}
   */
  public function getRandom(): RandomGeneratorInterface {
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
  public function getDrushVersion(): int {
    return $this->drushMajorVersion ??= $this->detectMajorVersion();
  }

  /**
   * {@inheritdoc}
   */
  public function getCommands(): CommandsInterface {
    if (!$this->commands instanceof CommandsInterface) {
      $this->setCommands($this->createCommands($this->commandsClass ?? $this->getVersionCommandsClass()));
    }

    return $this->commands;
  }

  /**
   * {@inheritdoc}
   */
  public function setCommands(CommandsInterface $commands): void {
    $this->assertCommandsFitVersion($commands);

    if ($this->experimentalRegistry instanceof ExperimentalRegistryInterface) {
      $commands->setExperimentalRegistry($this->experimentalRegistry);
    }

    $this->commands = $commands;
  }

  /**
   * {@inheritdoc}
   *
   * The registry is handed to the commands once they are built, so setting it
   * runs no Drush command.
   *
   * @internal
   *   Injection point called by the backend registry.
   */
  public function setExperimentalRegistry(ExperimentalRegistryInterface $registry): void {
    $this->experimentalRegistry = $registry;
    $this->commands?->setExperimentalRegistry($registry);
  }

  /**
   * {@inheritdoc}
   */
  public function getCreationAliases(string $entity_type): array {
    return $this->getCommands()->getCreationAliases($entity_type);
  }

  /**
   * {@inheritdoc}
   *
   * The alias is registered on the commands, which run it when they create a
   * user.
   */
  public function registerCreationAlias(CreationAliasInterface $alias): void {
    $this->getCommands()->registerCreationAlias($alias);
  }

  /**
   * {@inheritdoc}
   *
   * The header is passed to each command in the environment variable PHP
   * reads a request header from, such as 'HTTP_X_CONFIG_NO_OVERRIDE'.
   */
  public function requestHeaderSet(string $name, string $value): void {
    $this->environment[static::getRequestHeaderVariable($name)] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function requestHeaderUnset(string $name): void {
    unset($this->environment[static::getRequestHeaderVariable($name)]);
  }

  /**
   * Returns the name PHP gives the server variable of a request header.
   *
   * @param string $name
   *   The header name, such as 'X-Config-No-Override'.
   */
  protected static function getRequestHeaderVariable(string $name): string {
    return 'HTTP_' . strtoupper(str_replace('-', '_', $name));
  }

  /**
   * {@inheritdoc}
   */
  public function cacheClear(): void {
    $this->getCommands()->cacheClear();
  }

  /**
   * {@inheritdoc}
   */
  public function cacheClearStatic(): void {
    $this->getCommands()->cacheClearStatic();
  }

  /**
   * {@inheritdoc}
   */
  public function cacheClearBin(string $bin): void {
    $this->getCommands()->cacheClearBin($bin);
  }

  /**
   * {@inheritdoc}
   */
  public function cacheInvalidateTags(array $tags): void {
    $this->getCommands()->cacheInvalidateTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  public function cacheDeletePagePath(string $bin, string $path, bool $is_pattern = FALSE): void {
    $this->getCommands()->cacheDeletePagePath($bin, $path, $is_pattern);
  }

  /**
   * {@inheritdoc}
   */
  public function configGet(string $name, ?string $key = NULL): mixed {
    return $this->getCommands()->configGet($name, $key);
  }

  /**
   * {@inheritdoc}
   */
  public function configGetOriginal(string $name, ?string $key = NULL): mixed {
    return $this->getCommands()->configGetOriginal($name, $key);
  }

  /**
   * {@inheritdoc}
   */
  public function configSet(string $name, string $key, mixed $value): void {
    $this->getCommands()->configSet($name, $key, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function configExists(string $name): bool {
    return $this->getCommands()->configExists($name);
  }

  /**
   * {@inheritdoc}
   */
  public function configGetData(string $name): array {
    return $this->getCommands()->configGetData($name);
  }

  /**
   * {@inheritdoc}
   */
  public function configSetData(string $name, array $data): void {
    $this->getCommands()->configSetData($name, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function configDelete(string $name): void {
    $this->getCommands()->configDelete($name);
  }

  /**
   * {@inheritdoc}
   */
  public function stateGet(string $name): mixed {
    return $this->getCommands()->stateGet($name);
  }

  /**
   * {@inheritdoc}
   */
  public function stateSet(string $name, mixed $value): void {
    $this->getCommands()->stateSet($name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function stateDelete(string $name): void {
    $this->getCommands()->stateDelete($name);
  }

  /**
   * {@inheritdoc}
   */
  public function stateExists(string $name): bool {
    return $this->getCommands()->stateExists($name);
  }

  /**
   * {@inheritdoc}
   */
  public function cronRun(): bool {
    return $this->getCommands()->cronRun();
  }

  /**
   * {@inheritdoc}
   */
  public function moduleInstall(string $module_name): void {
    $this->getCommands()->moduleInstall($module_name);
  }

  /**
   * {@inheritdoc}
   */
  public function moduleUninstall(string $module_name): void {
    $this->getCommands()->moduleUninstall($module_name);
  }

  /**
   * {@inheritdoc}
   */
  public function moduleIsEnabled(string $module_name): bool {
    return $this->getCommands()->moduleIsEnabled($module_name);
  }

  /**
   * {@inheritdoc}
   */
  public function moduleIsPresent(string $module_name): bool {
    return $this->getCommands()->moduleIsPresent($module_name);
  }

  /**
   * {@inheritdoc}
   */
  public function createRole(array $permissions, ?string $id = NULL, ?string $label = NULL): EntityStubInterface {
    return $this->getCommands()->createRole($permissions, $id, $label);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRole(string $role_name): void {
    $this->getCommands()->deleteRole($role_name);
  }

  /**
   * {@inheritdoc}
   */
  public function createUser(EntityStubInterface $stub): EntityStubInterface {
    return $this->getCommands()->createUser($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteUser(EntityStubInterface $stub): void {
    $this->getCommands()->deleteUser($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function addUserRole(EntityStubInterface $stub, string $role): void {
    $this->getCommands()->addUserRole($stub, $role);
  }

  /**
   * {@inheritdoc}
   */
  public function getOneTimeLoginUrl(EntityStubInterface $stub): string {
    return $this->getCommands()->getOneTimeLoginUrl($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function getOneTimeLoginDirectUrl(EntityStubInterface $stub): string {
    return $this->getCommands()->getOneTimeLoginDirectUrl($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function getUserRoles(EntityStubInterface $stub): array {
    return $this->getCommands()->getUserRoles($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function setUserPassword(EntityStubInterface $stub, string $password): void {
    $this->getCommands()->setUserPassword($stub, $password);
  }

  /**
   * {@inheritdoc}
   */
  public function queueCountItems(string $queue): int {
    return $this->getCommands()->queueCountItems($queue);
  }

  /**
   * {@inheritdoc}
   */
  public function queueDelete(string $queue): void {
    $this->getCommands()->queueDelete($queue);
  }

  /**
   * {@inheritdoc}
   */
  public function queueProcess(string $queue, int $limit, int $lease_time): int {
    return $this->getCommands()->queueProcess($queue, $limit, $lease_time);
  }

  /**
   * {@inheritdoc}
   */
  public function logGetLastId(): int {
    return $this->getCommands()->logGetLastId();
  }

  /**
   * {@inheritdoc}
   */
  public function logRead(array $types, int $after_id, int $severity): array {
    return $this->getCommands()->logRead($types, $after_id, $severity);
  }

  /**
   * {@inheritdoc}
   */
  public function logDelete(array $ids): void {
    $this->getCommands()->logDelete($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function searchIndexItems(int $limit): void {
    $this->getCommands()->searchIndexItems($limit);
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
   *   1 entry per whitespace-separated argument, empty when none are set.
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
   * @return \DrevOps\BehatSteps\Backend\Drupal\Drush\DrushResult
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

    // A signaled process yields a NULL exit code, so it is classified as a
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
    $result = $this->drushChecked($command, $arguments, $options);

    // Some Drush commands write to stderr instead of stdout.
    if ($result->output === '' || $result->output === '0') {
      return $result->errorOutput;
    }

    return $result->output;
  }

  /**
   * Executes a drush command, failing on a non-zero exit.
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
   *   The binary followed by its arguments, 1 entry each.
   *
   * @return \Symfony\Component\Process\Process
   *   The process after it has finished running.
   */
  protected function runProcess(array $argv): Process {
    $process = new Process($argv, NULL, $this->environment);
    $process->setTimeout(3600);
    $process->run();

    return $process;
  }

  /**
   * Resolves a bare 'drush' to the project's Drush, or leaves it to $PATH.
   *
   * Checks 'COMPOSER_BIN_DIR', then 'vendor/bin/drush' in the working
   * directory. A path it finds is absolute, so it stays valid after the working
   * directory changes.
   *
   * @return string
   *   The absolute path to the project's Drush, or 'drush' when it has none.
   */
  public static function resolveProjectDrush(): string {
    $composer_bin = getenv('COMPOSER_BIN_DIR');

    if ($composer_bin && file_exists($composer_bin . '/drush')) {
      return realpath($composer_bin) . '/drush';
    }

    $candidate = getcwd() . '/vendor/bin/drush';

    return file_exists($candidate) ? $candidate : 'drush';
  }

  /**
   * Parses options into individual argv entries.
   *
   * @param array<string, string|bool|null> $arguments
   *   An array of option names to values. A NULL value yields a bare flag.
   *
   * @return array<int, string>
   *   1 entry per option.
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

  /**
   * Detects the major version of the Drush binary.
   *
   * @return int
   *   The major version number, 13 or higher.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\BootstrapException
   *   When the major is below 13.
   * @throws \RuntimeException
   *   When Drush does not print its version.
   */
  protected function detectMajorVersion(): int {
    $output = trim($this->drush('version', [], ['format' => 'json']));
    $data = json_decode($output, TRUE);
    $version = is_array($data) ? ($data['drush-version'] ?? NULL) : NULL;

    if (!is_string($version) || preg_match('/^(\d+)\./', $version, $matches) !== 1) {
      throw new \RuntimeException(sprintf('Drush did not print its version. Output: %s', $output));
    }

    if ((int) $matches[1] < static::MINIMUM_VERSION) {
      throw new BootstrapException(sprintf('Unsupported Drush version %s. Drush %d or higher is required.', $version, static::MINIMUM_VERSION));
    }

    return (int) $matches[1];
  }

  /**
   * Gets the commands class this package ships for the detected major.
   *
   * Takes the commands of the newest major at or below the detected one, or
   * the base 'Commands' when there are none. Drush 15 gets 'Drush14\Commands'
   * when that class exists and 'Drush15\Commands' does not.
   *
   * @return class-string
   *   The commands class.
   */
  protected function getVersionCommandsClass(): string {
    for ($major = $this->getDrushVersion(); $major >= static::MINIMUM_VERSION; $major--) {
      $class = sprintf(static::VERSION_COMMANDS_CLASS, $major);

      if (class_exists($class)) {
        return $class;
      }
    }

    return Commands::class;
  }

  /**
   * Builds commands of the given class.
   *
   * The constructor receives the backend as the runner and the random
   * generator, in the order the base 'Commands' constructor takes them.
   *
   * @param string $class
   *   The commands class to build.
   *
   * @return \DrevOps\BehatSteps\Backend\Drupal\Drush\CommandsInterface
   *   The commands.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\BootstrapException
   *   When the class does not exist, does not implement 'CommandsInterface'
   *   or cannot be instantiated.
   */
  protected function createCommands(string $class): CommandsInterface {
    if (!class_exists($class)) {
      throw new BootstrapException(sprintf('Commands class "%s" does not exist.', $class));
    }

    if (!is_subclass_of($class, CommandsInterface::class)) {
      throw new BootstrapException(sprintf('Commands class "%s" must implement "%s".', $class, CommandsInterface::class));
    }

    if (!(new \ReflectionClass($class))->isInstantiable()) {
      throw new BootstrapException(sprintf('Commands class "%s" must be instantiable.', $class));
    }

    return new $class($this, $this->random);
  }

  /**
   * Asserts that commands built on commands of this package fit the major.
   *
   * Commands that neither are nor extend commands of this package pass.
   *
   * @param \DrevOps\BehatSteps\Backend\Drupal\Drush\CommandsInterface $commands
   *   The commands to check.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\BootstrapException
   *   When the nearest commands of this package the commands are or extend
   *   are the ones for another major.
   */
  protected function assertCommandsFitVersion(CommandsInterface $commands): void {
    $package_commands = $this->findPackageCommandsClass($commands::class);

    if ($package_commands === NULL) {
      return;
    }

    $version_commands = $this->getVersionCommandsClass();

    if ($package_commands === $version_commands) {
      return;
    }

    if ($package_commands === $commands::class) {
      throw new BootstrapException(sprintf('Commands class "%s" is not the commands class for Drush %d. Use "%s" instead.', $commands::class, $this->getDrushVersion(), $version_commands));
    }

    $template = 'Commands class "%s" extends "%s", which is not the commands class for Drush %d. Extend "%s" instead.';

    throw new BootstrapException(sprintf($template, $commands::class, $package_commands, $this->getDrushVersion(), $version_commands));
  }

  /**
   * Finds the commands class of this package that a class is or extends.
   *
   * Commands of this package are named by 'VERSION_COMMANDS_CLASS' with the
   * major filled in, or left out for the base 'Commands'.
   *
   * @param class-string $class
   *   The class to inspect.
   *
   * @return string|null
   *   The nearest of the class and its parents named as commands of this
   *   package, or NULL when there is none.
   */
  protected function findPackageCommandsClass(string $class): ?string {
    $pattern = '/^' . str_replace('%d', '\d*', preg_quote(static::VERSION_COMMANDS_CLASS, '/')) . '$/';

    for ($current = $class; $current !== FALSE; $current = get_parent_class($current)) {
      if (preg_match($pattern, $current) === 1) {
        return $current;
      }
    }

    return NULL;
  }

}
