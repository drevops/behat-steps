<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Driver\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Steps\Generic\HelperTrait;
use Drupal\Core\Url;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\OneTimeAuthentication;
use Drupal\user\UserInterface;

/**
 * Manage Drupal users with role and permission assignments.
 *
 * - Create user accounts
 * - Create user roles
 * - Visit user profile pages for editing and deletion.
 * - Assert user roles.
 * - Assert user account status (active/inactive).
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\RawContext
 */
trait UserTrait {

  use HelperTrait;

  /**
   * Remove users specified in a table.
   *
   * @code
   * Given the following users do not exist:
   *   | name |
   *   | John |
   *   | Jane |
   * @endcode
   *
   * @code
   *  Given the following users do not exist:
   *    | mail             |
   *    | john@example.com |
   *    | jane@example.com |
   * @endcode
   */
  #[Given('the following users do not exist:')]
  public function userDelete(TableNode $table): void {
    foreach ($table->getHash() as $user_hash) {
      $users = [];

      if (isset($user_hash['mail'])) {
        $users = $this->userLoadMultiple(['mail' => $user_hash['mail']]);
      }
      elseif (isset($user_hash['name'])) {
        $users = $this->userLoadMultiple(['name' => $user_hash['name']]);
      }

      foreach ($users as $user) {
        $user->delete();
        $this->getUserManager()->removeUser($user->getAccountName());
      }
    }
  }

  /**
   * Create users with vertical field format.
   *
   * Supports both single and multiple entity creation using vertical table
   * format where fields are listed in rows instead of columns.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   Vertical format table with field names in first column.
   *
   * @code
   *   Given the following users with fields exist:
   *     | name  | [TEST] user1         | [TEST] user2         |
   *     | mail  | user1@example.com    | user2@example.com    |
   *     | roles | editor               | author               |
   * @endcode
   */
  #[Given('the following users with fields exist:')]
  public function userCreateWithFields(TableNode $table): void {
    $entities = $this->helperTransposeVerticalTable($table);
    $horizontal_table = $this->helperBuildHorizontalTable($entities);
    $this->userCreateMultiple($horizontal_table);
  }

  /**
   * Create users from a table of field values.
   *
   * Each row becomes one user; each column is a base property or a field. A
   * `roles` column takes a comma-separated list, assigned after the account is
   * saved. A row without a `pass` column gets a random password.
   *
   * @code
   *   Given the following users exist:
   *     | name         | mail              | roles  |
   *     | [TEST] user1 | user1@example.com | editor |
   * @endcode
   */
  #[Given('the following users exist:')]
  public function userCreateMultiple(TableNode $table): void {
    $driver = $this->getDriver();

    if (!$driver instanceof UserCapabilityInterface) {
      throw new \RuntimeException(sprintf('The active Drupal driver "%s" does not support user creation.', $driver::class));
    }

    foreach ($table->getHash() as $values) {
      $roles = '';

      if (isset($values['roles'])) {
        $roles = (string) $values['roles'];
        unset($values['roles']);
      }

      // A blank cell reads as "no password given", not as an empty password,
      // which the account could not be created with.
      if (empty($values['pass'])) {
        $values['pass'] = $this->getRandom()->name();
      }

      $stub = new EntityStub('user', NULL, $values);
      $this->userCreate($stub);

      $this->userAssignRoles($driver, $stub, $roles);
    }
  }

  /**
   * Log the current user out so the session is anonymous.
   *
   * @code
   * Given the user is anonymous
   * @endcode
   */
  #[Given('the user is anonymous')]
  public function userLogOutSession(): void {
    $this->logout(TRUE);
  }

  /**
   * Set a password for a user.
   *
   * @code
   * Given the password for the user "John" is "password"
   * @endcode
   */
  #[Given('the password for the user :name is :password')]
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
   */
  #[Given('the last access time for the user :name is :datetime')]
  public function userSetLastAccessTime(string $name, string $datetime): void {
    $user = $this->userLoadByName($name);

    $timestamp = is_numeric($datetime) ? (int) $datetime : strtotime($datetime);

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
   */
  #[Given('the last login time for the user :name is :datetime')]
  public function userSetLastLoginTime(string $name, string $datetime): void {
    $user = $this->userLoadByName($name);

    $timestamp = is_numeric($datetime) ? (int) $datetime : strtotime($datetime);

    if ($timestamp === FALSE) {
      throw new \RuntimeException('Invalid date format.');
    }

    $user->setLastLoginTime($timestamp)->save();
  }

  /**
   * Create a single role with specified permissions.
   *
   * @code
   * Given the role "Content Manager" has the permissions "access content, create article content, edit any article content"
   * @endcode
   */
  #[Given('the role :role_name has the permissions :permissions')]
  public function userCreateRole(string $role_name, string $permissions): void {
    $this->drupal();

    $permissions = $this->helperSplitCommaSeparated($permissions);

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

    // @codeCoverageIgnoreStart
    if ($saved !== SAVED_NEW) {
      throw new \RuntimeException(sprintf('Failed to create a role with "%s" permission(s).', implode(', ', $permissions)));
    }
    // @codeCoverageIgnoreEnd
    $this->roles[] = (string) $role->id();

    user_role_grant_permissions($role->id(), $permissions);
  }

  /**
   * Create multiple roles from the specified table.
   *
   * @code
   * Given the following roles exist:
   *   | name              | permissions                              |
   *   | Content Editor    | access content, create article content   |
   *   | Content Approver  | access content, edit any article content |
   * @endcode
   */
  #[Given('the following roles exist:')]
  public function userCreateRoles(TableNode $table): void {
    foreach ($table->getHash() as $hash) {
      if (!isset($hash['name'])) {
        throw new \RuntimeException('Missing required column "name".');
      }

      $permissions = $hash['permissions'] ?: '';
      $this->userCreateRole($hash['name'], $permissions);
    }
  }

  /**
   * Create a user with the roles and log in as them.
   *
   * Several roles are given as a comma-separated list. The `authenticated`
   * role is implied by having an account, so it is not assigned.
   *
   * @code
   * When I log in as a user with the "editor" role
   * When I log in as a user with the "editor, admin" roles
   * @endcode
   */
  #[When('I log in as a user with the :roles role(s)')]
  public function userLogInWithRoles(string $roles): void {
    $this->userCreateAndLogIn($roles);
  }

  /**
   * Create a user with the roles and fields, and log in as them.
   *
   * @code
   *   When I log in as a user with the "editor" role and the following fields:
   *     | field_user_name    | John  |
   *     | field_user_surname | Smith |
   * @endcode
   */
  #[When('I log in as a user with the :roles role(s) and the following fields:')]
  public function userLogInWithRolesAndFields(string $roles, TableNode $fields): void {
    $this->userCreateAndLogIn($roles, $fields->getRowsHash());
  }

  /**
   * Create a role carrying the permissions, then log in as a user with it.
   *
   * Several permissions are given as a comma-separated list.
   *
   * @code
   * When I log in as a user with the "administer nodes" permission
   * When I log in as a user with the "administer nodes, access content" permissions
   * @endcode
   */
  #[When('I log in as a user with the :permissions permission(s)')]
  public function userLogInWithPermissions(string $permissions): void {
    $driver = $this->getDriver();

    if (!$driver instanceof RoleCapabilityInterface || !$driver instanceof UserCapabilityInterface) {
      throw new \RuntimeException(sprintf('The active Drupal driver "%s" does not support role and user management.', $driver::class));
    }

    $role = $driver->roleCreate(array_filter(array_map(trim(...), explode(',', $permissions))));
    $this->roles[] = $role;

    $stub = $this->userBuildStub();
    $this->userCreate($stub);
    $driver->userAddRole($stub, $role);

    $this->login($stub);
  }

  /**
   * Log in as an existing user created earlier in the scenario.
   *
   * @code
   * When I log in as the user "[TEST] user1"
   * @endcode
   */
  #[When('I log in as the user :name')]
  public function userLogInAs(string $name): void {
    $this->login($this->getUserManager()->getUser($name));
  }

  /**
   * Log the current user out.
   *
   * @code
   * When I log out
   * @endcode
   */
  #[When('I log out')]
  public function userLogOut(): void {
    $this->logout(TRUE);
  }

  /**
   * Visit the profile page of the specified user.
   *
   * @code
   * When I visit "John" user profile page
   * @endcode
   */
  #[When('I visit :name user profile page')]
  public function userVisitProfile(string $name): void {
    $this->userVisitActionPage($name);
  }

  /**
   * Visit the profile page of the current user.
   *
   * @code
   * When I visit my own user profile page
   * @endcode
   */
  #[When('I visit my own user profile page')]
  public function userVisitOwnProfile(): void {
    $this->userVisitActionPage('current');
  }

  /**
   * Visit the profile edit page of the specified user.
   *
   * @code
   * When I visit "John" user profile edit page
   * @endcode
   */
  #[When('I visit :name user profile edit page')]
  public function userEditProfile(string $name): void {
    $this->userVisitActionPage($name, '/edit');
  }

  /**
   * Visit the profile edit page of the current user.
   *
   * @code
   * When I visit my own user profile edit page
   * @endcode
   */
  #[When('I visit my own user profile edit page')]
  public function userEditOwnProfile(): void {
    $this->userVisitActionPage('current', '/edit');
  }

  /**
   * Visit the profile delete page of the specified user.
   *
   * @code
   * When I visit "John" user profile delete page
   * @endcode
   */
  #[When('I visit :name user profile delete page')]
  public function userDeleteProfile(string $name): void {
    $this->userVisitActionPage($name, '/cancel');
  }

  /**
   * Visit the profile delete page of the current user.
   *
   * @code
   * When I visit my own user profile delete page
   * @endcode
   */
  #[When('I visit my own user profile delete page')]
  public function userDeleteOwnProfile(): void {
    $this->userVisitActionPage('current', '/cancel');
  }

  /**
   * Visit the password reset link for a user.
   *
   * @code
   * When I visit the password reset link for "admin"
   * When I visit the password reset link for "test_user"
   * @endcode
   */
  #[When('I visit the password reset link for :name')]
  public function userVisitPasswordResetLink(string $name): void {
    $user = $this->userLoadByName($name);
    $this->userVisitPasswordResetLinkForUser($user);
  }

  /**
   * Visit the password reset link for the currently logged-in user.
   *
   * @code
   * When I visit my own password reset link
   * @endcode
   */
  #[When('I visit my own password reset link')]
  public function userVisitOwnPasswordResetLink(): void {
    $current_user = $this->getUserManager()->getCurrentUser();

    if (!$current_user instanceof EntityStubInterface) {
      throw new \RuntimeException('Current user is not logged in.');
    }

    $user = $this->userLoadByName((string) $current_user->getValue('name'));
    $this->userVisitPasswordResetLinkForUser($user);
  }

  /**
   * Assert that a user has roles assigned.
   *
   * @code
   * Then the user "John" should have the roles "administrator, editor" assigned
   * @endcode
   */
  #[Then('the user :name should have the role(s) :roles assigned')]
  public function userAssertHasRoles(string $name, string $roles): void {
    $user = $this->userLoadByName($name);

    $roles = $this->helperSplitCommaSeparated($roles);

    if (count(array_intersect($roles, $user->getRoles())) !== count($roles)) {
      throw new ExpectationException(sprintf('User "%s" does not have role(s) "%s", but has roles "%s".', $name, implode('", "', $roles), implode('", "', $user->getRoles())), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a user does not have roles assigned.
   *
   * @code
   * Then the user "John" should not have the roles "administrator, editor" assigned
   * @endcode
   */
  #[Then('the user :name should not have the role(s) :roles assigned')]
  public function userAssertNotHasRoles(string $name, string $roles): void {
    $user = $this->userLoadByName($name);

    $roles = $this->helperSplitCommaSeparated($roles);

    if (count(array_intersect($roles, $user->getRoles())) > 0) {
      throw new ExpectationException(sprintf('User "%s" should not have role(s) "%s", but has "%s".', $name, implode('", "', $roles), implode('", "', $user->getRoles())), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a user with an email address exists.
   *
   * Performs a case-insensitive match against the user mail property.
   *
   * @code
   * Then the user with the email "alice@example.com" should exist
   * @endcode
   */
  #[Then('the user with the email :mail should exist')]
  public function userAssertExistsByMail(string $mail): void {
    if (!$this->userExistsByMail($mail)) {
      throw new ExpectationException(sprintf('User with email "%s" is expected to exist, but they do not.', $mail), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a user with an email address does not exist.
   *
   * Performs a case-insensitive match against the user mail property.
   *
   * @code
   * Then the user with the email "alice@example.com" should not exist
   * @endcode
   */
  #[Then('the user with the email :mail should not exist')]
  public function userAssertNotExistsByMail(string $mail): void {
    if ($this->userExistsByMail($mail)) {
      throw new ExpectationException(sprintf('User with email "%s" is expected to not exist, but they do.', $mail), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a user is blocked.
   *
   * @code
   * Then the user "John" should be blocked
   * @endcode
   */
  #[Then('the user :name should be blocked')]
  public function userAssertBlocked(string $name): void {
    $user = $this->userLoadByName($name);

    if ($user->isActive()) {
      throw new ExpectationException(sprintf('User "%s" is expected to be blocked, but they are not.', $name), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a user is not blocked.
   *
   * @code
   * Then the user "John" should not be blocked
   * @endcode
   */
  #[Then('the user :name should not be blocked')]
  public function userAssertNotBlocked(string $name): void {
    $user = $this->userLoadByName($name);

    if (!$user->isActive()) {
      throw new ExpectationException(sprintf('User "%s" is expected to not be blocked, but they are.', $name), $this->getSession()->getDriver());
    }
  }

  /**
   * Create a user carrying the roles and extra fields, and log in as them.
   *
   * @param string $roles
   *   One role, or several as a comma-separated list.
   * @param array<string, mixed> $extra_fields
   *   Additional values to set on the account.
   *
   * @throws \RuntimeException
   *   When the active driver cannot assign roles.
   */
  protected function userCreateAndLogIn(string $roles, array $extra_fields = []): void {
    $driver = $this->getDriver();

    if (!$driver instanceof UserCapabilityInterface) {
      throw new \RuntimeException(sprintf('The active Drupal driver "%s" does not support user role assignment.', $driver::class));
    }

    $stub = $this->userBuildStub($extra_fields);
    $this->userCreate($stub);

    $this->userAssignRoles($driver, $stub, $roles);

    $this->login($stub);
  }

  /**
   * Assign the roles named in a comma-separated list to a saved account.
   *
   * @param \DrevOps\BehatSteps\Driver\Capability\UserCapabilityInterface $driver
   *   The driver that performs the assignment.
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The saved user stub.
   * @param string $roles
   *   One role, or several as a comma-separated list. An empty string assigns
   *   nothing.
   */
  protected function userAssignRoles(UserCapabilityInterface $driver, EntityStubInterface $stub, string $roles): void {
    foreach (array_filter(array_map(trim(...), explode(',', $roles))) as $role) {
      // Having an account already carries 'authenticated', and the role is not
      // assignable in its own right.
      if (in_array(strtolower($role), ['authenticated', 'authenticated user'], TRUE)) {
        continue;
      }

      $driver->userAddRole($stub, $role);
    }
  }

  /**
   * Build a user stub with a random name, password and email.
   *
   * @param array<string, mixed> $extra_fields
   *   Additional values to set on the account.
   */
  protected function userBuildStub(array $extra_fields = []): EntityStubInterface {
    $name = (string) $this->getRandom()->name(8);

    $stub = new EntityStub('user', NULL, [
      'name' => $name,
      'pass' => (string) $this->getRandom()->name(16),
      'mail' => $name . '@example.com',
    ]);

    foreach ($extra_fields as $field => $value) {
      $stub->setValue($field, $value);
    }

    return $stub;
  }

  /**
   * Visit the password reset link for a given user object.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user object.
   */
  protected function userVisitPasswordResetLinkForUser(UserInterface $user): void {
    $this->drupal();

    $timestamp = \Drupal::time()->getRequestTime();

    $path = Url::fromRoute('user.reset', [
      'uid' => $user->id(),
      'timestamp' => $timestamp,
      'hash' => \Drupal::service(OneTimeAuthentication::class)->generateHmac($user, $timestamp),
    ])->toString();

    $this->visitPath($path);
  }

  /**
   * Check whether a user with the given email address exists.
   *
   * Performs a case-insensitive match against the user mail property.
   *
   * @param string $mail
   *   The email address to check.
   *
   * @return bool
   *   TRUE if a user with the email exists, FALSE otherwise.
   */
  protected function userExistsByMail(string $mail): bool {
    $this->drupal();

    $ids = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('mail', $mail, 'LIKE')
      ->range(0, 1)
      ->execute();

    return !empty($ids);
  }

  /**
   * Load multiple users with specified conditions.
   *
   * @param array<string, string> $conditions
   *   Conditions keyed by field names.
   *
   * @return array<int, \Drupal\user\UserInterface>
   *   Array of loaded user objects.
   */
  protected function userLoadMultiple(array $conditions = []): array {
    $this->drupal();

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
   *   The loaded user object. The nullable return type is retained for
   *   compatibility, but a missing user raises an exception rather than
   *   returning NULL.
   *
   * @throws \RuntimeException
   *   When no user with the specified name exists.
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
      $user = $this->getUserManager()->getCurrentUser();

      if (!$user instanceof EntityStubInterface) {
        throw new \RuntimeException('Current user is not logged in.');
      }

      // A stub the driver saved carries the entity; one the Drush driver
      // created carries the id as a value instead.
      $uid = $user->getId() ?? $user->getValue('uid');

      if ($uid === NULL || $uid === '' || (int) $uid === 0) {
        throw new \RuntimeException(sprintf('The current user "%s" carries no id, so the profile path cannot be built.', (string) $user->getValue('name')));
      }
    }
    else {
      $user = $this->userLoadByName($name);
      $uid = $user->id();
    }

    $this->visitPath('/user/' . $uid . $action_subpath);
  }

}
