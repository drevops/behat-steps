<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Capability;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Capability: set when a user last accessed the site and last logged in.
 *
 * Writing either time takes a save of the account. A backend that drives users
 * through commands may not be able to do that, so this interface is separate
 * from 'UserCapabilityInterface'.
 */
interface UserActivityCapabilityInterface {

  /**
   * Sets the time a user last accessed the site.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The user stub. Either the saved-entity slot or a 'uid' value must be
   *   populated so the backend can resolve the account.
   * @param int $timestamp
   *   The time, as a Unix timestamp.
   *
   * @throws \RuntimeException
   *   When the stub carries no id, or no user has the id.
   */
  public function setUserLastAccessTime(EntityStubInterface $stub, int $timestamp): void;

  /**
   * Sets the time a user last logged in.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The user stub. Either the saved-entity slot or a 'uid' value must be
   *   populated so the backend can resolve the account.
   * @param int $timestamp
   *   The time, as a Unix timestamp.
   *
   * @throws \RuntimeException
   *   When the stub carries no id, or no user has the id.
   */
  public function setUserLastLoginTime(EntityStubInterface $stub, int $timestamp): void;

}
