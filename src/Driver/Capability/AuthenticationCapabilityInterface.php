<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Driver\Capability;

use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;

/**
 * Capability: authenticate users on the backend.
 */
interface AuthenticationCapabilityInterface {

  /**
   * Logs a user in.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The user stub. Either the saved-entity slot or a 'uid' value must be
   *   populated so the driver can resolve the account.
   */
  public function login(EntityStubInterface $stub): void;

  /**
   * Logs the current user out.
   */
  public function logout(): void;

}
