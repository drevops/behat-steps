<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\user\Entity\Role;

/**
 * Trait RoleTrait.
 *
 * Role-related steps.
 *
 * @package DrevOps\BehatSteps
 */
trait RoleTrait {

  /**
   * Roles ids.
   *
   * @var array
   */
  protected array $rolesNeedClean = [];

  /**
   * Create a single role with specified permissions.
   *
   * @Given role :name with permissions :permissions
   */
  public function roleCreateSingle(string $name, string $permissions) {
    $permissions = array_map(trim(...), explode(',', $permissions));

    $rid = strtolower($name);
    $name = trim($name);

    $existing_role = Role::load($rid);
    if ($existing_role) {
      $existing_role->delete();
    }

    $role = \Drupal::entityTypeManager()->getStorage('user_role')->create([
      'id' => $rid,
      'label' => $name,
    ]);
    $saved = $role->save();

    if ($saved === SAVED_NEW) {
      user_role_grant_permissions($role->id(), $permissions);
      // Mark for clean later.
      $this->rolesNeedClean[$role->id()] = $role->id();

      return Role::load($role->id());

    }

    throw new \RuntimeException(sprintf('Failed to create a role with "%s" permission(s).', implode(', ', $permissions)));
  }

  /**
   * Create multiple roles from the specified table.
   *
   * @Given roles:
   */
  public function roleCreateMultiple(TableNode $table): void {
    foreach ($table->getHash() as $hash) {
      if (!isset($hash['name'])) {
        throw new \RuntimeException('Missing required column "name"');
      }

      $permissions = $hash['permissions'] ?: '';
      $this->roleCreateSingle($hash['name'], $permissions);
    }
  }

  /**
   * Remove all roles after scenario run.
   *
   * @AfterScenario
   */
  public function roleCleanAll(AfterScenarioScope $scope): void {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    foreach ($this->rolesNeedClean as $rid) {
      $role = Role::load($rid);
      if ($role) {
        $role->delete();
      }
    }

    $this->rolesNeedClean = [];
  }

}
