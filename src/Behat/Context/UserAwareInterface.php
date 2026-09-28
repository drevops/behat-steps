<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context;

use Behat\Behat\Context\Context;
use DrevOps\BehatSteps\Behat\Manager\AuthenticationManagerInterface;
use DrevOps\BehatSteps\Behat\Manager\UserManagerInterface;

/**
 * Contract for a context that logs users in and tracks the ones it creates.
 *
 * A trait cannot implement an interface, so a context composing
 * 'AuthTrait' declares this one to receive the user manager. The
 * context initializer injects into nothing else, so a suite that creates no
 * users never builds one.
 *
 * @see \DrevOps\BehatSteps\Helper\Drupal\AuthTrait
 */
interface UserAwareInterface extends Context {

  /**
   * Sets the user manager.
   *
   * @internal
   *   Injection point called by the context initializer.
   */
  public function authSetUserManager(UserManagerInterface $user_manager): void;

  /**
   * Returns the user manager.
   */
  public function authGetUserManager(): UserManagerInterface;

  /**
   * Sets the manager that logs a user in and out.
   *
   * @internal
   *   Injection point called by the context initializer.
   */
  public function authSetManager(AuthenticationManagerInterface $authentication_manager): void;

  /**
   * Returns the manager that logs a user in and out.
   */
  public function authGetManager(): AuthenticationManagerInterface;

}
