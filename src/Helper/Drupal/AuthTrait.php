<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper\Drupal;

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
trait AuthTrait {

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
  public function authCleanUsers(AfterScenarioScope $scope): void {
    if (!$this->shouldCleanup() || $this->skipTag('cleanUsers', $scope)) {
      return;
    }

    $user_manager = $this->authGetUserManager();

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
    if ($this->authGetManager() instanceof FastLogoutInterface) {
      $this->authLogout(TRUE);
    }
    elseif (!$user_manager->currentUserIsAnonymous()) {
      $this->authLogout();
    }
  }

  /**
   * Removes any created roles.
   */
  #[AfterScenario]
  public function authCleanRoles(AfterScenarioScope $scope): void {
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
  public function authSetUserManager(UserManagerInterface $user_manager): void {
    $this->userManager = $user_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function authSetManager(AuthenticationManagerInterface $authentication_manager): void {
    $this->authenticationManager = $authentication_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function authGetManager(): AuthenticationManagerInterface {
    if (!$this->authenticationManager instanceof AuthenticationManagerInterface) {
      throw new \RuntimeException('The authentication manager is available only after Behat has initialized the context.');
    }

    return $this->authenticationManager;
  }

  /**
   * {@inheritdoc}
   */
  public function authGetUserManager(): UserManagerInterface {
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
  public function authUserCreate(EntityStubInterface $stub): EntityStubInterface {
    $this->entityDispatchHooks(BeforeUserCreateScope::class, $stub);
    $this->entityDispatchHooks(BeforeEntityCreateScope::class, $stub);

    $driver = $this->driverFor(UserCapabilityInterface::class);
    $this->entityParseCreatedFields($stub, $driver, ['role']);

    $scalars = $this->entityCaptureScalarBaseFields($stub);
    $driver->userCreate($stub);
    $this->entityRestoreScalarBaseFields($stub, $scalars);

    // Register before the post-create hooks run: a hook that throws still
    // leaves the user behind, and cleanup removes only registered stubs.
    $this->authGetUserManager()->addUser($stub);

    $this->entityDispatchHooks(AfterUserCreateScope::class, $stub);
    $this->entityDispatchHooks(AfterEntityCreateScope::class, $stub);

    return $stub;
  }

  /**
   * Logs the given user in.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $user
   *   The user stub to log in.
   */
  public function authLogin(EntityStubInterface $user): void {
    $this->authGetManager()->logIn($user);
  }

  /**
   * Logs the current user out.
   *
   * @param bool $fast
   *   Reset the session directly where the manager supports it.
   */
  public function authLogout(bool $fast = FALSE): void {
    $authentication_manager = $this->authGetManager();

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
  public function authLoggedIn(): bool {
    return $this->authGetManager()->loggedIn();
  }

}
