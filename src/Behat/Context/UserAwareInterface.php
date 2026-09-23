<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context;

use Behat\Behat\Context\Context;
use DrevOps\BehatSteps\Behat\Manager\UserManagerInterface;

/**
 * Contract for contexts that track the users a scenario created.
 *
 * Separate from 'DriverAwareInterface' because the user manager indexes
 * created users for the teardown that removes them, which only the Drupal
 * half runs.
 *
 * @see \DrevOps\BehatSteps\Behat\Context\Initializer\DriverAwareInitializer
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
