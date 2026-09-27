<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context;

use Behat\Behat\Context\Context;
use DrevOps\BehatSteps\Behat\Manager\UserManagerInterface;

/**
 * Contract for a context that tracks the users a scenario creates.
 *
 * A trait cannot implement an interface, so a context composing
 * 'AuthenticationTrait' declares this one to receive the user manager. The
 * context initializer injects into nothing else, so a suite that creates no
 * users never builds one.
 *
 * @see \DrevOps\BehatSteps\Helper\AuthenticationTrait
 */
interface UserAwareInterface extends Context {

  /**
   * Sets the user manager.
   *
   * @internal
   *   Injection point called by the context initializer.
   */
  public function setUserManager(UserManagerInterface $user_manager): void;

  /**
   * Returns the user manager.
   */
  public function getUserManager(): UserManagerInterface;

}
