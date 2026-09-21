<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Driver\Fixtures;

use DrevOps\BehatSteps\Driver\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;

/**
 * Recording test double for 'UserCapabilityInterface'.
 *
 * Records the roles assigned to a user so a test can assert them without
 * booting a real driver. Calls to 'userCreate()' and 'userDelete()' are
 * intentional no-ops; only 'userAddRole()' records.
 */
class RecordingUserCapability implements UserCapabilityInterface {

  /**
   * Roles assigned during the test, in the order they were applied.
   *
   * @var array<int, string>
   */
  public array $roles = [];

  /**
   * {@inheritdoc}
   */
  public function userCreate(EntityStubInterface $stub): void {
  }

  /**
   * {@inheritdoc}
   */
  public function userDelete(EntityStubInterface $stub): void {
  }

  /**
   * {@inheritdoc}
   */
  public function userAddRole(EntityStubInterface $stub, string $role): void {
    $this->roles[] = $role;
  }

}
