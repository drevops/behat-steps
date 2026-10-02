<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\Context\UserAwareInterface;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Helper\Drupal\AuthTrait;
use DrevOps\BehatSteps\Helper\Drupal\StaticCacheTrait;

/**
 * Context exposing the registries and helpers the step vocabulary fills.
 */
class TestableRawContext extends WebRawContext implements UserAwareInterface {

  use AuthTrait;
  use StaticCacheTrait;

  /**
   * Returns the stubs created during the scenario.
   *
   * @return array<int, \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface>
   *   The stubs, in creation order.
   */
  public function getCreatedStubs(): array {
    return $this->createdStubs;
  }

  /**
   * Seeds the creation registry.
   *
   * @param array<int, \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface> $stubs
   *   The stubs to register as created.
   */
  public function setCreatedStubs(array $stubs): void {
    $this->createdStubs = $stubs;
  }

  /**
   * Seeds the role registry.
   *
   * @param array<int, string> $roles
   *   The role names to register as created.
   */
  public function setRoles(array $roles): void {
    $this->roles = $roles;
  }

  /**
   * Returns the roles still registered for cleanup.
   *
   * @return array<int, string>
   *   The role names.
   */
  public function getRoles(): array {
    return $this->roles;
  }

  /**
   * Public bridge to the protected vocabulary resolver.
   */
  public function callResolveVocabularyMachineName(string $identifier): string {
    return $this->entityLifecycleResolveVocabularyMachineName($identifier);
  }

}
