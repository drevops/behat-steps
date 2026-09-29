<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context;

use Behat\Behat\Hook\Scope\ScenarioScope;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Testwork\Hook\HookDispatcher;
use DrevOps\BehatSteps\Behat\Manager\BasicAuthInterface;
use DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface;
use DrevOps\BehatSteps\Behat\Mink\BrowserCapabilityResolver;
use DrevOps\BehatSteps\Behat\ParametersTrait;
use DrevOps\BehatSteps\Behat\Tag;
use DrevOps\BehatSteps\Driver\DriverInterface;
use DrevOps\BehatSteps\Helper\Web\LastStepTrait;
use DrevOps\BehatSteps\Helper\Web\RequestHeadersTrait;
use DrevOps\BehatSteps\Helper\Web\StringTrait;
use Drupal\Component\Utility\Random;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * Root context carrying the plumbing every suite needs.
 *
 * Provides driver access, authentication delegation, option resolution and
 * the hook dispatcher, and composes the four helper traits the web half
 * shares. It registers no step definitions and references no Drupal class
 * beyond 'Random', which a layer lint holds.
 *
 * Extend this to compose a context out of a chosen set of traits; extend
 * 'WebContext' instead to get the whole web vocabulary, or 'DrupalContext'
 * to get the Drupal vocabulary on top of it.
 *
 * The helper traits it composes are on '$this' for a consuming project's own
 * step definitions, and composing one again in a step trait shares the same
 * state rather than duplicating it.
 *
 * @see \DrevOps\BehatSteps\Behat\Context\WebContext
 * @see \DrevOps\BehatSteps\Behat\Context\DrupalContext
 */
class WebRawContext extends RawMinkContext implements DriverAwareInterface {

  use LastStepTrait;
  use ParametersTrait;
  use RequestHeadersTrait;
  use StringTrait;

  /**
   * Driver manager.
   */
  protected ?DriverManagerInterface $driverManager = NULL;

  /**
   * Resolves what the session's browser driver can do.
   */
  protected ?BrowserCapabilityResolver $browserResolver = NULL;

  /**
   * Hook dispatcher.
   */
  protected ?HookDispatcher $dispatcher = NULL;

  /**
   * Applies webserver-level basic auth to the session.
   */
  protected ?BasicAuthInterface $basicAuthManager = NULL;

  /**
   * Per-context option overrides, as the suite declared them.
   *
   * @var array<string, array<string, mixed>>
   */
  protected array $contextConfig = [];

  /**
   * Options resolved against the schemas, NULL until the first read.
   *
   * @var array<string, array<string, mixed>>|null
   */
  protected ?array $contextConfigResolved = NULL;

  /**
   * Declared schemas, keyed by context class name.
   *
   * Reflection over every method of a context composing forty traits is too
   * expensive to repeat per option read, and a class's schemas cannot change
   * within a run.
   *
   * @var array<string, array<string, array<string, array<string, mixed>>>>
   */
  protected static array $contextConfigDeclarationCache = [];

  /**
   * Constructs a WebRawContext object.
   *
   * @param array<string, array<string, mixed>> $config
   *   Option overrides, keyed by trait group and then by option name. Behat
   *   binds a context argument by parameter name, so a suite declares them
   *   under 'config'.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When a group or an option is not one this context composes, or a value
   *   does not match the type its declaration defaults to.
   */
  public function __construct(array $config = []) {
    // Validated here rather than on first read, so a typo fails while Behat
    // builds the context instead of at the step that would have read it.
    $this->contextConfigMerge($this->contextConfigDefaults(), $config, TRUE);

    $this->contextConfig = $config;
  }

  /**
   * {@inheritdoc}
   */
  public function setDriverManager(DriverManagerInterface $driver_manager): void {
    $this->driverManager = $driver_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getDriverManager(): DriverManagerInterface {
    if (!$this->driverManager instanceof DriverManagerInterface) {
      throw new \RuntimeException('The driver manager is available only after Behat has initialized the context.');
    }

    return $this->driverManager;
  }

  /**
   * {@inheritdoc}
   */
  public function setDispatcher(HookDispatcher $dispatcher): void {
    $this->dispatcher = $dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function setBasicAuthManager(BasicAuthInterface $basic_auth_manager): void {
    $this->basicAuthManager = $basic_auth_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getBasicAuthManager(): BasicAuthInterface {
    if (!$this->basicAuthManager instanceof BasicAuthInterface) {
      throw new \RuntimeException('The basic authentication manager is available only after Behat has initialized the context.');
    }

    return $this->basicAuthManager;
  }

  /**
   * Returns a driver of this scenario by the name its suite gave it.
   *
   * @param string $name
   *   The tag name the suite lists the driver under.
   */
  public function getDriver(string $name): DriverInterface {
    return $this->getDriverManager()->getDriver($name);
  }

  /**
   * Returns the highest-priority driver providing the given capability.
   *
   * A step names the capability it needs and never a driver, so the shipped
   * vocabulary stays portable: a project that registers its own driver gets
   * the step working as soon as that driver implements the interface.
   *
   * @param class-string<T> $capability
   *   The capability interface the caller needs.
   *
   * @return T
   *   The driver, bootstrapped.
   *
   * @throws \DrevOps\BehatSteps\Driver\Exception\UnsupportedDriverActionException
   *   When no driver in the scenario's order implements the capability.
   *
   * @template T of object
   */
  public function driverFor(string $capability): object {
    return $this->getDriverManager()->getDriverFor($capability);
  }

  /**
   * Returns the adapter providing a browser capability for this session.
   *
   * The browser half of the vocabulary resolves its capabilities separately
   * from the Drupal half: a Mink session runs exactly 1 driver, so there is no
   * ordered list to walk and no driver to bootstrap.
   *
   * @param class-string<T> $capability
   *   The browser capability interface the caller needs.
   *
   * @return T
   *   The adapter speaking for the session's driver.
   *
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *   When the session's driver does not provide the capability.
   *
   * @template T of object
   */
  public function browserDriverFor(string $capability): object {
    return $this->getBrowserResolver()->resolve($this->getSession()->getDriver(), $capability);
  }

  /**
   * Whether this session's driver provides a browser capability.
   *
   * A step that degrades gracefully without the capability asks this; a step
   * that cannot proceed without it calls 'browserDriverFor()'.
   *
   * @param class-string $capability
   *   The browser capability interface to look for.
   */
  public function browserDriverHas(string $capability): bool {
    return $this->getBrowserResolver()->has($this->getSession()->getDriver(), $capability);
  }

  /**
   * Returns the browser capability resolver, creating it on first use.
   */
  public function getBrowserResolver(): BrowserCapabilityResolver {
    $this->browserResolver ??= new BrowserCapabilityResolver();

    return $this->browserResolver;
  }

  /**
   * Returns the driver's random generator.
   */
  public function getRandom(): Random {
    return $this->driverFor(DriverInterface::class)->getRandom();
  }

  /**
   * Returns a trait option resolved for this context.
   *
   * The value is taken from the first of these that declares it: the scenario's
   * tags, the feature's tags, this context's 'config' argument, the extension's
   * 'steps' section, the declaration's own default. The two tag layers are read
   * only when a scope is passed, which a hook has and a step does not.
   *
   * @param string $group
   *   The trait group the option belongs to, such as 'javascript'.
   * @param string $key
   *   The option name within the group, such as 'fail_on_errors'.
   * @param \Behat\Behat\Hook\Scope\ScenarioScope|null $scope
   *   The scenario scope a hook received, to read the tag layers.
   *
   * @return mixed
   *   The resolved value.
   *
   * @throws \RuntimeException
   *   When no trait this context composes declares the option.
   */
  public function getOption(string $group, string $key, ?ScenarioScope $scope = NULL): mixed {
    $schema = $this->contextConfigDeclarations();

    if (!isset($schema[$group][$key])) {
      throw new \RuntimeException(sprintf('No trait in %s declares the option "%s.%s". Declared options: %s.', static::class, $group, $key, $this->contextConfigOptionList()));
    }

    $resolved = $this->contextConfigResolve();
    $value = $resolved[$group][$key];

    if (!$scope instanceof ScenarioScope) {
      return $value;
    }

    return $this->contextConfigApplyTags($group, $key, $value, $scope);
  }

  /**
   * Determines whether scenario cleanup should run.
   *
   * 'BEHAT_STEPS_DISABLE_CLEANUP' set to '1', 'true', 'yes' or 'on'
   * (case-insensitive) skips the AfterScenario teardown of entities, users
   * and roles, so the state a failing scenario leaves behind can be
   * inspected. The variable is not intended for CI runs.
   */
  protected function shouldCleanup(): bool {
    $env = getenv('BEHAT_STEPS_DISABLE_CLEANUP');

    if ($env === FALSE || $env === '') {
      return TRUE;
    }

    return !in_array(strtolower(trim($env)), ['1', 'true', 'yes', 'on'], TRUE);
  }

  /**
   * Determines whether a scenario opts out of a hook.
   *
   * One tag form covers every hook in the library:
   * '@behat-steps-skip:<Name>', where '<Name>' is either a hook method name or
   * a trait name. Feature tags and scenario tags are read together, so the tag
   * works on either line.
   *
   * A trait that declares an 'enabled' option is also switched off by that
   * option, so a project turns the trait off for the whole profile or for one
   * context instead of tagging every feature file.
   *
   * @param string $name
   *   The hook method name or trait name the tag would carry.
   * @param \Behat\Behat\Hook\Scope\ScenarioScope $scope
   *   The scenario scope the hook received.
   *
   * @return bool
   *   TRUE when the scenario or its feature carries the skip tag, or the
   *   trait's 'enabled' option resolves to FALSE.
   */
  protected function skipTag(string $name, ScenarioScope $scope): bool {
    $tags = Tag::all($scope);

    if (in_array('behat-steps-skip:' . $name, $tags, TRUE)) {
      return TRUE;
    }

    $group = $this->contextConfigGroupFor($name);

    return $group !== NULL && $this->getOption($group, 'enabled', $scope) === FALSE;
  }

  /**
   * Collects the option declarations of every trait this context composes.
   *
   * A trait declares its options in a '<prefix>ConfigSchema()' method, named
   * by the prefix its other methods carry. The group name derives from the
   * method name, so a consuming project's own trait participates without being
   * registered anywhere.
   *
   * @return array<string, array<string, array<string, mixed>>>
   *   Declarations keyed by group name and then by option name.
   *
   * @throws \RuntimeException
   *   When a declaration omits its default or its description.
   */
  protected function contextConfigDeclarations(): array {
    if (isset(self::$contextConfigDeclarationCache[static::class])) {
      return self::$contextConfigDeclarationCache[static::class];
    }

    $reflection = new \ReflectionClass(static::class);
    $schema = [];

    foreach ($reflection->getMethods() as $method) {
      if ($method->getNumberOfParameters() > 0 || preg_match('/^(.+)ConfigSchema$/', $method->getName(), $matches) !== 1) {
        continue;
      }

      $group = $this->contextConfigSnakeCase($matches[1]);
      $declarations = $method->invoke($this);

      if (!is_array($declarations)) {
        throw new \RuntimeException(sprintf('%s::%s() must return an array of option declarations.', static::class, $method->getName()));
      }

      foreach ($declarations as $key => $declaration) {
        if (!is_array($declaration) || !array_key_exists('default', $declaration) || !isset($declaration['description'])) {
          throw new \RuntimeException(sprintf('The "%s.%s" declaration in %s::%s() needs a "default" and a "description".', $group, $key, static::class, $method->getName()));
        }

        if (isset($declaration['tags']) && !is_array($declaration['tags'])) {
          throw new \RuntimeException(sprintf('The "%s.%s" declaration in %s::%s() lists its tags as a map of tag name to the value it sets.', $group, $key, static::class, $method->getName()));
        }
      }

      $schema[$group] = $declarations;
    }

    ksort($schema);
    self::$contextConfigDeclarationCache[static::class] = $schema;

    return $schema;
  }

  /**
   * Resolves every option against the extension and this context's overrides.
   *
   * The extension's 'steps' section is set through 'setParameters()', which
   * Behat calls after it has constructed the context, so the resolution is
   * deferred to the first read and memoised.
   *
   * @return array<string, array<string, mixed>>
   *   Resolved values keyed by group name and then by option name.
   */
  protected function contextConfigResolve(): array {
    if ($this->contextConfigResolved !== NULL) {
      return $this->contextConfigResolved;
    }

    $resolved = $this->contextConfigDefaults();

    $steps = $this->getParameter('steps');

    // A group under 'steps' may name a trait only one of the registered
    // contexts composes, so an unservable group is skipped rather than
    // rejected.
    if (is_array($steps)) {
      $resolved = $this->contextConfigMerge($resolved, $steps, FALSE);
    }

    $this->contextConfigResolved = $this->contextConfigMerge($resolved, $this->contextConfig, TRUE);

    return $this->contextConfigResolved;
  }

  /**
   * Returns every declared option at its default value.
   *
   * @return array<string, array<string, mixed>>
   *   Default values keyed by group name and then by option name.
   */
  protected function contextConfigDefaults(): array {
    $defaults = [];

    foreach ($this->contextConfigDeclarations() as $group => $declarations) {
      foreach ($declarations as $key => $declaration) {
        $defaults[$group][$key] = $declaration['default'];
      }
    }

    return $defaults;
  }

  /**
   * Layers one set of overrides over the resolved values.
   *
   * @param array<string, array<string, mixed>> $resolved
   *   The values resolved so far.
   * @param array<string, mixed> $overrides
   *   The overrides to apply.
   * @param bool $strict
   *   Reject a group or an option no trait declares, rather than skipping it.
   *
   * @return array<string, array<string, mixed>>
   *   The values with the overrides applied.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When a group or an option is undeclared under a strict merge, a group
   *   does not hold a map of options, or a value does not match the type its
   *   declaration defaults to.
   */
  protected function contextConfigMerge(array $resolved, array $overrides, bool $strict): array {
    $schema = $this->contextConfigDeclarations();

    foreach ($overrides as $group => $options) {
      if (!isset($schema[$group])) {
        if ($strict) {
          throw new InvalidConfigurationException(sprintf('Unknown option group "%s" for context "%s". This context accepts: %s.', $group, static::class, implode(', ', array_keys($schema)) ?: 'nothing'));
        }

        continue;
      }

      if (!is_array($options)) {
        throw new InvalidConfigurationException(sprintf('The "%s" option group holds a map of options, but a %s was given.', $group, get_debug_type($options)));
      }

      foreach ($options as $key => $value) {
        if (!isset($schema[$group][$key])) {
          if ($strict) {
            throw new InvalidConfigurationException(sprintf('Unknown option "%s.%s" for context "%s". The "%s" group accepts: %s.', $group, $key, static::class, $group, implode(', ', array_keys($schema[$group]))));
          }

          continue;
        }

        $resolved[$group][$key] = $this->contextConfigCast($value, $schema[$group][$key]['default'], $group, (string) $key);
      }
    }

    return $resolved;
  }

  /**
   * Reads a configured value as the type its declaration defaults to.
   *
   * @param mixed $value
   *   The configured value.
   * @param mixed $default
   *   The declared default, whose type the value has to match.
   * @param string $group
   *   The group name, for the failure message.
   * @param string $key
   *   The option name, for the failure message.
   *
   * @return mixed
   *   The value, cast where a numeric form is unambiguous.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When the value cannot be read as the declared type.
   */
  protected function contextConfigCast(mixed $value, mixed $default, string $group, string $key): mixed {
    $expected = get_debug_type($default);

    if ($expected === 'bool' && is_bool($value)) {
      return $value;
    }

    if ($expected === 'int' && (is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1))) {
      return (int) $value;
    }

    if ($expected === 'float' && (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)))) {
      return (float) $value;
    }

    if ($expected === 'string' && (is_string($value) || is_int($value) || is_float($value))) {
      return (string) $value;
    }

    if ($expected === 'array' && is_array($value)) {
      return $value;
    }

    // A declaration defaulting to NULL names no type, so anything it is given
    // passes through.
    if (!in_array($expected, ['bool', 'int', 'float', 'string', 'array'], TRUE)) {
      return $value;
    }

    throw new InvalidConfigurationException(sprintf('The "%s.%s" option expects %s, but %s was given.', $group, $key, $this->contextConfigTypeName($expected), $this->contextConfigTypeName(get_debug_type($value))));
  }

  /**
   * Names a type as it reads in a failure message.
   *
   * @param string $type
   *   A type name as 'get_debug_type()' reports it.
   *
   * @return string
   *   The name with its article, or the type itself where none applies.
   */
  protected function contextConfigTypeName(string $type): string {
    return match ($type) {
      'bool' => 'a boolean',
      'int' => 'an integer',
      'float' => 'a float',
      'string' => 'a string',
      'array' => 'a map',
      'null' => 'null',
      default => 'a ' . $type,
    };
  }

  /**
   * Applies the tag layers of one option.
   *
   * A declaration names the tags that set it and the value each one sets.
   * Feature tags are read before scenario tags, so the tag on the narrower node
   * settles the value.
   *
   * @param string $group
   *   The group the option belongs to.
   * @param string $key
   *   The option name.
   * @param mixed $value
   *   The value resolved from the configuration.
   * @param \Behat\Behat\Hook\Scope\ScenarioScope $scope
   *   The scenario scope a hook received.
   *
   * @return mixed
   *   The value, replaced by whatever the last matching tag sets.
   */
  protected function contextConfigApplyTags(string $group, string $key, mixed $value, ScenarioScope $scope): mixed {
    $tags = $this->contextConfigDeclarations()[$group][$key]['tags'] ?? [];

    // An 'enabled' option is also switched off by the library's one skip tag,
    // named after the trait the group belongs to.
    if ($key === 'enabled') {
      $tags['behat-steps-skip:' . ucfirst($this->contextConfigCamelCase($group)) . 'Trait'] = FALSE;
    }

    if ($tags === []) {
      return $value;
    }

    foreach (Tag::all($scope) as $tag) {
      if (array_key_exists($tag, $tags)) {
        $value = $tags[$tag];
      }
    }

    return $value;
  }

  /**
   * Resolves the group a skip name belongs to.
   *
   * A name is either a trait name, which maps to its group directly, or a hook
   * method name, which carries its trait's prefix. The longest matching prefix
   * wins, so 'configOverrideBeforeStep' resolves to 'config_override' rather
   * than to 'config'.
   *
   * @param string $name
   *   The hook method name or trait name a skip tag would carry.
   *
   * @return string|null
   *   The group name, or NULL when no group with an 'enabled' option matches.
   */
  protected function contextConfigGroupFor(string $name): ?string {
    $groups = array_keys(array_filter($this->contextConfigDeclarations(), static fn(array $declarations): bool => isset($declarations['enabled'])));

    if (str_ends_with($name, 'Trait')) {
      $group = $this->contextConfigSnakeCase(substr($name, 0, -strlen('Trait')));

      return in_array($group, $groups, TRUE) ? $group : NULL;
    }

    $match = NULL;

    foreach ($groups as $group) {
      $prefix = $this->contextConfigCamelCase($group);

      if (!str_starts_with($name, $prefix) || !ctype_upper(substr($name, strlen($prefix), 1))) {
        continue;
      }

      if ($match === NULL || strlen($group) > strlen($match)) {
        $match = $group;
      }
    }

    return $match;
  }

  /**
   * Lists every declared option as a dotted path.
   */
  protected function contextConfigOptionList(): string {
    $paths = [];

    foreach ($this->contextConfigDeclarations() as $group => $declarations) {
      foreach (array_keys($declarations) as $key) {
        $paths[] = $group . '.' . $key;
      }
    }

    return implode(', ', $paths) ?: 'none';
  }

  /**
   * Converts a camel case method prefix to its snake case group name.
   */
  protected function contextConfigSnakeCase(string $prefix): string {
    return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $prefix));
  }

  /**
   * Converts a snake case group name to its camel case method prefix.
   */
  protected function contextConfigCamelCase(string $group): string {
    return lcfirst(str_replace('_', '', ucwords($group, '_')));
  }

}
