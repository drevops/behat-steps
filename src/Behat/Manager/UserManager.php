<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Manager;

use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;

/**
 * Default implementation of the user manager service.
 */
class UserManager implements UserManagerInterface {

  /**
   * The user stub representing the currently logged in user.
   */
  protected EntityStubInterface|false $user = FALSE;

  /**
   * An array of user stubs representing users created during the test.
   *
   * @var array<string, \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface>
   */
  protected array $users = [];

  /**
   * {@inheritdoc}
   */
  public function getCurrentUser(): EntityStubInterface|false {
    return $this->user;
  }

  /**
   * {@inheritdoc}
   */
  public function setCurrentUser(EntityStubInterface|false $user): void {
    $this->user = $user;
  }

  /**
   * {@inheritdoc}
   */
  public function addUser(EntityStubInterface $user): void {
    $name = (string) $user->getValue('name');
    $this->users[$name] = $user;
  }

  /**
   * {@inheritdoc}
   */
  public function removeUser(string $name): void {
    unset($this->users[$name]);
  }

  /**
   * {@inheritdoc}
   */
  public function getUser(string $name): EntityStubInterface {
    if (!isset($this->users[$name])) {
      throw new \InvalidArgumentException(sprintf('No user with %s name is registered with the driver.', $name));
    }

    return $this->users[$name];
  }

  /**
   * {@inheritdoc}
   */
  public function getUsers(): array {
    return $this->users;
  }

  /**
   * {@inheritdoc}
   */
  public function clearUsers(): void {
    $this->user = FALSE;
    $this->users = [];
  }

  /**
   * {@inheritdoc}
   */
  public function hasUsers(): bool {
    return $this->users !== [];
  }

  /**
   * {@inheritdoc}
   */
  public function currentUserIsAnonymous(): bool {
    return $this->user === FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function currentUserHasRole(string $role): bool {
    if (!$this->user instanceof EntityStubInterface) {
      return FALSE;
    }

    $current_role = $this->user->getValue('role');

    if ($current_role === NULL || $current_role === '') {
      return FALSE;
    }

    $held = array_map(trim(...), explode(',', (string) $current_role));

    foreach (explode(',', $role) as $wanted) {
      if (!in_array(trim($wanted), $held, TRUE)) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
