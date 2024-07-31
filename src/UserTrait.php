<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Gherkin\Node\TableNode;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

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
    $user = $this->userGetByName($name);
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
    $user = $this->userGetByName($name);
    $this->visitPath('/user/' . $user->id() . '/edit');
  }

  /**
   * Remove users specified in the table.
   *
   * @Given no users:
   */
  public function userDelete(TableNode $usersTable): void {
    foreach ($usersTable->getHash() as $userHash) {
      $user = NULL;
      try {
        if (isset($userHash['mail'])) {
          $user = $this->userGetByMail($userHash['mail']);
        }
        elseif (isset($userHash['name'])) {
          $user = $this->userGetByName($userHash['name']);
        }
      }
      catch (\Exception) {
        // User may not exist - do nothing.
      }

      if ($user) {
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
    $user = $this->userGetByName($name);

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
    $user = $this->userGetByName($name);

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
    $status = $status === 'active';

    $user = $this->userGetByName($name);

    if ($user->isActive() != $status) {
      throw new \Exception(sprintf('User "%s" is expected to have status "%s", but has status "%s".', $name, $status ? 'active' : 'blocked', $user->isActive() ? 'active' : 'blocked'));
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

    try {
      /** @var \Drupal\user\UserInterface $user */
      $user = $this->userGetByName($name);
    }
    catch (\Exception) {
      try {
        $user = $this->userGetByMail($name);
      }
      catch (\Exception) {
        throw new \Exception(sprintf('Unable to find a user with name or email "%s".', $name));
      }
    }

    $user->setPassword($password)->save();
  }

  /**
   * Set last access time for user.
   *
   * @Then the last access time of user :name is :time
   */
  public function setUserLastAccess(string $name, string $time): void {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->userGetByName($name);
    $timestamp = (int) static::dateRelativeProcessValue($time, time());
    $user->setLastAccessTime($timestamp)->save();
  }

  /**
   * Get user by name.
   */
  protected function userGetByName(string $name): UserInterface {
    $users = $this->userLoadMultiple(['name' => $name]);
    $user = reset($users);

    if (!$user) {
      throw new \RuntimeException(sprintf('Unable to find user with name "%s".', $name));
    }

    return $user;
  }

  /**
   * Get user by mail.
   */
  protected function userGetByMail(string $mail) {
    $users = $this->userLoadMultiple(['mail' => $mail]);
    $user = reset($users);

    if (!$user) {
      throw new \RuntimeException(sprintf('Unable to find user with mail "%s".', $mail));
    }

    return $user;
  }

  /**
   * Load multiple users with specified conditions.
   *
   * @param array $conditions
   *   Conditions keyed by field names.
   *
   * @return array
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
