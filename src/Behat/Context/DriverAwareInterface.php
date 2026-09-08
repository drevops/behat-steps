<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context;

use Behat\Behat\Context\Context;
use Behat\Testwork\Hook\HookDispatcher;
use DrevOps\BehatSteps\Behat\Manager\AuthenticationManagerInterface;
use DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface;
use DrevOps\BehatSteps\Behat\Manager\UserManagerInterface;
use DrevOps\BehatSteps\Behat\ParametersAwareInterface;

/**
 * Contract for contexts wired to the driver manager and its collaborators.
 *
 * @see \DrevOps\BehatSteps\Behat\Context\Initializer\DriverAwareInitializer
 */
interface DriverAwareInterface extends Context, ParametersAwareInterface {

  /**
   * Sets the driver manager.
   */
  public function setDriverManager(DriverManagerInterface $driverManager): void;

  /**
   * Returns the driver manager.
   *
   * @throws \RuntimeException
   *   When the context has not been initialized by Behat yet.
   */
  public function getDriverManager(): DriverManagerInterface;

  /**
   * Sets the hook dispatcher.
   */
  public function setDispatcher(HookDispatcher $dispatcher): void;

  /**
   * Sets the user manager.
   */
  public function setUserManager(UserManagerInterface $userManager): void;

  /**
   * Returns the user manager.
   */
  public function getUserManager(): UserManagerInterface;

  /**
   * Sets the authentication manager.
   */
  public function setAuthenticationManager(AuthenticationManagerInterface $authenticationManager): void;

  /**
   * Returns the authentication manager.
   */
  public function getAuthenticationManager(): AuthenticationManagerInterface;

}
