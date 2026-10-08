<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context;

use Behat\Behat\Context\Context;
use Behat\Testwork\Hook\HookDispatcher;
use DrevOps\BehatSteps\Behat\Auth\BasicAuthenticatorInterface;
use DrevOps\BehatSteps\Behat\Config\TraitOptionResolverFactoryInterface;
use DrevOps\BehatSteps\Behat\Config\TraitOptionResolverInterface;
use DrevOps\BehatSteps\Behat\Http\HttpClientFactoryInterface;
use DrevOps\BehatSteps\Behat\Registry\BackendRegistryInterface;

/**
 * Contract for contexts wired to the backend registry and its collaborators.
 *
 * @see \DrevOps\BehatSteps\Behat\Context\Initializer\BackendAwareInitializer
 */
interface BackendAwareInterface extends Context, ParametersAwareInterface {

  /**
   * Sets the backend registry.
   *
   * @internal
   *   Injection point called by the context initializer.
   */
  public function setBackendRegistry(BackendRegistryInterface $backend_registry): void;

  /**
   * Returns the backend registry.
   *
   * @throws \RuntimeException
   *   When the context has not been initialized by Behat yet.
   */
  public function getBackendRegistry(): BackendRegistryInterface;

  /**
   * Sets the hook dispatcher.
   *
   * @internal
   *   Injection point called by the context initializer.
   */
  public function setHookDispatcher(HookDispatcher $hook_dispatcher): void;

  /**
   * Sets the basic authenticator.
   *
   * @internal
   *   Injection point called by the context initializer.
   */
  public function setBasicAuthenticator(BasicAuthenticatorInterface $basic_authenticator): void;

  /**
   * Returns the basic authenticator.
   */
  public function getBasicAuthenticator(): BasicAuthenticatorInterface;

  /**
   * Sets the factory that builds the detached and bare browsers.
   *
   * @internal
   *   Injection point called by the context initializer.
   */
  public function setHttpClientFactory(HttpClientFactoryInterface $factory): void;

  /**
   * Returns the factory that builds the detached and bare browsers.
   */
  public function getHttpClientFactory(): HttpClientFactoryInterface;

  /**
   * Sets the factory that builds this context's option resolver.
   *
   * Replaces the resolver the context built for itself, so the shared tag
   * registry and schema reader are the ones the container holds.
   *
   * @internal
   *   Injection point called by the context initializer.
   */
  public function setOptionResolverFactory(TraitOptionResolverFactoryInterface $factory): void;

  /**
   * Returns the resolver of the options this context's traits declare.
   */
  public function getOptionResolver(): TraitOptionResolverInterface;

}
