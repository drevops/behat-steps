<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Manager;

use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;

/**
 * Interface for classes that authenticate users during tests.
 */
interface AuthenticationManagerInterface {

  /**
   * Logs in as the given user.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $user
   *   The user stub to log in.
   */
  public function logIn(EntityStubInterface $user): void;

  /**
   * Logs the current user out.
   */
  public function logOut(): void;

  /**
   * Determines whether a user is already logged in for this session.
   */
  public function loggedIn(): bool;

}
