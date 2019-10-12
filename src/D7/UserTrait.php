<?php

namespace IntegratedExperts\BehatSteps\D7;

use Behat\Gherkin\Node\TableNode;

/**
 * Trait UserTrait.
 *
 * @package IntegratedExperts\BehatSteps\D7
 */
trait UserTrait {

  /**
   * Visit profile page of the user with provided name.
   *
   * @code
   * When I visit user "johndoe" profile
   * @endcode
   *
   * @When I visit user :name profile
   */
  public function userVisitProfile($name) {
    $user = user_load_by_name($name);

    if (empty($user)) {
      throw new \Exception(sprintf('Unable to find user with name "%s"', $name));
    }

    $path = $this->locatePath('/user/' . $user->uid);
    print $path;
    $this->getSession()->visit($path);
  }

  /**
   * Remove users with provided fields.
   *
   * @code
   * Given no users:
   * | mail                |
   * | johndoe@example.com |
   * | janedoe@example.com |
   *
   * Given no users:
   * | name    |
   * | johndoe |
   * | janedoe |
   * @endcode
   *
   * @Given no users:
   */
  public function userDelete(TableNode $usersTable) {
    foreach ($usersTable->getHash() as $userHash) {
      $user = NULL;
      if (isset($userHash['mail'])) {
        $user = user_load_by_mail($userHash['mail']);
      }
      elseif (isset($userHash['name'])) {
        $user = user_load_by_name($userHash['name']);
      }

      if ($user) {
        user_delete($user->uid);
        $this->getUserManager()->removeUser($user->name);
      }
    }
  }

  /**
   * Assert that a user with provided name has all specified roles assigned.
   *
   * @code
   * Then user "johndoe" has "administrator" role assigned
   *
   * Then user "johndoe" has "administrator, editor" roles assigned
   * @endcode
   *
   * @Then user :name has :roles role(s) assigned
   */
  public function userAssertHasRoles($name, $roles) {
    $user = $this->userGetByName($name);

    $roles = explode(',', $roles);
    $roles = array_map(function ($value) {
      return trim($value);
    }, $roles);

    if (count(array_intersect($roles, $user->roles)) != count($roles)) {
      throw new \Exception(sprintf('User "%s" does not have role(s) "%s", but has roles "%s"', $name, implode('", "', $roles), implode('", "', $user->roles)));
    }
  }

  /**
   * Assert that a user with provided name has none of specified roles assigned.
   *
   * @code
   * Then user "johndoe" does not have "administrator" role assigned
   *
   * Then user "johndoe" does not have "administrator, editor" roles assigned
   * @endcode
   *
   * @Then user :name does not have :roles role(s) assigned
   */
  public function userAssertHasNoRoles($name, $roles) {
    $user = $this->userGetByName($name);

    $roles = explode(',', $roles);
    $roles = array_map(function ($value) {
      return trim($value);
    }, $roles);

    if (count(array_intersect($roles, $user->roles)) > 0) {
      throw new \Exception(sprintf('User "%s" should not have roles(s) "%s", but has "%s"', $name, implode('", "', $roles), implode('", "', $user->roles)));
    }
  }

  /**
   * Assert that user with specified account has status.
   *
   * @code
   * Then user "johndoe" has "active" status
   * @endcode
   *
   * @Then user :name has :status status
   */
  public function userAssertHasStatus($name, $status) {
    $status = $status == 'active';

    $user = user_load_by_name($name);
    if (!$user) {
      throw new \Exception(sprintf('Unable to find user with name "%s"', $name));
    }

    if ($user->status != $status) {
      throw new \Exception(sprintf('User "%s" is expected to have status "%s", but has status "%s"', $name, $status ? 'active' : 'blocked', $user->status ? 'active' : 'blocked'));
    }
  }

  /**
   * Get user by name.
   */
  protected function userGetByName($name) {
    if (is_object($name)) {
      return $name;
    }

    $users = user_load_multiple([], ['name' => $name], TRUE);
    $user = reset($users);

    if (!$user) {
      throw new \RuntimeException(sprintf('Unable to find user with name "%s"', $name));
    }

    return $user;
  }

}
