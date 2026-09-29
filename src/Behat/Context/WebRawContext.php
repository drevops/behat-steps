<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context;

use Behat\Behat\Hook\Scope\ScenarioScope;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Testwork\Hook\HookDispatcher;
use DrevOps\BehatSteps\Behat\Config\TagOverrides;
use DrevOps\BehatSteps\Behat\Config\TraitOptionResolverFactory;
use DrevOps\BehatSteps\Behat\Config\TraitOptionResolverFactoryInterface;
use DrevOps\BehatSteps\Behat\Config\TraitOptionResolverInterface;
use DrevOps\BehatSteps\Behat\Manager\BasicAuthenticatorInterface;
use DrevOps\BehatSteps\Behat\Manager\DriverRegistryInterface;
use DrevOps\BehatSteps\Behat\Mink\BrowserCapabilityResolver;
use DrevOps\BehatSteps\Behat\ParametersTrait;
use DrevOps\BehatSteps\Behat\Tag;
use DrevOps\BehatSteps\Driver\DriverInterface;
use DrevOps\BehatSteps\Helper\Web\LastStepTrait;
use DrevOps\BehatSteps\Helper\Web\RequestHeadersTrait;
use DrevOps\BehatSteps\Helper\Web\StringTrait;
use Drupal\Component\Utility\Random;

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
  use ParametersTrait {
    setParameters as protected setParameterValues;
  }
  use RequestHeadersTrait;
  use StringTrait;

  /**
   * Driver registry.
   */
  protected ?DriverRegistryInterface $driverRegistry = NULL;

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
  protected ?BasicAuthenticatorInterface $basicAuthenticator = NULL;

  /**
   * Builds the option resolver out of the shared collaborators.
   */
  protected ?TraitOptionResolverFactoryInterface $optionResolverFactory = NULL;

  /**
   * Resolves the options this context's traits declare, NULL until first read.
   */
  protected ?TraitOptionResolverInterface $optionResolver = NULL;

  /**
   * Constructs a WebRawContext object.
   *
   * @param array<array-key, mixed> $config
   *   Option overrides, keyed by trait group and then by option name. Behat
   *   binds a context argument by parameter name, so a suite declares them
   *   under 'config'.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When a group or an option is not one this context composes, or a value
   *   does not match the type its declaration defaults to.
   */
  public function __construct(protected array $config = []) {
    // Resolved here rather than on first read, so a typo fails while Behat
    // builds the context instead of at the step that would have read it. The
    // extension's 'steps' section arrives later, through 'setParameters()'.
    $this->optionResolver = $this->buildOptionResolver();
  }

  /**
   * {@inheritdoc}
   */
  public function setParameters(array $parameters): void {
    $this->setParameterValues($parameters);

    $this->optionResolver = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setDriverRegistry(DriverRegistryInterface $driver_registry): void {
    $this->driverRegistry = $driver_registry;
  }

  /**
   * {@inheritdoc}
   */
  public function getDriverRegistry(): DriverRegistryInterface {
    if (!$this->driverRegistry instanceof DriverRegistryInterface) {
      throw new \RuntimeException('The driver registry is available only after Behat has initialized the context.');
    }

    return $this->driverRegistry;
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
  public function setBasicAuthenticator(BasicAuthenticatorInterface $basic_authenticator): void {
    $this->basicAuthenticator = $basic_authenticator;
  }

  /**
   * {@inheritdoc}
   */
  public function getBasicAuthenticator(): BasicAuthenticatorInterface {
    if (!$this->basicAuthenticator instanceof BasicAuthenticatorInterface) {
      throw new \RuntimeException('The basic authenticator is available only after Behat has initialized the context.');
    }

    return $this->basicAuthenticator;
  }

  /**
   * {@inheritdoc}
   */
  public function setOptionResolverFactory(TraitOptionResolverFactoryInterface $factory): void {
    $this->optionResolverFactory = $factory;

    $this->optionResolver = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptionResolver(): TraitOptionResolverInterface {
    $this->optionResolver ??= $this->buildOptionResolver();

    return $this->optionResolver;
  }

  /**
   * Returns the resolver factory, creating a standalone one on first use.
   *
   * A context Behat has not initialized resolves its options against its own
   * collaborators, which hold no scenario tags.
   */
  public function getOptionResolverFactory(): TraitOptionResolverFactoryInterface {
    $this->optionResolverFactory ??= new TraitOptionResolverFactory();

    return $this->optionResolverFactory;
  }

  /**
   * Returns a driver of this scenario by the name its suite gave it.
   *
   * @param string $name
   *   The tag name the suite lists the driver under.
   */
  public function getDriver(string $name): DriverInterface {
    return $this->getDriverRegistry()->getDriver($name);
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
    return $this->getDriverRegistry()->getDriverFor($capability);
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
   * Returns a trait option at whatever type it resolved to.
   *
   * Reserved for an option whose declaration defaults to NULL and so names no
   * type. Every other read names its type.
   *
   * @param string $group
   *   The trait group the option belongs to, such as 'javascript'.
   * @param string $key
   *   The option name within the group, such as 'fail_on_errors'.
   *
   * @return mixed
   *   The resolved value.
   *
   * @throws \RuntimeException
   *   When no trait this context composes declares the option.
   */
  public function getOption(string $group, string $key): mixed {
    return $this->getOptionResolver()->raw($group, $key);
  }

  /**
   * Returns a trait option declared as a boolean.
   *
   * @param string $group
   *   The trait group the option belongs to.
   * @param string $key
   *   The option name within the group.
   *
   * @throws \RuntimeException
   *   When no trait declares the option, or it resolved to another type.
   */
  public function getOptionBool(string $group, string $key): bool {
    return $this->getOptionResolver()->bool($group, $key);
  }

  /**
   * Returns a trait option declared as an integer.
   *
   * @param string $group
   *   The trait group the option belongs to.
   * @param string $key
   *   The option name within the group.
   *
   * @throws \RuntimeException
   *   When no trait declares the option, or it resolved to another type.
   */
  public function getOptionInt(string $group, string $key): int {
    return $this->getOptionResolver()->int($group, $key);
  }

  /**
   * Returns a trait option declared as a float.
   *
   * @param string $group
   *   The trait group the option belongs to.
   * @param string $key
   *   The option name within the group.
   *
   * @throws \RuntimeException
   *   When no trait declares the option, or it resolved to another type.
   */
  public function getOptionFloat(string $group, string $key): float {
    return $this->getOptionResolver()->float($group, $key);
  }

  /**
   * Returns a trait option declared as a string.
   *
   * @param string $group
   *   The trait group the option belongs to.
   * @param string $key
   *   The option name within the group.
   *
   * @throws \RuntimeException
   *   When no trait declares the option, or it resolved to another type.
   */
  public function getOptionString(string $group, string $key): string {
    return $this->getOptionResolver()->string($group, $key);
  }

  /**
   * Returns a trait option declared as a map.
   *
   * @param string $group
   *   The trait group the option belongs to.
   * @param string $key
   *   The option name within the group.
   *
   * @return array<array-key, mixed>
   *   The resolved value.
   *
   * @throws \RuntimeException
   *   When no trait declares the option, or it resolved to another type.
   */
  public function getOptionArray(string $group, string $key): array {
    return $this->getOptionResolver()->array($group, $key);
  }

  /**
   * Builds the resolver from this context's class, argument and parameters.
   *
   * @throws \RuntimeException
   *   When the context declares a constructor that never reached this one, so
   *   the 'config' argument was never set.
   */
  protected function buildOptionResolver(): TraitOptionResolverInterface {
    // A promoted constructor property is set by the constructor and by nothing
    // else, so a subclass constructor that does not forward leaves it unset.
    // @phpstan-ignore isset.initializedProperty
    if (!isset($this->config)) {
      throw new \RuntimeException(sprintf('%s declares a constructor that does not call parent::__construct(), so its "config" argument was never set. Add "array $config = []" to the constructor and forward it.', static::class));
    }

    $steps = $this->getParameter('steps');

    return $this->getOptionResolverFactory()->create(static::class, $this->config, is_array($steps) ? $steps : []);
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
    if (in_array(TagOverrides::SKIP_TAG_PREFIX . $name, Tag::all($scope), TRUE)) {
      return TRUE;
    }

    $group = $this->getOptionResolver()->groupFor($name);

    return $group !== NULL && !$this->getOptionBool($group, TagOverrides::ENABLED_OPTION);
  }

}
