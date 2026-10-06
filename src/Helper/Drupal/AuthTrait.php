<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Hook\AfterScenario;
use DrevOps\BehatSteps\Backend\Capability\BatchCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Behat\Auth\AuthenticatorInterface;
use DrevOps\BehatSteps\Behat\Auth\FastLogoutInterface;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterEntityCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterUserCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeEntityCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeUserCreateScope;
use DrevOps\BehatSteps\Behat\Registry\UserRegistryInterface;

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
   * Removes the users the scenario created, then its roles.
   *
   * The role cleanup runs even when removing the users throws, and that
   * failure is rethrown afterwards.
   *
   * 'BEHAT_STEPS_DISABLE_CLEANUP' leaves the failing scenario's state intact,
   * session included, so the early-return guard skips the logout as well.
   * Later scenarios in the same run inherit that login.
   *
   * @throws \RuntimeException
   *   When removing the users and removing the roles both fail. The message
   *   names both failures, and the user failure is the previous exception.
   */
  #[AfterScenario]
  public function authAfterScenario(AfterScenarioScope $scope): void {
    if (!$this->shouldCleanup() || $this->skipTag(__TRAIT__, $scope)) {
      return;
    }

    try {
      $this->authCleanUsers();
    }
    catch (\Throwable $exception) {
      try {
        $this->authCleanRoles();
      }
      catch (\Throwable $roles_exception) {
        throw new \RuntimeException(sprintf('Removing the created users failed: %s' . PHP_EOL . 'Removing the created roles failed: %s', $exception->getMessage(), $roles_exception->getMessage()), 0, $exception);
      }

      throw $exception;
    }

    $this->authCleanRoles();
  }

  /**
   * Removes any created users.
   */
  protected function authCleanUsers(): void {
    $user_registry = $this->authGetUserRegistry();

    // Resolving a backend bootstraps it, so a scenario that created no users
    // never boots one during teardown.
    if ($user_registry->hasUsers() && $this->getBackendRegistry()->hasCapability(UserCapabilityInterface::class)) {
      $backend = $this->backendFor(UserCapabilityInterface::class);

      foreach ($user_registry->getUsers() as $user) {
        $backend->deleteUser($user);
      }

      if ($backend instanceof BatchCapabilityInterface) {
        $backend->processBatch();
      }

      $user_registry->clearUsers();
    }

    // A scenario can log in as a pre-existing user without calling
    // authCreateUser(), so the auth state is reset even when it created no
    // users.
    // Otherwise the next scenario starts with stale session state.
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
  protected function authCleanRoles(): void {
    if ($this->roles === []) {
      return;
    }

    if (!$this->getBackendRegistry()->hasCapability(RoleCapabilityInterface::class)) {
      return;
    }

    $backend = $this->backendFor(RoleCapabilityInterface::class);

    foreach ($this->roles as $role) {
      $backend->deleteRole($role);
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
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The user stub.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The same stub, now flagged as saved.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException
   *   When no backend in the scenario's order can create users.
   */
  public function authCreateUser(EntityStubInterface $stub): EntityStubInterface {
    $this->entityLifecycleDispatchHooks(BeforeUserCreateScope::class, $stub);
    $this->entityLifecycleDispatchHooks(BeforeEntityCreateScope::class, $stub);

    $backend = $this->backendFor(UserCapabilityInterface::class);
    $this->entityLifecycleParseCreatedFields($stub, $backend, ['role']);

    $scalars = $this->entityLifecycleCaptureScalarBaseFields($stub);
    $backend->createUser($stub);
    $this->entityLifecycleRestoreScalarBaseFields($stub, $scalars);

    // Cleanup removes only registered stubs. A post-create hook that throws
    // leaves the saved user in place, so the stub is registered first.
    $this->authGetUserRegistry()->addUser($stub);

    $this->entityLifecycleDispatchHooks(AfterUserCreateScope::class, $stub);
    $this->entityLifecycleDispatchHooks(AfterEntityCreateScope::class, $stub);

    return $stub;
  }

  /**
   * Logs the given user in.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $user
   *   The user stub to log in.
   */
  public function authLogin(EntityStubInterface $user): void {
    $this->authGetAuthenticator()->login($user);
  }

  /**
   * Logs the current user out.
   *
   * @param bool $is_fast
   *   Reset the session directly where the authenticator supports it.
   */
  public function authLogout(bool $is_fast = FALSE): void {
    $authenticator = $this->authGetAuthenticator();

    if ($is_fast && $authenticator instanceof FastLogoutInterface) {
      $authenticator->fastLogout();
    }
    else {
      $authenticator->logout();
    }
  }

  /**
   * Determines whether a user is logged in for this session.
   */
  public function authIsLoggedIn(): bool {
    return $this->authGetAuthenticator()->isLoggedIn();
  }

}
