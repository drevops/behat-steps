<?php

namespace DrevOps\BehatSteps;

use Behat\Gherkin\Node\TableNode;
use Drupal\user\Entity\User;

/**
 * Trait UserTrait.
 *
 * @package DrevOps\BehatSteps\D7
 */
trait UserTrait {

  /**
   * @When I visit user :name profile
   */
  public function userVisitProfile($name) {
    $user = $this->userGetByName($name);

    if (empty($user)) {
      throw new \Exception(sprintf('Unable to find user with name "%s"', $name));
    }

    $path = $this->locatePath('/user/' . $user->id());
    $this->getSession()->visit($path);
  }

  /**
   * @When I go to my edit profile page
   */
  public function userVisitOwnProfilePage() {
    $user = $this->getUserManager()->getCurrentUser();
    if ($user === FALSE) {
      throw new \RuntimeException('Require user to login before visiting profile page.');
    }
    $page = '/user/' . $user->uid . '/edit';
    $this->visitPath($page);
  }

  /**
   * @Given no users:
   */
  public function userDelete(TableNode $usersTable) {
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
      catch (\Exception $exception) {
        // User may not exist - do nothing.
      }

      if ($user) {
        $user->delete();
        $this->getUserManager()->removeUser($user->getAccountName());
      }
    }
  }

  /**
   * @Then user :name has :roles role(s) assigned
   */
  public function userAssertHasRoles($name, $roles) {
    $user = $this->userGetByName($name);

    $roles = explode(',', $roles);
    $roles = array_map(function ($value) {
      return trim($value);
    }, $roles);

    if (count(array_intersect($roles, $user->getRoles())) != count($roles)) {
      throw new \Exception(sprintf('User "%s" does not have role(s) "%s", but has roles "%s"', $name, implode('", "', $roles), implode('", "', $user->getRoles())));
    }
  }

  /**
   * @Then user :name does not have :roles role(s) assigned
   */
  public function userAssertHasNoRoles($name, $roles) {
    $user = $this->userGetByName($name);

    $roles = explode(',', $roles);
    $roles = array_map(function ($value) {
      return trim($value);
    }, $roles);

    if (count(array_intersect($roles, $user->getRoles())) > 0) {
      throw new \Exception(sprintf('User "%s" should not have roles(s) "%s", but has "%s"', $name, implode('", "', $roles), implode('", "', $user->getRoles())));
    }
  }

  /**
   * @Then user :name has :status status
   */
  public function userAssertHasStatus($name, $status) {
    $status = $status == 'active';

    $user = $this->userGetByName($name);
    if (!$user) {
      throw new \Exception(sprintf('Unable to find user with name %s', $name));
    }

    if ($user->isActive() != $status) {
      throw new \Exception(sprintf('User "%s" is expected to have status "%s", but has status "%s"', $name, $status ? 'active' : 'blocked', $user->isActive() ? 'active' : 'blocked'));
    }
  }

  /**
   * @Then I set user :user password to :password
   */
  public function userSetPassword($name, $password) {
    if (empty($password)) {
      throw new \RuntimeException('Password must be not empty.');
    }

    try {
      /** @var \Drupal\user\UserInterface $user */
      $user = $this->userGetByName($name);
    }
    catch (\Exception $e1) {
      try {
        $user = $this->userGetByMail($name);
      }
      catch (\Exception $e2) {
        throw new \Exception(sprintf('Unable to find a user with name or email "%s".', $name));
      }
    }

    $user->setPassword($password)->save();
  }

  /**
   * Get user by name.
   */
  protected function userGetByName($name) {
    if (is_object($name)) {
      return $name;
    }

    $users = $this->userLoadMultiple(['name' => $name]);
    $user = reset($users);

    if (!$user) {
      throw new \RuntimeException(sprintf('Unable to find user with name "%s"', $name));
    }

    return $user;
  }

  /**
   * Get user by mail.
   */
  protected function userGetByMail($mail) {
    if (is_object($mail)) {
      return $mail;
    }

    $users = $this->userLoadMultiple(['mail' => $mail]);
    $user = reset($users);

    if (!$user) {
      throw new \RuntimeException(sprintf('Unable to find user with mail "%s"', $mail));
    }

    return $user;
  }

  /**
   * Helper to load multiple users with specified conditions.
   *
   * @param array $conditions
   *   Conditions keyed by field names.
   *
   * @return array
   *   Array of loaded user objects.
   */
  protected function userLoadMultiple(array $conditions = []) {
    $query = \Drupal::entityQuery('user');
    foreach ($conditions as $k => $v) {
      $and = $query->andConditionGroup();
      $and->condition($k, $v);
      $query->condition($and);
    }

    $ids = $query->execute();

    return $ids ? User::loadMultiple($ids) : [];
  }

}
