<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Gherkin\Node\TableNode;
use Drupal\user\Entity\User;

/**
 * Trait UserTrait.
 *
 * User-related steps.
 *
 * @package DrevOps\BehatSteps\D7
 */
trait UserTrait {

  use DateTrait;

  /**
   * Visit profile page of the specified user.
   *
   * @When I visit user :name profile
   */
  public function userVisitProfile(string $name): void {
    $users = $this->userLoadMultiple(['name' => $name]);

    if (empty($users)) {
      throw new \RuntimeException(sprintf('User "%s" does not exist.', $name));
    }

    $user = reset($users);

    $this->visitPath('/user/' . $user->id());
  }

  /**
   * Visit edit page of the current user.
   *
   * @When I go to my profile edit page
   */
  public function userVisitOwnProfile(): void {
    $user = $this->getUserManager()->getCurrentUser();

    if ($user instanceof \stdClass) {
      $id = $user->uid;
    }
    else {
      throw new \RuntimeException('Require user to login before visiting profile page.');
    }

    $this->visitPath(sprintf('/user/%s/edit', $id));
  }

  /**
   * Visit edit page of the specified user.
   *
   * @When I edit user :name profile
   */
  public function userEditProfile(string $name): void {
    $users = $this->userLoadMultiple(['name' => $name]);

    if (empty($users)) {
      throw new \RuntimeException(sprintf('User "%s" does not exist.', $name));
    }

    $user = reset($users);

    $this->visitPath('/user/' . $user->id() . '/edit');
  }

  /**
   * Remove users specified in the table.
   *
   * @Given no users:
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

      if (!empty($users)) {
        $user = reset($users);
        $user->delete();
        $this->getUserManager()->removeUser($user->getAccountName());
      }
    }
  }

  /**
   * Assert that a user has roles assigned.
   *
   * @Then user :name has :roles role(s) assigned
   */
  public function userAssertHasRoles(string $name, string $roles): void {
    $users = $this->userLoadMultiple(['name' => $name]);

    if (empty($users)) {
      throw new \RuntimeException(sprintf('User "%s" does not exist.', $name));
    }

    $user = reset($users);

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
   * @Then user :name does not have :roles role(s) assigned
   */
  public function userAssertHasNoRoles(string $name, string $roles): void {
    $users = $this->userLoadMultiple(['name' => $name]);

    if (empty($users)) {
      throw new \RuntimeException(sprintf('User "%s" does not exist.', $name));
    }

    $user = reset($users);

    $roles = explode(',', $roles);
    $roles = array_map(function ($value): string {
      return trim($value);
    }, $roles);

    if (count(array_intersect($roles, $user->getRoles())) > 0) {
      throw new \Exception(sprintf('User "%s" should not have roles(s) "%s", but has "%s".', $name, implode('", "', $roles), implode('", "', $user->getRoles())));
    }
  }

  /**
   * Assert that a user is active or not.
   *
   * @Then user :name has :status status
   */
  public function userAssertHasStatus(string $name, string $status): void {
    if (!in_array($status, ['active', 'blocked'])) {
      throw new \Exception(sprintf('Invalid status "%s".', $status));
    }

    $users = $this->userLoadMultiple(['name' => $name]);

    if (empty($users)) {
      throw new \RuntimeException(sprintf('User "%s" does not exist.', $name));
    }

    $user = reset($users);

    if ($status === 'active') {
      if (!$user->isActive()) {
        throw new \Exception(sprintf('User "%s" is expected to have status "active", but has status "blocked".', $name));
      }
    }
    elseif ($user->isActive()) {
      throw new \Exception(sprintf('User "%s" is expected to have status "blocked", but has status "active".', $name));
    }
  }

  /**
   * Set a password for a user.
   *
   * @Then I set user :user password to :password
   */
  public function userSetPassword(string $name, string $password): void {
    if (empty($password)) {
      throw new \RuntimeException('Password must be not empty.');
    }

    $users = $this->userLoadMultiple(['name' => $name]);
    if (empty($users)) {
      $users = $this->userLoadMultiple(['mail' => $name]);
    }

    if (empty($users)) {
      throw new \RuntimeException(sprintf('Unable to find a user with name or email "%s".', $name));
    }

    $user = reset($users);

    $user->setPassword($password)->save();
  }

  /**
   * Set last access time for user.
   *
   * @Then the last access time of user :name is :time
   */
  public function setUserLastAccess(string $name, string $time): void {
    $users = $this->userLoadMultiple(['name' => $name]);

    if (empty($users)) {
      throw new \RuntimeException(sprintf('User "%s" does not exist.', $name));
    }

    $user = reset($users);

    $timestamp = (int) static::dateRelativeProcessValue($time, time());
    $user->setLastAccessTime($timestamp)->save();
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

}
