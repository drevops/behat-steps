<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Manage Drupal users with role and permission assignments.
 *
 * - Create user accounts
 * - Create user roles
 * - Visit user profile pages for editing and deletion.
 * - Assert user roles and permissions.
 * - Assert user account status (active/inactive).
 */
trait UserTrait {

  /**
   * Remove users specified in a table.
   *
   * @code
   * Given the following users do not exist:
   *  | name |
   *  | John |
   *  | Jane |
   * @endcode
   *
   * @code
   *  Given the following users do not exist:
   *   | mail             |
   *   | john@example.com |
   *   | jane@example.com |
   * @endcode
   *
   * @Given the following users do not exist:
   */
  public function userDelete(TableNode $usersTable): void {
    foreach ($usersTable->getHash() as $userHash) {
      $users = [];

      if (isset($userHash['mail'])) {
        $users = $this->userLoadMultiple(['mail' => $userHash['mail']]);
      }
      elseif (isset($userHash['name'])) {
        $users = $this->userLoadMultiple(['name' => $userHash['name']]);
      }

      foreach ($users as $user) {
        $user->delete();
        $this->getUserManager()->removeUser($user->getAccountName());
      }
    }
  }

  /**
   * Set a password for a user.
   *
   * @code
   * Given the password for the user "John" is "password"
   * @endcode
   *
   * @Given the password for the user :name is :password
   */
  public function userSetPassword(string $name, string $password): void {
    if (empty($password)) {
      throw new \RuntimeException('Password must not be empty.');
    }

    $user = $this->userLoadByName($name);

    $user->setPassword($password)->save();
  }

  /**
   * Set last access time for a user.
   *
   * @code
   * Given the last access time for the user "John" is "Friday, 22 November 2024 13:46:14"
   * @endcode
   *
   * @code
   * Given the last access time for the user "John" is "1732319174"
   * @endcode
   *
   * @Given the last access time for the user :name is :datetime
   */
  public function userSetLastAccessTime(string $name, string $datetime): void {
    $user = $this->userLoadByName($name);

    $timestamp = is_numeric($datetime) ? intval($datetime) : strtotime($datetime);

    if ($timestamp === FALSE) {
      throw new \RuntimeException('Invalid date format.');
    }

    $user->setLastAccessTime($timestamp)->save();
  }

  /**
   * Set last login time for a user.
   *
   * @code
   * Given the last login time for the user "John" is "Friday, 22 November 2024 13:46:14"
   * @endcode
   *
   * @code
   * Given the last login time for the user "John" is "1732319174"
   * @endcode
   *
   * @Given the last login time for the user :name is :datetime
   */
  public function userSetLastLoginTime(string $name, string $datetime): void {
    $user = $this->userLoadByName($name);

    $timestamp = is_numeric($datetime) ? intval($datetime) : strtotime($datetime);

    if ($timestamp === FALSE) {
      throw new \RuntimeException('Invalid date format.');
    }

    $user->setLastLoginTime($timestamp)->save();
  }

  /**
   * Visit the profile page of the specified user.
   *
   * @code
   * When I visit "John" user profile page
   * @endcode
   *
   * @When I visit :name user profile page
   */
  public function userVisitProfile(string $name): void {
    $this->userVisitActionPage($name);
  }

  /**
   * Visit the profile page of the current user.
   *
   * @code
   * When I visit my own user profile page
   * @endcode
   *
   * @When I visit my own user profile page
   */
  public function userVisitOwnProfile(): void {
    $this->userVisitActionPage('current');
  }

  /**
   * Visit the profile edit page of the specified user.
   *
   * @code
   * When I visit "John" user profile edit page
   * @endcode
   *
   * @When I visit :name user profile edit page
   */
  public function userEditProfile(string $name): void {
    $this->userVisitActionPage($name, '/edit');
  }

  /**
   * Visit the profile edit page of the current user.
   *
   * @code
   * When I visit my own user profile edit page
   * @endcode
   *
   * @When I visit my own user profile edit page
   */
  public function userEditOwnProfile(): void {
    $this->userVisitActionPage('current', '/edit');
  }

  /**
   * Visit the profile delete page of the specified user.
   *
   * @code
   * When I visit "John" user profile delete page
   * @endcode
   *
   * @When I visit :name user profile delete page
   */
  public function userDeleteProfile(string $name): void {
    $this->userVisitActionPage($name, '/cancel');
  }

  /**
   * Visit the profile delete page of the current user.
   *
   * @code
   * When I visit my own user profile delete page
   * @endcode
   *
   * @When I visit my own user profile delete page
   */
  public function userDeleteOwnProfile(): void {
    $this->userVisitActionPage('current', '/cancel');
  }

  /**
   * Assert that a user has roles assigned.
   *
   * @code
   * Then the user "John" should have the roles "administrator, editor" assigned
   * @endcode
   *
   * @Then the user :name should have the role(s) :roles assigned
   */
  public function userAssertHasRoles(string $name, string $roles): void {
    $user = $this->userLoadByName($name);

    $roles = explode(',', $roles);
    $roles = array_map(function ($value): string {
      return trim($value);
    }, $roles);

    if (count(array_intersect($roles, $user->getRoles())) !== count($roles)) {
      throw new \Exception(sprintf('User "%s" does not have role(s) "%s", but has roles "%s".', $name, implode('", "', $roles), implode('", "', $user->getRoles())));
    }
  }

  /**
   * Assert that a user does not have roles assigned.
   *
   * @code
   * Then the user "John" should not have the roles "administrator, editor" assigned
   * @endcode
   *
   * @Then the user :name should not have the role(s) :roles assigned
   */
  public function userAssertHasNoRoles(string $name, string $roles): void {
    $user = $this->userLoadByName($name);

    $roles = explode(',', $roles);
    $roles = array_map(function ($value): string {
      return trim($value);
    }, $roles);

    if (count(array_intersect($roles, $user->getRoles())) > 0) {
      throw new \Exception(sprintf('User "%s" should not have roles(s) "%s", but has "%s".', $name, implode('", "', $roles), implode('", "', $user->getRoles())));
    }
  }

  /**
   * Assert that a user is blocked.
   *
   * @code
   * Then the user "John" should be blocked
   * @endcode
   *
   * @Then the user :name should be blocked
   */
  public function userAssertIsBlocked(string $name): void {
    $user = $this->userLoadByName($name);

    if ($user->isActive()) {
      throw new \Exception(sprintf('User "%s" is expected to be blocked, but they are not.', $name));
    }
  }

  /**
   * Assert that a user is not blocked.
   *
   * @code
   * Then the user "John" should not be blocked
   * @endcode
   *
   * @Then the user :name should not be blocked
   */
  public function userAssertIsNotBlocked(string $name): void {
    $user = $this->userLoadByName($name);

    if (!$user->isActive()) {
      throw new \Exception(sprintf('User "%s" is expected to not be blocked, but they are.', $name));
    }
  }

  /**
   * Load multiple users with specified conditions.
   *
   * @param array<string,string> $conditions
   *   Conditions keyed by field names.
   *
   * @return array<int,\Drupal\user\UserInterface>
   *   Array of loaded user objects.
   */
  protected function userLoadMultiple(array $conditions = []): array {
    $query = \Drupal::entityQuery('user')->accessCheck(FALSE);

    foreach ($conditions as $k => $v) {
      $and = $query->andConditionGroup();
      $and->condition($k, $v);
      $query->condition($and);
    }

    $ids = $query->execute();

    return $ids ? User::loadMultiple($ids) : [];
  }

  /**
   * Load a user by name.
   *
   * @param string $name
   *   The user name.
   *
   * @return \Drupal\user\UserInterface|null
   *   The loaded user object or NULL if not found.
   */
  protected function userLoadByName(string $name): ?UserInterface {
    $users = $this->userLoadMultiple(['name' => $name]);

    if (empty($users)) {
      throw new \RuntimeException(sprintf('User with name "%s" does not exist.', $name));
    }

    return reset($users);
  }

  /**
   * Visit a user action page.
   *
   * @param string $name
   *   The user name.
   * @param string $action_subpath
   *   The action subpath.
   */
  protected function userVisitActionPage(string $name, string $action_subpath = ''): void {
    if ($name === 'current') {
      /** @var \Drupal\user\UserInterface $user */
      $user = $this->getUserManager()->getCurrentUser();

      if (!$user instanceof \StdClass) {
        throw new \RuntimeException('Current user is not logged in.');
      }

      $uid = $user->uid;
    }
    else {
      $user = $this->userLoadByName($name);
      $uid = $user->id();
    }

    $this->visitPath('/user/' . $uid . $action_subpath);
  }

  /**
   * Create a single role with specified permissions.
   *
   * @code
   * Given the role "Content Manager" with the permissions "access content, create article content, edit any article content"
   * @endcode
   *
   * @Given the role :role_name with the permissions :permissions
   */
  public function userCreateRole(string $role_name, string $permissions): void {
    $permissions = array_map(trim(...), explode(',', $permissions));

    $rid = strtolower($role_name);
    $role_name = trim($role_name);

    $existing_role = Role::load($rid);
    if ($existing_role) {
      $existing_role->delete();
    }

    /** @var \Drupal\user\RoleInterface $role */
    $role = \Drupal::entityTypeManager()->getStorage('user_role')->create([
      'id' => $rid,
      'label' => $role_name,
    ]);
    $saved = $role->save();

    if ($saved !== SAVED_NEW) {
      throw new \RuntimeException(sprintf('Failed to create a role with "%s" permission(s).', implode(', ', $permissions)));
    }
    $this->roles[(string) $role->id()] = (string) $role->id();

    user_role_grant_permissions($role->id(), $permissions);
  }

  /**
   * Create multiple roles from the specified table.
   *
   * @code
   * Given the following roles:
   * | name              | permissions                              |
   * | Content Editor    | access content, create article content   |
   * | Content Approver  | access content, edit any article content |
   * @endcode
   *
   * @Given the following roles:
   */
  public function userCreateRoles(TableNode $table): void {
    foreach ($table->getHash() as $hash) {
      if (!isset($hash['name'])) {
        throw new \RuntimeException('Missing required column "name"');
      }

      $permissions = $hash['permissions'] ?: '';
      $this->userCreateRole($hash['name'], $permissions);
    }
  }

}
