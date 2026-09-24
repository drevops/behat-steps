<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context;

use Behat\Behat\Context\Context;
use Behat\Testwork\Hook\HookDispatcher;
use DrevOps\BehatSteps\Behat\Manager\AuthenticationManagerInterface;
use DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface;
use DrevOps\BehatSteps\Behat\ParametersAwareInterface;

/**
 * Contract for contexts wired to the driver manager and its collaborators.
 *
 * @see \DrevOps\BehatSteps\Behat\Context\Initializer\DriverAwareInitializer
 */
interface DriverAwareInterface extends Context, ParametersAwareInterface {

  /**
   * Sets the driver manager.
   *
   * @internal
   *   Injection point called by the context initializer.
   */
  public function setDriverManager(DriverManagerInterface $driver_manager): void;

  /**
   * Returns the driver manager.
   *
   * @throws \RuntimeException
   *   When the context has not been initialized by Behat yet.
   */
  public function getDriverManager(): DriverManagerInterface;

  /**
   * Sets the hook dispatcher.
   *
   * @internal
   *   Injection point called by the context initializer.
   */
  public function setDispatcher(HookDispatcher $dispatcher): void;

  /**
   * Sets the authentication manager.
   *
   * @internal
   *   Injection point called by the context initializer.
   */
  public function setAuthenticationManager(AuthenticationManagerInterface $authentication_manager): void;

  /**
   * Returns the authentication manager.
   */
  public function getAuthenticationManager(): AuthenticationManagerInterface;

}
