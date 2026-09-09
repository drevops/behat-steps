<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\ScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Hook\HookDispatcher;
use DrevOps\BehatSteps\Behat\Hook\Attribute\BeforeNodeCreate;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterEntityCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterLanguageCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterNodeCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterTermCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterUserCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeEntityCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeLanguageCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeNodeCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeTermCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeUserCreateScope;
use DrevOps\BehatSteps\Behat\Manager\AuthenticationManagerInterface;
use DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface;
use DrevOps\BehatSteps\Behat\Manager\FastLogoutInterface;
use DrevOps\BehatSteps\Behat\Manager\UserManagerInterface;
use DrevOps\BehatSteps\Behat\ParametersTrait;
use DrevOps\BehatSteps\Driver\Capability\BatchCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\LanguageCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Driver\Core\Field\FieldClassifierInterface;
use DrevOps\BehatSteps\Driver\Core\Field\Parser\EntityFieldParser;
use DrevOps\BehatSteps\Driver\Core\Field\Parser\EntityFieldParserInterface;
use DrevOps\BehatSteps\Driver\DriverInterface;
use DrevOps\BehatSteps\Driver\DrupalDriverInterface;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Driver\Exception\BootstrapException;
use Drupal\Component\Utility\Random;
use Drupal\Core\Entity\EntityInterface;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Base context carrying the scenario lifecycle.
 *
 * Provides driver access, authentication delegation, entity creation and hook
 * dispatch. It registers no step definitions - a consuming FeatureContext
 * extends it and mixes in the step traits that project needs.
 */
class RawContext extends RawMinkContext implements DriverAwareInterface {

  use ParametersTrait;

  /**
   * Driver manager.
   */
  protected ?DriverManagerInterface $driverManager = NULL;

  /**
   * Hook dispatcher.
   */
  protected ?HookDispatcher $dispatcher = NULL;

  /**
   * Authentication manager.
   */
  protected ?AuthenticationManagerInterface $authenticationManager = NULL;

  /**
   * User manager.
   */
  protected ?UserManagerInterface $userManager = NULL;

  /**
   * Tracks every entity stub created during a scenario for cleanup.
   *
   * Users are tracked separately via the user manager because they need
   * lookup-by-name. Everything else (nodes, terms, languages, generic
   * entities) lives here and is removed in reverse order so dependent
   * entities come down before their dependencies.
   *
   * @var array<int, \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface>
   */
  protected array $createdStubs = [];

  /**
   * Roles created during a scenario, so they can be removed after it.
   *
   * @var array<int, string>
   */
  protected array $roles = [];

  /**
   * Converts textual node timestamps into the numeric form storage expects.
   *
   * @throws \RuntimeException
   *   When a timestamp value cannot be read as a date.
   */
  #[BeforeNodeCreate]
  public static function alterNodeParameters(BeforeNodeCreateScope $scope): void {
    $stub = $scope->getStub();

    // Blackbox and Drush drivers route around this entity pipeline entirely,
    // so converting string dates on timestamp fields only means anything for
    // the in-process driver.
    $context = $scope->getContext();

    if (!$context instanceof DriverAwareInterface) {
      return;
    }

    if (!$context->getDriverManager()->getDriver() instanceof DrupalDriverInterface) {
      return;
    }

    foreach (['changed', 'created', 'revision_timestamp'] as $field) {
      $value = $stub->getValue($field);

      if ($value === NULL || $value === '' || is_numeric($value)) {
        continue;
      }

      $timestamp = strtotime((string) $value);

      if ($timestamp === FALSE) {
        throw new \RuntimeException(sprintf('Unable to read the "%s" value "%s" as a date.', $field, (string) $value));
      }

      $stub->setValue($field, $timestamp);
    }
  }

  /**
   * Removes every entity created during the scenario.
   *
   * Walks 'createdStubs' in reverse order so dependent entities (a node
   * referencing a term, say) come down before the entities they reference.
   *
   * Skip the whole pass with '@behat-steps-skip:cleanEntities', or one entity
   * type with '@behat-steps-entity-cleanup-skip:<entity_type_id>'.
   */
  #[AfterScenario]
  public function cleanEntities(AfterScenarioScope $scope): void {
    if (!$this->shouldCleanup() || $this->skipTag('cleanEntities', $scope)) {
      return;
    }

    if ($this->createdStubs === []) {
      return;
    }

    $skip_types = $this->entityCleanupSkippedTypes($scope);
    $driver = $this->getDriver();

    foreach (array_reverse($this->createdStubs) as $stub) {
      if (in_array($stub->getEntityType(), $skip_types, TRUE)) {
        continue;
      }

      $this->deleteStub($stub, $driver);
    }

    $this->createdStubs = [];
  }

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

    $driver = $this->getDriver();
    $user_manager = $this->getUserManager();

    if ($user_manager->hasUsers() && $driver instanceof UserCapabilityInterface) {
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

    $driver = $this->getDriver();

    if (!$driver instanceof RoleCapabilityInterface) {
      return;
    }

    foreach ($this->roles as $role) {
      $driver->roleDelete($role);
    }

    $this->roles = [];
  }

  /**
   * Clears static caches.
   */
  #[AfterScenario('@api')]
  public function clearStaticCaches(): void {
    $driver = $this->getDriver();

    if ($driver instanceof CacheCapabilityInterface) {
      $driver->cacheClearStatic();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setDriverManager(DriverManagerInterface $driverManager): void {
    $this->driverManager = $driverManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getDriverManager(): DriverManagerInterface {
    if (!$this->driverManager instanceof DriverManagerInterface) {
      throw new \RuntimeException('The driver manager is available only after Behat has initialized the context.');
    }

    return $this->driverManager;
  }

  /**
   * {@inheritdoc}
   */
  public function setDispatcher(HookDispatcher $dispatcher): void {
    $this->dispatcher = $dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function setUserManager(UserManagerInterface $userManager): void {
    $this->userManager = $userManager;
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
   * {@inheritdoc}
   */
  public function setAuthenticationManager(AuthenticationManagerInterface $authenticationManager): void {
    $this->authenticationManager = $authenticationManager;
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
   * Returns the active driver.
   *
   * @param string|null $name
   *   The driver name, or NULL for the scenario's default driver.
   */
  public function getDriver(?string $name = NULL): DriverInterface {
    return $this->getDriverManager()->getDriver($name);
  }

  /**
   * Returns the bootstrapped in-process Drupal driver.
   *
   * The sanctioned gateway to Drupal's API: a trait calls it before touching
   * '\Drupal::' statics, because the container exists only once the driver has
   * bootstrapped. Bootstrapping happens on the first call of a scenario and is
   * a no-op afterwards.
   *
   * @throws \DrevOps\BehatSteps\Driver\Exception\BootstrapException
   *   When the scenario runs on a driver that does not bootstrap Drupal
   *   in-process, such as the Blackbox or Drush driver.
   */
  public function drupal(): DrupalDriverInterface {
    $driver = $this->getDriver();

    if (!$driver instanceof DrupalDriverInterface) {
      throw new BootstrapException(sprintf('The step requires Drupal\'s API, which the active driver "%s" does not provide. Tag the scenario "@api" so it runs on the in-process Drupal driver.', $driver::class));
    }

    if (!$driver->isBootstrapped()) {
      $driver->bootstrap();
    }

    return $driver;
  }

  /**
   * Returns the driver's random generator.
   */
  public function getRandom(): Random {
    return $this->getDriver()->getRandom();
  }

  /**
   * Creates a node.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The node stub.
   *
   * @return \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface
   *   The same stub, now flagged as saved.
   */
  public function nodeCreate(EntityStubInterface $stub): EntityStubInterface {
    $this->dispatchHooks(BeforeNodeCreateScope::class, $stub);
    $this->dispatchHooks(BeforeEntityCreateScope::class, $stub);
    $this->parseCreatedEntityFields($stub, ['author']);

    $driver = $this->getContentDriver();

    $scalars = $this->captureScalarBaseFields($stub);
    $driver->nodeCreate($stub);
    $this->restoreScalarBaseFields($stub, $scalars);

    // Register before the post-create hooks run: a hook that throws still
    // leaves the entity behind, and cleanup can only remove what it knows.
    $this->createdStubs[] = $stub;

    $this->dispatchHooks(AfterNodeCreateScope::class, $stub);
    $this->dispatchHooks(AfterEntityCreateScope::class, $stub);

    return $stub;
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
   * @throws \RuntimeException
   *   When the active driver cannot create users.
   */
  public function userCreate(EntityStubInterface $stub): EntityStubInterface {
    $this->dispatchHooks(BeforeUserCreateScope::class, $stub);
    $this->dispatchHooks(BeforeEntityCreateScope::class, $stub);
    $this->parseCreatedEntityFields($stub, ['role']);

    $driver = $this->getDriver();

    if (!$driver instanceof UserCapabilityInterface) {
      throw new \RuntimeException(sprintf('The active Drupal driver "%s" does not support user creation.', $driver::class));
    }

    $scalars = $this->captureScalarBaseFields($stub);
    $driver->userCreate($stub);
    $this->restoreScalarBaseFields($stub, $scalars);

    // Register before the post-create hooks run: a hook that throws still
    // leaves the user behind, and cleanup can only remove what it knows.
    $this->getUserManager()->addUser($stub);

    $this->dispatchHooks(AfterUserCreateScope::class, $stub);
    $this->dispatchHooks(AfterEntityCreateScope::class, $stub);

    return $stub;
  }

  /**
   * Creates a taxonomy term.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The term stub.
   *
   * @return \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface
   *   The same stub, now flagged as saved.
   */
  public function termCreate(EntityStubInterface $stub): EntityStubInterface {
    // The driver loads vocabularies by machine name only, so resolve a human
    // label to one first. The resolution is best-effort - the driver reports
    // a clearer failure than this could.
    $vocabulary = $stub->getValue('vocabulary_machine_name');

    if (!empty($vocabulary)) {
      $stub->setValue('vocabulary_machine_name', $this->resolveVocabularyMachineName((string) $vocabulary));
    }

    // The driver resolves 'parent' as a term name in the same vocabulary, so
    // pass it through unchanged. An empty value is removed, because the field
    // pipeline would try to expand the empty string as an entity reference.
    if ($stub->hasValue('parent') && empty($stub->getValue('parent'))) {
      $stub->removeValue('parent');
    }

    $this->dispatchHooks(BeforeTermCreateScope::class, $stub);
    $this->dispatchHooks(BeforeEntityCreateScope::class, $stub);
    $this->parseCreatedEntityFields($stub, ['vocabulary_machine_name']);

    $driver = $this->getContentDriver();

    $scalars = $this->captureScalarBaseFields($stub);
    $driver->termCreate($stub);
    $this->restoreScalarBaseFields($stub, $scalars);

    // Register before the post-create hooks run: a hook that throws still
    // leaves the term behind, and cleanup can only remove what it knows.
    $this->createdStubs[] = $stub;

    $this->dispatchHooks(AfterTermCreateScope::class, $stub);
    $this->dispatchHooks(AfterEntityCreateScope::class, $stub);

    return $stub;
  }

  /**
   * Creates an entity of a type that has no dedicated method.
   *
   * The stub joins 'createdStubs', so 'cleanEntities()' removes it after the
   * scenario through the driver's 'entityDelete()' fallback.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The entity stub.
   *
   * @return \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface
   *   The same stub, now flagged as saved.
   */
  public function entityCreate(EntityStubInterface $stub): EntityStubInterface {
    $this->dispatchHooks(BeforeEntityCreateScope::class, $stub);
    $this->parseCreatedEntityFields($stub);

    $driver = $this->getContentDriver();

    $scalars = $this->captureScalarBaseFields($stub);
    $driver->entityCreate($stub);
    $this->restoreScalarBaseFields($stub, $scalars);

    // Register before the post-create hook runs: a hook that throws still
    // leaves the entity behind, and cleanup can only remove what it knows.
    $this->createdStubs[] = $stub;

    $this->dispatchHooks(AfterEntityCreateScope::class, $stub);

    return $stub;
  }

  /**
   * Expands a stub's raw Gherkin values into the storage field shape.
   *
   * Table cells arrive as written - a bare scalar, a comma-separated list, or
   * a compound 'key:"value"' cell - and the parser resolves each against the
   * field's own definition before the driver saves the entity.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The stub, mutated in place.
   * @param array<int, string> $ignored_properties
   *   Value names to leave untouched, such as base properties the caller
   *   handles itself.
   *
   * @throws \DrevOps\BehatSteps\Driver\Exception\BootstrapException
   *   When the scenario does not run on the in-process Drupal driver.
   */
  public function parseEntityFields(EntityStubInterface $stub, array $ignored_properties = []): void {
    $classifier = $this->drupal()->getCore()->getFieldClassifier();

    $parser = $this->getFieldParser($stub->getEntityType(), $classifier, $stub->getBundle());
    $parser->ignoring($ignored_properties);

    $stub->setValues($parser->parse($stub->getValues()));
  }

  /**
   * Registers an entity saved outside the create pipeline for cleanup.
   *
   * A step that saves an entity through Drupal's API rather than the driver
   * calls this so the entity joins the same reverse-order teardown. Only the
   * type and id are kept, so cleanup reloads the entity and tolerates a row
   * the scenario already deleted.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The saved entity.
   */
  public function entityRegister(EntityInterface $entity): void {
    $id = $entity->id();
    $id_key = $entity->getEntityType()->getKey('id');

    if ($id === NULL || !is_string($id_key) || $id_key === '') {
      return;
    }

    $this->createdStubs[] = new EntityStub($entity->getEntityTypeId(), $entity->bundle(), [$id_key => $id]);
  }

  /**
   * Creates a language.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   Language stub. Must carry a 'langcode' value.
   *
   * @return \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface|false
   *   The created language stub, or FALSE if the language already existed.
   *
   * @throws \RuntimeException
   *   When the active driver cannot manage languages.
   */
  public function languageCreate(EntityStubInterface $stub): EntityStubInterface|false {
    $this->dispatchHooks(BeforeLanguageCreateScope::class, $stub);

    $driver = $this->getDriver();

    if (!$driver instanceof LanguageCapabilityInterface) {
      throw new \RuntimeException(sprintf('The active Drupal driver "%s" does not support language management.', $driver::class));
    }

    $result = $driver->languageCreate($stub);

    if ($result === FALSE) {
      return FALSE;
    }

    // Register before the post-create hook runs: a hook that throws still
    // leaves the language behind, and cleanup can only remove what it knows.
    $this->createdStubs[] = $result;

    $this->dispatchHooks(AfterLanguageCreateScope::class, $result);

    return $result;
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

  /**
   * Routes a stub to the right per-type driver delete method.
   */
  protected function deleteStub(EntityStubInterface $stub, DriverInterface $driver): void {
    $type = $stub->getEntityType();

    if (in_array($type, ['language', 'configurable_language'], TRUE)) {
      if ($driver instanceof LanguageCapabilityInterface) {
        try {
          $driver->languageDelete($stub);
        }
        catch (\InvalidArgumentException) {
          // The scenario removed the language itself. Deleting a node, a term
          // or a generic entity twice is tolerated, so a language is too.
        }
      }

      return;
    }

    if (!$driver instanceof ContentCapabilityInterface) {
      return;
    }

    match ($type) {
      'node' => $driver->nodeDelete($stub),
      'taxonomy_term' => $driver->termDelete($stub),
      default => $driver->entityDelete($stub),
    };
  }

  /**
   * Determines whether scenario cleanup should run.
   *
   * Set 'BEHAT_STEPS_DISABLE_CLEANUP' to '1', 'true', 'yes', or 'on'
   * (case-insensitive) to skip the AfterScenario teardown of entities, users
   * and roles. Useful for inspecting state left behind by a failing scenario;
   * not intended for CI runs.
   */
  protected function shouldCleanup(): bool {
    $env = getenv('BEHAT_STEPS_DISABLE_CLEANUP');

    if ($env === FALSE || $env === '') {
      return TRUE;
    }

    return !in_array(strtolower(trim($env)), ['1', 'true', 'yes', 'on'], TRUE);
  }

  /**
   * Determines whether a scenario opts out of a hook.
   *
   * One tag form covers every hook in the library:
   * '@behat-steps-skip:<Name>', where '<Name>' is either a hook method name or
   * a trait name. Feature tags and scenario tags are read together, so the tag
   * works on either line.
   *
   * @param string $name
   *   The hook method name or trait name the tag would carry.
   * @param \Behat\Behat\Hook\Scope\ScenarioScope $scope
   *   The scenario scope the hook received.
   *
   * @return bool
   *   TRUE when the scenario or its feature carries the skip tag.
   */
  protected function skipTag(string $name, ScenarioScope $scope): bool {
    $tags = array_merge($scope->getFeature()->getTags(), $scope->getScenario()->getTags());

    return in_array('behat-steps-skip:' . $name, $tags, TRUE);
  }

  /**
   * Collects the entity types named in per-type cleanup bypass tags.
   *
   * @param \Behat\Behat\Hook\Scope\ScenarioScope $scope
   *   The scenario scope the hook received.
   *
   * @return array<int, string>
   *   Entity type ids parsed from
   *   '@behat-steps-entity-cleanup-skip:<entity_type_id>' tags.
   */
  protected function entityCleanupSkippedTypes(ScenarioScope $scope): array {
    $prefix = 'behat-steps-entity-cleanup-skip:';
    $tags = array_merge($scope->getFeature()->getTags(), $scope->getScenario()->getTags());
    $types = [];

    foreach ($tags as $tag) {
      if (str_starts_with($tag, $prefix)) {
        $types[] = substr($tag, strlen($prefix));
      }
    }

    return $types;
  }

  /**
   * Dispatches the hooks registered for a scope.
   *
   * @param class-string<\DrevOps\BehatSteps\Behat\Hook\Scope\BaseEntityScope> $scopeClass
   *   The fully-qualified scope class name.
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The entity stub flowing through the create pipeline.
   *
   * @throws \RuntimeException
   *   When the context has not been initialized by Behat.
   */
  protected function dispatchHooks(string $scopeClass, EntityStubInterface $stub): void {
    if (!$this->dispatcher instanceof HookDispatcher) {
      throw new \RuntimeException('The hook dispatcher is available only after Behat has initialized the context.');
    }

    $environment = $this->getDriverManager()->getEnvironment();

    if (!$environment instanceof Environment) {
      throw new \RuntimeException('Hooks can be dispatched only once a scenario has started.');
    }

    $scope = new $scopeClass($environment, $this, $stub);
    $call_results = $this->dispatcher->dispatchScopeHooks($scope);

    // The dispatcher collects exceptions rather than raising them, so surface
    // the first one here.
    foreach ($call_results as $call_result) {
      $exception = $call_result->getException();

      if ($exception instanceof \Throwable) {
        throw $exception;
      }
    }
  }

  /**
   * Expands a stub's values during creation, when the driver can classify them.
   *
   * Classification reads the site's field definitions, which only the
   * in-process driver exposes. On the Drush driver the values reach the
   * command line as the scenario wrote them, so the pipeline leaves them
   * alone rather than failing a creation the driver can carry out.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The stub, mutated in place.
   * @param array<int, string> $ignored_properties
   *   Value names to leave untouched.
   */
  protected function parseCreatedEntityFields(EntityStubInterface $stub, array $ignored_properties = []): void {
    if (!$this->getDriver() instanceof DrupalDriverInterface) {
      return;
    }

    $this->parseEntityFields($stub, $ignored_properties);
  }

  /**
   * Builds the entity-field parser for one parsing call.
   *
   * Override in the consuming context to swap in a custom implementation.
   *
   * @param string $entity_type
   *   The entity type the values belong to.
   * @param \DrevOps\BehatSteps\Driver\Core\Field\FieldClassifierInterface $classifier
   *   The classifier resolving each value's field definition.
   * @param string|null $bundle
   *   The bundle, or NULL for an entity type without bundles.
   */
  protected function getFieldParser(string $entity_type, FieldClassifierInterface $classifier, ?string $bundle = NULL): EntityFieldParserInterface {
    return new EntityFieldParser($entity_type, $classifier, $bundle);
  }

  /**
   * Resolves a vocabulary identifier to its machine name.
   *
   * Accepts either the machine name (returned as-is) or the human label
   * (looked up via the vocabulary storage). Falls back to the original value
   * when no label matches, leaving the driver to surface a not-found error.
   */
  protected function resolveVocabularyMachineName(string $identifier): string {
    if (!class_exists(Vocabulary::class) || Vocabulary::load($identifier) instanceof Vocabulary) {
      return $identifier;
    }

    foreach (Vocabulary::loadMultiple() as $vocabulary) {
      if ($vocabulary->label() === $identifier) {
        return (string) $vocabulary->id();
      }
    }

    return $identifier;
  }

  /**
   * Captures the scalar values on an entity stub.
   *
   * The driver runs base fields through the field-handler pipeline during
   * create, which casts scalar values such as 'title', 'name', 'mail' or
   * 'pass' to single-element arrays. Downstream code (user manager indexing,
   * login flow, stub matching) expects scalars, so callers snapshot them
   * before the driver call and restore them after.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The entity stub to inspect.
   *
   * @return array<string, scalar>
   *   The scalar values keyed by name.
   */
  protected function captureScalarBaseFields(EntityStubInterface $stub): array {
    return array_filter($stub->getValues(), is_scalar(...));
  }

  /**
   * Restores scalar values previously captured.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The entity stub to mutate.
   * @param array<string, scalar> $scalars
   *   Map of value name to original scalar value.
   */
  protected function restoreScalarBaseFields(EntityStubInterface $stub, array $scalars): void {
    foreach ($scalars as $field => $value) {
      $stub->setValue($field, $value);
    }
  }

  /**
   * Resolves the active driver as a content-capable instance.
   *
   * @throws \RuntimeException
   *   When the active driver does not implement 'ContentCapabilityInterface'.
   */
  protected function getContentDriver(): ContentCapabilityInterface {
    $driver = $this->getDriver();

    if (!$driver instanceof ContentCapabilityInterface) {
      throw new \RuntimeException(sprintf('The active Drupal driver "%s" does not support content creation.', $driver::class));
    }

    return $driver;
  }

}
