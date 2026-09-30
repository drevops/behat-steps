<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context;

use Behat\Behat\Context\Context;
use Behat\Testwork\Hook\HookDispatcher;
use DrevOps\BehatSteps\Behat\Config\TraitOptionResolverFactoryInterface;
use DrevOps\BehatSteps\Behat\Config\TraitOptionResolverInterface;
use DrevOps\BehatSteps\Behat\Manager\BasicAuthenticatorInterface;
use DrevOps\BehatSteps\Behat\Manager\DriverRegistryInterface;
use DrevOps\BehatSteps\Behat\ParametersAwareInterface;

/**
 * Contract for contexts wired to the driver registry and its collaborators.
 *
 * @see \DrevOps\BehatSteps\Behat\Context\Initializer\DriverAwareInitializer
 */
interface DriverAwareInterface extends Context, ParametersAwareInterface {

  /**
   * Sets the driver registry.
   *
   * @internal
   *   Injection point called by the context initializer.
   */
  public function setDriverRegistry(DriverRegistryInterface $driver_registry): void;

  /**
   * Returns the driver registry.
   *
   * @throws \RuntimeException
   *   When the context has not been initialized by Behat yet.
   */
  public function getDriverRegistry(): DriverRegistryInterface;

  /**
   * Sets the hook dispatcher.
   *
   * @internal
   *   Injection point called by the context initializer.
   */
  public function setDispatcher(HookDispatcher $dispatcher): void;

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
