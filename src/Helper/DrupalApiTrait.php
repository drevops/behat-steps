<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\ScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Hook\HookDispatcher;
use DrevOps\BehatSteps\Attribute\Helper;
use DrevOps\BehatSteps\Behat\Context\DriverAwareInterface;
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
use DrevOps\BehatSteps\Behat\Manager\FastLogoutInterface;
use DrevOps\BehatSteps\Behat\Manager\UserManagerInterface;
use DrevOps\BehatSteps\Behat\Tag;
use DrevOps\BehatSteps\Driver\Capability\BatchCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\LanguageCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Driver\Core\Field\FieldClassifierInterface;
use DrevOps\BehatSteps\Driver\Core\Field\Parser\EntityFieldParser;
use DrevOps\BehatSteps\Driver\Core\Field\Parser\EntityFieldParserInterface;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Carries the Drupal scenario lifecycle.
 *
 * Owns entity creation and its hooks, the login flow, the teardown that
 * removes the entities, users and roles a scenario created, and the
 * Drupal-side helpers the step traits read: fixture-path expansion, node id
 * queries and the vertical table transform.
 *
 * A class composing this trait implements 'DrupalApiInterface', which is what
 * a Drupal step trait names in its '@phpstan-require-implements' annotation.
 * 'DrupalContext' does both; a project that wants the plumbing without the
 * vocabulary composes this trait onto its own 'WebRawContext' subclass.
 *
 * @see \DrevOps\BehatSteps\Behat\Context\DrupalApiInterface
 * @see \DrevOps\BehatSteps\Behat\Context\DrupalContext
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
#[Helper]
trait DrupalApiTrait {

  /**
   * User manager.
   */
  protected ?UserManagerInterface $userManager = NULL;

  /**
   * Tracks every entity stub created during a scenario for cleanup.
   *
   * Users are tracked separately via the user manager because they need
   * lookup-by-name. Everything else (nodes, terms, languages, generic
   * entities) is stored here and removed in reverse order, so a dependent
   * entity is deleted before the entity it references.
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

    // A driver that writes the node over the command line takes the values as
    // written, so string dates are converted only for a driver that saves them
    // through Drupal's own storage.
    $context = $scope->getContext();

    if (!$context instanceof DriverAwareInterface) {
      return;
    }

    $manager = $context->getDriverManager();

    if (!$manager->hasCapability(ContentCapabilityInterface::class)) {
      return;
    }

    if (!$manager->getDriverFor(ContentCapabilityInterface::class) instanceof CoreCapabilityInterface) {
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
   * Walks 'createdStubs' in reverse order, so a dependent entity such as a
   * node referencing a term is deleted before the entity it references.
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

    foreach (array_reverse($this->createdStubs) as $stub) {
      if (in_array($stub->getEntityType(), $skip_types, TRUE)) {
        continue;
      }

      $this->deleteStub($stub);
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
   * Clears static caches.
   *
   * Only a scenario that reached a cache-capable driver can have left a static
   * cache behind, so a scenario that never touched one is left alone.
   */
  #[AfterScenario]
  public function clearStaticCaches(): void {
    $this->getDriverManager()->getResolvedDriverFor(CacheCapabilityInterface::class)?->cacheClearStatic();
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
  public function getUserManager(): UserManagerInterface {
    if (!$this->userManager instanceof UserManagerInterface) {
      throw new \RuntimeException('The user manager is available only after Behat has initialized the context.');
    }

    return $this->userManager;
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

    $driver = $this->getContentDriver();
    $this->parseCreatedEntityFields($stub, $driver, ['author']);

    $scalars = $this->captureScalarBaseFields($stub);
    $driver->nodeCreate($stub);
    $this->restoreScalarBaseFields($stub, $scalars);

    // Register before the post-create hooks run: a hook that throws still
    // leaves the entity behind, and cleanup removes only registered stubs.
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

    if (!empty($vocabulary) && $this->getDriverManager()->hasCapability(CoreCapabilityInterface::class)) {
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

    $driver = $this->getContentDriver();
    $this->parseCreatedEntityFields($stub, $driver, ['vocabulary_machine_name']);

    $scalars = $this->captureScalarBaseFields($stub);
    $driver->termCreate($stub);
    $this->restoreScalarBaseFields($stub, $scalars);

    // Register before the post-create hooks run: a hook that throws still
    // leaves the term behind, and cleanup removes only registered stubs.
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

    $driver = $this->getContentDriver();
    $this->parseCreatedEntityFields($stub, $driver);

    $scalars = $this->captureScalarBaseFields($stub);
    $driver->entityCreate($stub);
    $this->restoreScalarBaseFields($stub, $scalars);

    // Register before the post-create hook runs: a hook that throws still
    // leaves the entity behind, and cleanup removes only registered stubs.
    $this->createdStubs[] = $stub;

    $this->dispatchHooks(AfterEntityCreateScope::class, $stub);

    return $stub;
  }

  /**
   * Expands a stub's raw Gherkin values into the storage field shape.
   *
   * A table cell is passed as written - a bare scalar, a comma-separated
   * list, or a compound 'key:"value"' cell - and the parser resolves each
   * against the field's own definition before the driver saves the entity.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The stub, mutated in place.
   * @param array<int, string> $ignored_properties
   *   Value names to leave untouched, such as base properties the caller
   *   handles itself.
   *
   * @throws \DrevOps\BehatSteps\Driver\Exception\UnsupportedDriverActionException
   *   When no driver in the scenario's order reaches Drupal's API.
   */
  public function parseEntityFields(EntityStubInterface $stub, array $ignored_properties = []): void {
    $classifier = $this->driverFor(CoreCapabilityInterface::class)->getCore()->getFieldClassifier();

    $parser = $this->getFieldParser($stub->getEntityType(), $classifier, $stub->getBundle());
    $parser->ignoring($ignored_properties);

    $stub->setValues($parser->parse($stub->getValues()));
  }

  /**
   * Registers an entity saved outside the create pipeline for cleanup.
   *
   * An entity saved through Drupal's API rather than the driver joins the
   * same reverse-order teardown. Only the type and id are kept, so cleanup
   * reloads the entity and tolerates a row the scenario already deleted.
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
   * @throws \DrevOps\BehatSteps\Driver\Exception\UnsupportedDriverActionException
   *   When no driver in the scenario's order can manage languages.
   */
  public function languageCreate(EntityStubInterface $stub): EntityStubInterface|false {
    $this->dispatchHooks(BeforeLanguageCreateScope::class, $stub);

    $result = $this->driverFor(LanguageCapabilityInterface::class)->languageCreate($stub);

    if ($result === FALSE) {
      return FALSE;
    }

    // Register before the post-create hook runs: a hook that throws still
    // leaves the language behind, and cleanup removes only registered stubs.
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
  protected function deleteStub(EntityStubInterface $stub): void {
    $type = $stub->getEntityType();
    $manager = $this->getDriverManager();

    if (in_array($type, ['language', 'configurable_language'], TRUE)) {
      if ($manager->hasCapability(LanguageCapabilityInterface::class)) {
        try {
          $this->driverFor(LanguageCapabilityInterface::class)->languageDelete($stub);
        }
        catch (\RuntimeException) {
          // The scenario removed the language itself. Deleting a node, a term
          // or a generic entity twice is tolerated, so a language is too.
        }
      }

      return;
    }

    if (!$manager->hasCapability(ContentCapabilityInterface::class)) {
      return;
    }

    $driver = $this->driverFor(ContentCapabilityInterface::class);

    match ($type) {
      'node' => $driver->nodeDelete($stub),
      'taxonomy_term' => $driver->termDelete($stub),
      default => $driver->entityDelete($stub),
    };
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
    $tags = Tag::all($scope);
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
   * @param class-string<\DrevOps\BehatSteps\Behat\Hook\Scope\BaseEntityScope> $scope_class
   *   The fully-qualified scope class name.
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The entity stub flowing through the create pipeline.
   *
   * @throws \RuntimeException
   *   When the context has not been initialized by Behat.
   */
  protected function dispatchHooks(string $scope_class, EntityStubInterface $stub): void {
    if (!$this->dispatcher instanceof HookDispatcher) {
      throw new \RuntimeException('The hook dispatcher is available only after Behat has initialized the context.');
    }

    $environment = $this->getDriverManager()->getEnvironment();

    if (!$environment instanceof Environment) {
      throw new \RuntimeException('Hooks can be dispatched only once a scenario has started.');
    }

    $scope = new $scope_class($environment, $this, $stub);
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
   * Classification reads the site's field definitions, which only a driver
   * with Drupal bootstrapped exposes. A driver that passes the values to the
   * command line takes them as written, so they are left unparsed instead of
   * failing a creation the driver can perform.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The stub, mutated in place.
   * @param object $driver
   *   The driver that will save the entity.
   * @param array<int, string> $ignored_properties
   *   Value names to leave untouched.
   */
  protected function parseCreatedEntityFields(EntityStubInterface $stub, object $driver, array $ignored_properties = []): void {
    if (!$driver instanceof CoreCapabilityInterface) {
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
    $this->driverFor(CoreCapabilityInterface::class);

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
   * login flow, stub matching) expects scalars, so the values are captured
   * before the driver call and restored after it.
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
   * Resolves the driver that saves entities for this scenario.
   *
   * @throws \DrevOps\BehatSteps\Driver\Exception\UnsupportedDriverActionException
   *   When no driver in the scenario's order can create content.
   */
  protected function getContentDriver(): ContentCapabilityInterface {
    return $this->driverFor(ContentCapabilityInterface::class);
  }

  /**
   * Transpose a vertical table format (field/value columns) to entity arrays.
   *
   * Supports both single and multiple entity creation:
   *
   * Single entity (2 columns):
   *   | name  | John  |
   *   | age   | 30    |
   *
   * Multiple entities (3+ columns):
   *   | name  | John      | Jane      |
   *   | age   | 30        | 25        |
   *
   * Returns:
   *   Single entity: [['name' => 'John', 'age' => '30']]
   *   Multiple entities: [['name' => 'John', 'age' => '30'], ['name' => 'Jane', 'age' => '25']]
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The vertical format table.
   *
   * @return array<int, array<string, string>>
   *   Array of entity data arrays. Each entity is an associative array.
   *
   * @throws \RuntimeException
   *   If table doesn't have at least 2 columns or has no rows.
   */
  public function transposeVerticalTable(TableNode $table): array {
    $rows = $table->getRows();

    $first_row = $rows[0];
    if (count($first_row) < 2) {
      throw new \RuntimeException('Vertical table must have at least 2 columns (field name and value).');
    }

    $field_names = array_column($rows, 0);
    $duplicate_fields = array_filter(array_count_values($field_names), fn(int $count): bool => $count > 1);

    if (!empty($duplicate_fields)) {
      throw new \RuntimeException(sprintf('Duplicate field names found: %s.', implode(', ', array_keys($duplicate_fields))));
    }

    foreach ($field_names as $field_name) {
      if (trim((string) $field_name) === '') {
        throw new \RuntimeException('Field names cannot be empty.');
      }
    }

    $num_entities = count($first_row) - 1;

    $entities = array_fill(0, $num_entities, []);

    foreach ($rows as $row) {
      $field_name = array_shift($row);

      foreach ($row as $index => $value) {
        $entities[$index][$field_name] = $value;
      }
    }

    return $entities;
  }

  /**
   * Convert vertical format entities to horizontal TableNode.
   *
   * @param array<int, array<string, string>> $entities
   *   Array of entity data arrays from transposeVerticalTable().
   *
   * @return \Behat\Gherkin\Node\TableNode
   *   TableNode in horizontal format (first row is headers, subsequent rows
   *   are values). Returns empty TableNode if input is empty.
   */
  public function buildHorizontalTable(array $entities): TableNode {
    // @codeCoverageIgnoreStart
    if (empty($entities)) {
      return new TableNode([]);
    }
    // @codeCoverageIgnoreEnd
    $field_names = array_keys($entities[0]);
    $rows = [$field_names];

    foreach ($entities as $entity) {
      $rows[] = array_values($entity);
    }

    return new TableNode($rows);
  }

  /**
   * Expand fixture file paths for file/image fields on an entity stub.
   *
   * Rewrites fixture paths on 'file' and 'image' field types to absolute
   * paths under the Mink 'files_path' so drupal-driver's FileHandler can read
   * and upload them during entity creation. A path is taken relative to the
   * fixtures directory, so both 'document.pdf' and 'images/photo.png'
   * resolve. Skips expansion when a managed file with the same basename
   * already exists in public:// or private://, so existing files take
   * precedence.
   *
   * @param string $entity_type
   *   The entity type machine name (e.g. 'node', 'media').
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The entity stub mutated in place.
   */
  public function expandEntityFieldsFixtures(string $entity_type, EntityStubInterface $stub): void {
    $files_path = $this->getMinkParameter('files_path');

    if (empty($files_path)) {
      return;
    }

    $resolved_files_path = realpath((string) $files_path);

    if ($resolved_files_path === FALSE || !is_dir($resolved_files_path)) {
      return;
    }

    $fixture_path = rtrim($resolved_files_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if (!$this->getDriverManager()->hasCapability(CoreCapabilityInterface::class)) {
      return;
    }

    $field_types = $this->driverFor(CoreCapabilityInterface::class)->getCore()->getEntityFieldTypes($entity_type);

    foreach ($stub->getValues() as $name => $value) {
      if (empty($field_types[$name]) || ($field_types[$name] !== 'image' && $field_types[$name] !== 'file')) {
        continue;
      }

      // A stub not yet parsed by 'parseEntityFields()' still holds the raw
      // compound cell as written in the Behat table
      // (e.g. 'target_id:"foo.jpg", alt:"A"').
      if (is_string($value) && $this->looksLikeCompoundCell($value)) {
        $rewritten = $this->expandCompoundCellFixtures($value, $fixture_path);

        if ($rewritten !== $value) {
          $stub->setValue($name, $rewritten);
        }

        continue;
      }

      // Parsed shapes produced by 'EntityFieldParser' or the legacy parser:
      // - scalar: 'foo.jpg' (treated as single-value)
      // - scalar list: ['foo.jpg', 'bar.jpg'] (multi-value)
      // - keyed record: ['target_id' => 'foo.jpg', 'alt' => 'A'] (single compound)
      // - list of records: [['target_id' => 'foo.jpg', 'alt' => 'A'], ...] (multi-value compound)
      //
      // Numerically-indexed arrays (lists) are iterated element-by-element so
      // every delta is resolved. Keyed records and bare scalars are wrapped
      // in a single-element list, processed once, and unwrapped when written
      // back to the stub.
      $is_list = is_array($value) && array_is_list($value);
      $records = $is_list ? $value : [$value];
      $mutated = FALSE;

      foreach ($records as $index => $record) {
        $path = is_array($record) ? $record['target_id'] ?? $record[0] ?? NULL : $record;

        if (!is_string($path) || $path === '') {
          continue;
        }

        if ($this->managedFileExists($path)) {
          continue;
        }

        $resolved = $this->resolveFixtureFile($path, $fixture_path);

        if ($resolved === NULL) {
          continue;
        }

        if (is_array($record)) {
          if (array_key_exists('target_id', $record)) {
            $records[$index]['target_id'] = $resolved;
          }
          else {
            $records[$index][0] = $resolved;
          }
        }
        else {
          $records[$index] = $resolved;
        }

        $mutated = TRUE;
      }

      if (!$mutated) {
        continue;
      }

      $stub->setValue($name, $is_list ? $records : $records[0]);
    }
  }

  /**
   * Detect a raw compound cell string of the shape 'key:"..."' or 'key:[...]'.
   *
   * Mirrors the top-level pattern 'EntityFieldParser' uses to enter compound
   * mode.
   */
  protected function looksLikeCompoundCell(string $value): bool {
    return preg_match('/^\s*[a-z_][a-z0-9_]*\s*:\s*[\"\[]/i', $value) === 1;
  }

  /**
   * Rewrite each 'target_id:"path"' segment to embed the fixture path.
   *
   * Only the 'target_id' key is touched and only when the quoted value is not
   * backed by an existing managed file and resolves to a real file under the
   * fixtures dir. Other compound columns (e.g. 'alt', 'description') are left
   * untouched so the parser can still process them.
   */
  protected function expandCompoundCellFixtures(string $value, string $fixture_path): string {
    $callback = function (array $matches) use ($fixture_path): string {
      $path = $matches[2];

      if ($this->managedFileExists($path)) {
        return $matches[0];
      }

      $resolved = $this->resolveFixtureFile($path, $fixture_path);

      return $resolved === NULL ? $matches[0] : $matches[1] . $resolved . $matches[3];
    };

    return (string) preg_replace_callback('/(target_id\s*:\s*")([^"\\\\]+)(")/i', $callback, $value);
  }

  /**
   * Resolve a field value against the fixtures directory.
   *
   * @param string $value
   *   The raw field value: a path relative to the fixtures directory, a
   *   stream URI or an absolute filesystem path.
   * @param string $fixture_path
   *   The resolved fixtures directory, with a trailing separator.
   *
   * @return string|null
   *   The absolute path to the fixture file, or NULL when the value does not
   *   resolve to a file inside the fixtures directory.
   */
  protected function resolveFixtureFile(string $value, string $fixture_path): ?string {
    // drupal-driver resolves stream URIs and absolute paths itself.
    if (str_contains($value, '://')) {
      return NULL;
    }

    if (str_starts_with($value, '/') || str_starts_with($value, '\\') || preg_match('#^[a-z]:[\\\\/]#i', $value) === 1) {
      return NULL;
    }

    if (!is_file($fixture_path . $value)) {
      return NULL;
    }

    $resolved = realpath($fixture_path . $value);

    // is_file() also succeeds for a '..' path that resolves outside the
    // fixtures directory.
    if ($resolved === FALSE || !str_starts_with($resolved, $fixture_path)) {
      return NULL;
    }

    return $resolved;
  }

  /**
   * Check whether a managed file with the given basename already exists.
   *
   * Mirrors drupal-driver FileHandler::resolveExistingFile() for bare
   * basenames so the driver's own lookup is not pre-empted.
   *
   * @param string $basename
   *   Candidate basename (no path separators).
   *
   * @return bool
   *   TRUE when a managed file exists at public://basename or
   *   private://basename.
   */
  protected function managedFileExists(string $basename): bool {
    $this->driverFor(CoreCapabilityInterface::class);

    if (str_contains($basename, '/') || str_contains($basename, '\\')) {
      return FALSE;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('file');

    foreach (['public', 'private'] as $scheme) {
      if ($storage->loadByProperties(['uri' => $scheme . '://' . $basename])) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Load the ids of the nodes of a content type matching the conditions.
   *
   * @param string $content_type
   *   The content type machine name.
   * @param array<string, mixed> $conditions
   *   Conditions keyed by field names.
   *
   * @return array<int, string>
   *   Array of node ids.
   */
  public function loadNodeIds(string $content_type, array $conditions = []): array {
    $this->driverFor(CoreCapabilityInterface::class);

    $query = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', $content_type);

    foreach ($conditions as $field => $value) {
      $and = $query->andConditionGroup();
      $and->condition($field, $value);
      $query->condition($and);
    }

    return $query->execute();
  }

  /**
   * Assert that a module backing a set of steps is enabled.
   *
   * Without the check, a step against a missing module fails with a fatal on
   * an unresolvable class or a raw database error, not a message naming the
   * module.
   *
   * @param string $module
   *   The module machine name.
   * @param string $package
   *   Optional Composer package to name in the message. Pass an empty string
   *   for a module that ships with Drupal core.
   *
   * @throws \RuntimeException
   *   When the module is not enabled.
   */
  public function assertModuleEnabled(string $module, string $package = ''): void {
    $this->driverFor(CoreCapabilityInterface::class);

    // @codeCoverageIgnoreStart
    if (\Drupal::moduleHandler()->moduleExists($module)) {
      return;
    }

    $remedy = $package === ''
      ? 'Enable it as part of the site setup; it ships with Drupal core.'
      : sprintf('Add "%s" to the consumer project\'s composer.json and enable the module as part of the site setup.', $package);

    throw new \RuntimeException(sprintf('The "%s" module is not enabled. %s', $module, $remedy));
    // @codeCoverageIgnoreEnd
  }

}
