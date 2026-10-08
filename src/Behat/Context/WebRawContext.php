<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context;

use Behat\Behat\Hook\Scope\ScenarioScope;
use Behat\Mink\Exception\DriverException;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Testwork\Hook\HookDispatcher;
use Behat\Testwork\Hook\Scope\HookScope;
use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException;
use DrevOps\BehatSteps\Behat\Auth\BasicAuthenticatorInterface;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Behat\Config\ParametersTrait;
use DrevOps\BehatSteps\Behat\Config\TagOverrides;
use DrevOps\BehatSteps\Behat\Config\TraitOptionResolverFactory;
use DrevOps\BehatSteps\Behat\Config\TraitOptionResolverFactoryInterface;
use DrevOps\BehatSteps\Behat\Config\TraitOptionResolverInterface;
use DrevOps\BehatSteps\Behat\Http\HttpClientFactory;
use DrevOps\BehatSteps\Behat\Http\HttpClientFactoryInterface;
use DrevOps\BehatSteps\Behat\Http\HttpIdentity;
use DrevOps\BehatSteps\Behat\Mink\BrowserCapabilityResolver;
use DrevOps\BehatSteps\Behat\Mink\Capability\CookieCapabilityInterface;
use DrevOps\BehatSteps\Behat\Mink\Capability\HttpClientCapabilityInterface;
use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Behat\Prerequisite\PrerequisiteReader;
use DrevOps\BehatSteps\Behat\Registry\BackendRegistryInterface;
use DrevOps\BehatSteps\Behat\Tag;
use DrevOps\BehatSteps\Helper\Web\LastStepTrait;
use DrevOps\BehatSteps\Helper\Web\RequestHeadersTrait;
use DrevOps\BehatSteps\Helper\Web\StringTrait;
use Drupal\Component\Utility\Random;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Root context carrying the plumbing every suite needs.
 *
 * Provides backend access, authentication delegation, option resolution,
 * prerequisite checks and the hook dispatcher, and composes 3 of the web
 * helper traits. It registers no step definitions and references no Drupal
 * class beyond 'Random'.
 *
 * A context composed out of a chosen set of traits extends this class.
 * 'WebContext' adds the whole web vocabulary, and 'DrupalContext' adds the
 * Drupal vocabulary on top of that.
 *
 * The helper traits it composes are on '$this' for a consuming project's own
 * step definitions. A step trait that composes one of them again shares the
 * same state.
 *
 * @see \DrevOps\BehatSteps\Behat\Context\WebContext
 * @see \DrevOps\BehatSteps\Behat\Context\DrupalContext
 */
class WebRawContext extends RawMinkContext implements BackendAwareInterface {

  use LastStepTrait;
  use ParametersTrait {
    setParameters as protected setParameterValues;
  }
  use RequestHeadersTrait;
  use StringTrait;

  /**
   * Backend registry.
   */
  protected ?BackendRegistryInterface $backendRegistry = NULL;

  /**
   * Resolves what the session's browser driver can do.
   */
  protected ?BrowserCapabilityResolver $browserCapabilityResolver = NULL;

  /**
   * Hook dispatcher.
   */
  protected ?HookDispatcher $hookDispatcher = NULL;

  /**
   * Applies webserver-level basic auth to the session.
   */
  protected ?BasicAuthenticatorInterface $basicAuthenticator = NULL;

  /**
   * Builds the detached and bare browsers.
   */
  protected ?HttpClientFactoryInterface $httpClientFactory = NULL;

  /**
   * Builds the option resolver out of the shared collaborators.
   */
  protected ?TraitOptionResolverFactoryInterface $optionResolverFactory = NULL;

  /**
   * Resolves the options this context's traits declare.
   *
   * Built by the constructor, then NULL between a 'setParameters()' or
   * 'setOptionResolverFactory()' call and the next 'getOptionResolver()'
   * call, which rebuilds it.
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
    // Built here so a mistyped option fails while Behat builds the context,
    // before any step reads it. The extension's 'steps' section is set later,
    // through 'setParameters()'.
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
  public function setBackendRegistry(BackendRegistryInterface $backend_registry): void {
    $this->backendRegistry = $backend_registry;
  }

  /**
   * {@inheritdoc}
   */
  public function getBackendRegistry(): BackendRegistryInterface {
    if (!$this->backendRegistry instanceof BackendRegistryInterface) {
      throw new \RuntimeException('The backend registry is available only after Behat has initialized the context.');
    }

    return $this->backendRegistry;
  }

  /**
   * {@inheritdoc}
   */
  public function setHookDispatcher(HookDispatcher $hook_dispatcher): void {
    $this->hookDispatcher = $hook_dispatcher;
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
  public function setHttpClientFactory(HttpClientFactoryInterface $factory): void {
    $this->httpClientFactory = $factory;
  }

  /**
   * {@inheritdoc}
   *
   * A context Behat has not initialized builds a standalone factory on first
   * use, which applies no connection options.
   */
  public function getHttpClientFactory(): HttpClientFactoryInterface {
    if (!$this->httpClientFactory instanceof HttpClientFactoryInterface) {
      $base_url = $this->getMinkParameter('base_url');
      $this->httpClientFactory = new HttpClientFactory(HttpClient::create(), is_string($base_url) ? $base_url : NULL);
    }

    return $this->httpClientFactory;
  }

  /**
   * Returns the browser the Mink session drives.
   *
   * A request sent through it becomes the page the next steps read. Only a
   * session on a PHP browser driver has one.
   *
   * @return \Symfony\Component\BrowserKit\AbstractBrowser<covariant object, covariant object>
   *   The browser the session drives.
   *
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *   When the session runs a real browser, as a JavaScript session does.
   */
  public function httpPageClient(): AbstractBrowser {
    return $this->browserDriverFor(HttpClientCapabilityInterface::class)->httpClient();
  }

  /**
   * Returns a one-off browser that acts as the scenario's visitor.
   *
   * It sends the session's cookies and the headers the steps set, plus the
   * site's basic-auth credentials, and leaves the page the session holds
   * untouched. It works under every session, JavaScript ones included.
   *
   * @param array<string, mixed> $options
   *   Symfony HttpClient options for this browser, such as a 'timeout'.
   *
   * @return \Symfony\Component\BrowserKit\AbstractBrowser<covariant object, covariant object>
   *   A fresh browser holding the scenario's identity.
   */
  public function httpDetachedClient(array $options = []): AbstractBrowser {
    return $this->getHttpClientFactory()->createDetached($this->httpIdentity(), $options);
  }

  /**
   * Returns a one-off browser that carries no scenario state.
   *
   * It applies the connection options the site's 'browserkit_http' session
   * declares, and nothing from the scenario. It suits requests that are not
   * the visitor's own, such as fetching a script from a CDN.
   *
   * @param array<string, mixed> $options
   *   Symfony HttpClient options for this browser, such as a 'timeout'.
   *
   * @return \Symfony\Component\BrowserKit\AbstractBrowser<covariant object, covariant object>
   *   A fresh browser with an empty cookie jar.
   */
  public function httpBareClient(array $options = []): AbstractBrowser {
    return $this->getHttpClientFactory()->createBare($options);
  }

  /**
   * Reads the scenario's identity from the current session.
   */
  protected function httpIdentity(): HttpIdentity {
    $cookies = [];
    $cookie_url = NULL;

    try {
      $cookie_url = $this->getSession()->getCurrentUrl();

      foreach ($this->browserDriverFor(CookieCapabilityInterface::class)->cookieGetAll() as $cookie) {
        $cookies[$cookie['name']] = $cookie['value'];
      }
    }
    catch (DriverException) {
      // A session that has not opened a page yet holds no cookies.
    }

    return new HttpIdentity($cookies, $cookie_url, $this->requestHeadersAll(), $this->basicAuthenticator?->findCredentials());
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
   * Returns a backend of this scenario by the name its suite gave it.
   *
   * @param string $name
   *   The tag name the suite lists the backend under.
   */
  public function getBackend(string $name): BackendInterface {
    return $this->getBackendRegistry()->getBackend($name);
  }

  /**
   * Returns the highest-priority backend providing the given capability.
   *
   * A step names the capability it needs and never a backend, so the shipped
   * vocabulary stays portable. A backend a project registers serves the step
   * as soon as it implements the interface.
   *
   * @param class-string<T> $capability
   *   The capability interface the caller needs.
   *
   * @return T
   *   The backend, bootstrapped.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException
   *   When no backend in the scenario's order implements the capability.
   *
   * @template T of object
   */
  public function backendFor(string $capability): object {
    return $this->getBackendRegistry()->getBackendFor($capability);
  }

  /**
   * Returns the adapter providing a browser capability for this session.
   *
   * The browser half of the vocabulary resolves its capabilities separately
   * from the Drupal half. A Mink session runs exactly 1 browser driver, so
   * there is no ordered list to walk and nothing to bootstrap.
   *
   * @param class-string<T> $capability
   *   The browser capability interface the caller needs.
   *
   * @return T
   *   The adapter for the session's browser driver.
   *
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *   When the session's browser driver does not provide the capability.
   *
   * @template T of object
   */
  public function browserDriverFor(string $capability): object {
    return $this->getBrowserCapabilityResolver()->resolve($this->getSession()->getDriver(), $capability);
  }

  /**
   * Whether this session's browser driver provides a browser capability.
   *
   * A step that degrades gracefully without the capability calls this; a step
   * that cannot proceed without it calls 'browserDriverFor()'.
   *
   * @param class-string $capability
   *   The browser capability interface to look for.
   */
  public function browserDriverHas(string $capability): bool {
    return $this->getBrowserCapabilityResolver()->has($this->getSession()->getDriver(), $capability);
  }

  /**
   * Returns the browser capability resolver, creating it on first use.
   */
  public function getBrowserCapabilityResolver(): BrowserCapabilityResolver {
    $this->browserCapabilityResolver ??= new BrowserCapabilityResolver();

    return $this->browserCapabilityResolver;
  }

  /**
   * Returns the backend's random generator.
   */
  public function getRandom(): Random {
    return $this->backendFor(BackendInterface::class)->getRandom();
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
   * and roles. The state a failing scenario leaves behind can then be
   * inspected.
   *
   * The variable is not intended for CI runs.
   */
  protected function shouldCleanup(): bool {
    $env = getenv('BEHAT_STEPS_DISABLE_CLEANUP');

    if ($env === FALSE || $env === '') {
      return TRUE;
    }

    return !in_array(strtolower(trim($env)), ['1', 'true', 'yes', 'on'], TRUE);
  }

  /**
   * Determines whether a scenario switches a trait's hooks off.
   *
   * The tag names the trait: '@behat-steps-skip:<TraitName>' switches off
   * every hook the trait registers. Feature tags and scenario tags are read
   * together, so the tag works on either line.
   *
   * A trait that declares an 'enabled' option is also switched off by that
   * option. Through the option, a project turns the trait off for the whole
   * profile or for 1 context instead of tagging every feature file.
   *
   * @param string $trait
   *   The trait the hook belongs to, fully qualified or short. A hook passes
   *   '__TRAIT__'.
   * @param \Behat\Behat\Hook\Scope\ScenarioScope $scope
   *   The scenario scope the hook received.
   *
   * @return bool
   *   TRUE when the scenario or its feature carries the skip tag, or the
   *   trait's 'enabled' option resolves to FALSE.
   */
  protected function skipTag(string $trait, ScenarioScope $scope): bool {
    $name = $this->traitName($trait);

    if (Tag::has($scope, TagOverrides::SKIP_TAG_PREFIX . $name)) {
      return TRUE;
    }

    $group = $this->getOptionResolver()->groupFor($name);

    return $group !== NULL && !$this->getOptionBool($group, Option::ENABLED);
  }

  /**
   * Returns a backend providing a capability, reusing one already reached.
   *
   * A read-only query such as a module check returns the same result through
   * any backend. A backend the scenario already reached is then returned
   * before the first one in the list, so no second backend starts.
   *
   * @param class-string<T> $capability
   *   The capability interface the caller needs.
   *
   * @return T
   *   The backend, bootstrapped.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException
   *   When no backend in the scenario's order implements the capability.
   *
   * @template T of object
   */
  protected function anyBackendFor(string $capability): object {
    $registry = $this->getBackendRegistry();

    return $registry->getResolvedBackendFor($capability) ?? $registry->getBackendFor($capability);
  }

  /**
   * Asserts that the prerequisites a trait declares hold.
   *
   * A trait declares them in a '<prefix>Prerequisites()' method. Each names
   * a capability a backend in the scenario's list provides and, optionally,
   * a check that backend passes.
   *
   * A backend the scenario already reached is used before the first one in
   * the list, so checking never starts a second one.
   *
   * @param string $trait
   *   The trait whose prerequisites to assert. A hook or a step passes
   *   '__TRAIT__'.
   * @param \Behat\Testwork\Hook\Scope\HookScope|null $scope
   *   The scope of the hook that checks, or NULL when a step checks. The
   *   option and the tag that switch a trait off stop its hooks but never a
   *   step, so only a hook's failure names them.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException
   *   When no backend in the scenario's list provides a declared capability.
   * @throws \RuntimeException
   *   When a declared check fails.
   */
  protected function assertPrerequisites(string $trait, ?HookScope $scope = NULL): void {
    $failure = $this->prerequisiteFailure($trait, $scope instanceof HookScope);

    if ($failure instanceof \RuntimeException) {
      throw $failure;
    }
  }

  /**
   * Determines whether the prerequisites a trait declares hold.
   *
   * A teardown calls this instead of asserting, so an unmet prerequisite
   * never replaces a failure the scenario has already recorded.
   *
   * @param string $trait
   *   The trait whose prerequisites to check. A hook passes '__TRAIT__'.
   */
  protected function prerequisitesMet(string $trait): bool {
    return !$this->prerequisiteFailure($trait, is_hook: FALSE) instanceof \RuntimeException;
  }

  /**
   * Returns the failure of the first declared prerequisite that does not hold.
   *
   * @param string $trait
   *   The trait whose prerequisites to evaluate.
   * @param bool $is_hook
   *   Whether a hook checks the prerequisites.
   *
   * @return \RuntimeException|null
   *   The failure to throw, or NULL when every prerequisite holds.
   */
  protected function prerequisiteFailure(string $trait, bool $is_hook): ?\RuntimeException {
    $registry = $this->getBackendRegistry();

    foreach ((new PrerequisiteReader())->read($this, $trait) as $prerequisite) {
      if (!$registry->hasCapability($prerequisite->capability)) {
        $backends = array_keys($registry->getScenarioBackends());
        $detail = sprintf('Backends available to this scenario, in order: %s.', $backends === [] ? 'none' : implode(', ', $backends));

        return new UnsupportedBackendActionException($this->prerequisiteMessage($trait, $prerequisite, $is_hook, $detail));
      }

      // The backend is resolved even for a capability without a check, so a
      // later check runs through that backend.
      $backend = $this->anyBackendFor($prerequisite->capability);

      if ($prerequisite->check instanceof \Closure && !($prerequisite->check)($backend)) {
        return new \RuntimeException($this->prerequisiteMessage($trait, $prerequisite, $is_hook));
      }
    }

    return NULL;
  }

  /**
   * Builds the message of a prerequisite that does not hold.
   *
   * A trait that declares an 'enabled' option can be switched off instead of
   * having its prerequisite met. That stops its hooks but never a step, so a
   * hook's message names both switches and a step's names neither.
   *
   * @param string $trait
   *   The trait that declares the prerequisite.
   * @param \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite $prerequisite
   *   The prerequisite that does not hold.
   * @param bool $is_hook
   *   Whether a hook checks the prerequisite.
   * @param string|null $detail
   *   What was found instead, as a sentence, or NULL for nothing to add.
   */
  protected function prerequisiteMessage(string $trait, Prerequisite $prerequisite, bool $is_hook, ?string $detail = NULL): string {
    $name = $this->traitName($trait);
    $message = sprintf('%s requires that %s, which does not hold.', $name, $prerequisite->description);

    if ($detail !== NULL) {
      $message .= ' ' . $detail;
    }

    if (!$is_hook) {
      return $message;
    }

    $group = $this->getOptionResolver()->groupFor($name);

    if ($group !== NULL) {
      $message .= sprintf(' Meet the prerequisite, or switch %s off with the "%s.%s" option or the "@%s%s" tag.', $name, $group, Option::ENABLED, TagOverrides::SKIP_TAG_PREFIX, $name);
    }

    return $message;
  }

  /**
   * Returns a trait's short name.
   *
   * @param string $trait
   *   The trait name, fully qualified or short.
   */
  protected function traitName(string $trait): string {
    $separator = strrpos($trait, '\\');

    return $separator === FALSE ? $trait : substr($trait, $separator + 1);
  }

}
