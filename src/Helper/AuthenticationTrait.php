<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Hook\AfterScenario;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterEntityCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterUserCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeEntityCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeUserCreateScope;
use DrevOps\BehatSteps\Behat\Manager\AuthenticationManagerInterface;
use DrevOps\BehatSteps\Behat\Manager\FastLogoutInterface;
use DrevOps\BehatSteps\Behat\Manager\UserManagerInterface;
use DrevOps\BehatSteps\Driver\Capability\BatchCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;

/**
 * Creates users and roles, logs them in, and removes them afterwards.
 *
 * The user manager holds the created users rather than the entity registry,
 * because a user is looked up by name. Roles are tracked separately for the
 * same reason.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait AuthenticationTrait {

  use EntityLifecycleTrait;

  /**
   * User manager.
   */
  protected ?UserManagerInterface $userManager = NULL;

  /**
   * Logs a user in and out of the site under test.
   */
  protected ?AuthenticationManagerInterface $authenticationManager = NULL;

  /**
   * Roles created during a scenario, so they can be removed after it.
   *
   * @var array<int, string>
   */
  protected array $roles = [];

  /**
   * Removes any created users.
   *
   * The early-return guard also skips the logout below, because
   * 'BEHAT_STEPS_DISABLE_CLEANUP' is there to leave the failing scenario's
   * state intact, session included.
   *
   * Later scenarios in the same run inherit that login.
   */
  #[AfterScenario]
  public function cleanUsers(AfterScenarioScope $scope): void {
    if (!$this->shouldCleanup() || $this->skipTag('cleanUsers', $scope)) {
      return;
    }

    $user_manager = $this->getUserManager();

    // Resolving a driver bootstraps it, so a scenario that created no users
    // never boots one on the way out.
    if ($user_manager->hasUsers() && $this->getDriverManager()->hasCapability(UserCapabilityInterface::class)) {
      $driver = $this->driverFor(UserCapabilityInterface::class);

      foreach ($user_manager->getUsers() as $user) {
        $driver->userDelete($user);
      }

      if ($driver instanceof BatchCapabilityInterface) {
        $driver->processBatch();
      }

      $user_manager->clearUsers();
    }

    // Reset auth state even when the scenario created no users: a scenario
    // may log in as a pre-existing user without calling userCreate(), leaving
    // stale session state for the next scenario.
    if ($this->getAuthenticationManager() instanceof FastLogoutInterface) {
      $this->logout(TRUE);
    }
    elseif (!$user_manager->currentUserIsAnonymous()) {
      $this->logout();
    }
  }

  /**
   * Removes any created roles.
   */
  #[AfterScenario]
  public function cleanRoles(AfterScenarioScope $scope): void {
    if (!$this->shouldCleanup() || $this->skipTag('cleanRoles', $scope)) {
      return;
    }

    if ($this->roles === []) {
      return;
    }

    if (!$this->getDriverManager()->hasCapability(RoleCapabilityInterface::class)) {
      return;
    }

    $driver = $this->driverFor(RoleCapabilityInterface::class);

    foreach ($this->roles as $role) {
      $driver->roleDelete($role);
    }

    $this->roles = [];
  }

  /**
   * {@inheritdoc}
   */
  public function setUserManager(UserManagerInterface $user_manager): void {
    $this->userManager = $user_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthenticationManager(AuthenticationManagerInterface $authentication_manager): void {
    $this->authenticationManager = $authentication_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationManager(): AuthenticationManagerInterface {
    if (!$this->authenticationManager instanceof AuthenticationManagerInterface) {
      throw new \RuntimeException('The authentication manager is available only after Behat has initialized the context.');
    }

    return $this->authenticationManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserManager(): UserManagerInterface {
    if (!$this->userManager instanceof UserManagerInterface) {
      throw new \RuntimeException('The user manager is available only after Behat has initialized the context.');
    }

    return $this->userManager;
  }

  /**
   * Creates a user.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The user stub.
   *
   * @return \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface
   *   The same stub, now flagged as saved.
   *
   * @throws \DrevOps\BehatSteps\Driver\Exception\UnsupportedDriverActionException
   *   When no driver in the scenario's order can create users.
   */
  public function userCreate(EntityStubInterface $stub): EntityStubInterface {
    $this->dispatchHooks(BeforeUserCreateScope::class, $stub);
    $this->dispatchHooks(BeforeEntityCreateScope::class, $stub);

    $driver = $this->driverFor(UserCapabilityInterface::class);
    $this->parseCreatedEntityFields($stub, $driver, ['role']);

    $scalars = $this->captureScalarBaseFields($stub);
    $driver->userCreate($stub);
    $this->restoreScalarBaseFields($stub, $scalars);

    // Register before the post-create hooks run: a hook that throws still
    // leaves the user behind, and cleanup removes only registered stubs.
    $this->getUserManager()->addUser($stub);

    $this->dispatchHooks(AfterUserCreateScope::class, $stub);
    $this->dispatchHooks(AfterEntityCreateScope::class, $stub);

    return $stub;
  }

  /**
   * Logs the given user in.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $user
   *   The user stub to log in.
   */
  public function login(EntityStubInterface $user): void {
    $this->getAuthenticationManager()->logIn($user);
  }

  /**
   * Logs the current user out.
   *
   * @param bool $fast
   *   Reset the session directly where the manager supports it.
   */
  public function logout(bool $fast = FALSE): void {
    $authentication_manager = $this->getAuthenticationManager();

    if ($fast && $authentication_manager instanceof FastLogoutInterface) {
      $authentication_manager->fastLogout();
    }
    else {
      $authentication_manager->logOut();
    }
  }

  /**
   * Determines whether a user is logged in for this session.
   */
  public function loggedIn(): bool {
    return $this->getAuthenticationManager()->loggedIn();
  }

}
