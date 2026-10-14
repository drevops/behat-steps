<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures;

use DrevOps\BehatSteps\Backend\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Recording test double for 'UserCapabilityInterface'.
 *
 * Records the roles assigned to a user so a test can assert them without
 * booting a real backend.
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
  public function createUser(EntityStubInterface $stub): EntityStubInterface {
    return $stub;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteUser(EntityStubInterface $stub): void {}

  /**
   * {@inheritdoc}
   */
  public function addUserRole(EntityStubInterface $stub, string $role): void {
    $this->roles[] = $role;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserRoles(EntityStubInterface $stub): array {
    return ['authenticated', ...$this->roles];
  }

  /**
   * {@inheritdoc}
   */
  public function setUserPassword(EntityStubInterface $stub, string $password): void {}

  /**
   * {@inheritdoc}
   */
  public function getOneTimeLoginUrl(EntityStubInterface $stub): string {
    return '/user/reset/' . $stub->getValue('uid') . '/1/hash';
  }

}
