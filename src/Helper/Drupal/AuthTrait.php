<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Hook\AfterScenario;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterEntityCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterUserCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeEntityCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeUserCreateScope;
use DrevOps\BehatSteps\Behat\Manager\AuthenticatorInterface;
use DrevOps\BehatSteps\Behat\Manager\FastLogoutInterface;
use DrevOps\BehatSteps\Behat\Manager\UserRegistryInterface;
use DrevOps\BehatSteps\Driver\Capability\BatchCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;

/**
 * Creates users and roles, logs them in, and removes them afterwards.
 *
 * The user registry holds the created users rather than the entity registry,
 * because a user is looked up by name. Roles are tracked separately for the
 * same reason.
 *
 * Keep the users and roles a scenario created with tag:
 * `@behat-steps-skip:AuthTrait`.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait AuthTrait {

  use EntityLifecycleTrait;

  /**
   * User registry.
   */
  protected ?UserRegistryInterface $userRegistry = NULL;

  /**
   * Logs a user in and out of the site under test.
   */
  protected ?AuthenticatorInterface $authenticator = NULL;

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
   * 'BEHAT_STEPS_DISABLE_CLEANUP' leaves the failing scenario's state intact,
   * session included. Later scenarios in the same run inherit that login.
   */
  #[AfterScenario]
  public function authCleanUsers(AfterScenarioScope $scope): void {
    if (!$this->shouldCleanup() || $this->skipTag(__TRAIT__, $scope)) {
      return;
    }

    $user_registry = $this->authGetUserRegistry();

    // Resolving a driver bootstraps it, so a scenario that created no users
    // never boots one during teardown.
    if ($user_registry->hasUsers() && $this->getDriverRegistry()->hasCapability(UserCapabilityInterface::class)) {
      $driver = $this->driverFor(UserCapabilityInterface::class);

      foreach ($user_registry->getUsers() as $user) {
        $driver->userDelete($user);
      }

      if ($driver instanceof BatchCapabilityInterface) {
        $driver->processBatch();
      }

      $user_registry->clearUsers();
    }

    // Reset auth state even when the scenario created no users: a scenario
    // may log in as a pre-existing user without calling userCreate(), leaving
    // stale session state for the next scenario.
    if ($this->authGetAuthenticator() instanceof FastLogoutInterface) {
      $this->authLogout(TRUE);
    }
    elseif (!$user_registry->currentUserIsAnonymous()) {
      $this->authLogout();
    }
  }

  /**
   * Removes any created roles.
   */
  #[AfterScenario]
  public function authCleanRoles(AfterScenarioScope $scope): void {
    if (!$this->shouldCleanup() || $this->skipTag(__TRAIT__, $scope)) {
      return;
    }

    if ($this->roles === []) {
      return;
    }

    if (!$this->getDriverRegistry()->hasCapability(RoleCapabilityInterface::class)) {
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
  public function authSetUserRegistry(UserRegistryInterface $user_registry): void {
    $this->userRegistry = $user_registry;
  }

  /**
   * {@inheritdoc}
   */
  public function authSetAuthenticator(AuthenticatorInterface $authenticator): void {
    $this->authenticator = $authenticator;
  }

  /**
   * {@inheritdoc}
   */
  public function authGetAuthenticator(): AuthenticatorInterface {
    if (!$this->authenticator instanceof AuthenticatorInterface) {
      throw new \RuntimeException('The authenticator is available only after Behat has initialized the context.');
    }

    return $this->authenticator;
  }

  /**
   * {@inheritdoc}
   */
  public function authGetUserRegistry(): UserRegistryInterface {
    if (!$this->userRegistry instanceof UserRegistryInterface) {
      throw new \RuntimeException('The user registry is available only after Behat has initialized the context.');
    }

    return $this->userRegistry;
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
    $this->entityLifecycleDispatchHooks(BeforeUserCreateScope::class, $stub);
    $this->entityLifecycleDispatchHooks(BeforeEntityCreateScope::class, $stub);

    $driver = $this->driverFor(UserCapabilityInterface::class);
    $this->entityLifecycleParseCreatedFields($stub, $driver, ['role']);

    $scalars = $this->entityLifecycleCaptureScalarBaseFields($stub);
    $driver->userCreate($stub);
    $this->entityLifecycleRestoreScalarBaseFields($stub, $scalars);

    // Register before the post-create hooks run: a hook that throws still
    // leaves the user behind, and cleanup removes only registered stubs.
    $this->authGetUserRegistry()->addUser($stub);

    $this->entityLifecycleDispatchHooks(AfterUserCreateScope::class, $stub);
    $this->entityLifecycleDispatchHooks(AfterEntityCreateScope::class, $stub);

    return $stub;
  }

  /**
   * Logs the given user in.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $user
   *   The user stub to log in.
   */
  public function authLogin(EntityStubInterface $user): void {
    $this->authGetAuthenticator()->logIn($user);
  }

  /**
   * Logs the current user out.
   *
   * @param bool $fast
   *   Reset the session directly where the authenticator supports it.
   */
  public function authLogout(bool $fast = FALSE): void {
    $authenticator = $this->authGetAuthenticator();

    if ($fast && $authenticator instanceof FastLogoutInterface) {
      $authenticator->fastLogout();
    }
    else {
      $authenticator->logOut();
    }
  }

  /**
   * Determines whether a user is logged in for this session.
   */
  public function authLoggedIn(): bool {
    return $this->authGetAuthenticator()->loggedIn();
  }

}
