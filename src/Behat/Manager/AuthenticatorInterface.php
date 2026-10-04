<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Manager;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Interface for classes that authenticate users during tests.
 */
interface AuthenticatorInterface {

  /**
   * Logs in as the given user.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $user
   *   The user stub to log in.
   */
  public function login(EntityStubInterface $user): void;

  /**
   * Logs the current user out.
   */
  public function logout(): void;

  /**
   * Determines whether a user is already logged in for this session.
   */
  public function isLoggedIn(): bool;

}
