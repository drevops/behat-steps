<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\ScenarioScope;
use Behat\Gherkin\Node\TaggedNodeInterface;
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
use DrevOps\BehatSteps\Driver\DriverInterface;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;
use Drupal\Component\Utility\Random;
use Drupal\Core\Entity\EntityInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

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
   * Per-context option overrides, as the suite declared them.
   *
   * @var array<string, array<string, mixed>>
   */
  protected array $contextConfig = [];

  /**
   * Options resolved against the schemas, NULL until the first read.
   *
   * @var array<string, array<string, mixed>>|null
   */
  protected ?array $contextConfigResolved = NULL;

  /**
   * Declared schemas, keyed by context class name.
   *
   * Reflection over every method of a context composing forty traits is too
   * expensive to repeat per option read, and a class's schemas cannot change
   * within a run.
   *
   * @var array<string, array<string, array<string, array<string, mixed>>>>
   */
  protected static array $contextConfigDeclarationCache = [];

  /**
   * Constructs a RawContext object.
   *
   * @param array<string, array<string, mixed>> $config
   *   Option overrides, keyed by trait group and then by option name. Behat
   *   binds a context argument by parameter name, so a suite declares them
   *   under 'config'.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When a group or an option is not one this context composes, or a value
   *   does not match the type its declaration defaults to.
   */
  public function __construct(array $config = []) {
    // Validated here rather than on first read, so a typo fails while Behat
    // builds the context instead of at the step that would have read it.
    $this->contextConfigMerge($this->contextConfigDefaults(), $config, TRUE);

    $this->contextConfig = $config;
  }

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
  public function setDriverManager(DriverManagerInterface $driver_manager): void {
    $this->driverManager = $driver_manager;
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
   * Returns a driver of this scenario by the name its suite gave it.
   *
   * @param string $name
   *   The tag name the suite lists the driver under.
   */
  public function getDriver(string $name): DriverInterface {
    return $this->getDriverManager()->getDriver($name);
  }

  /**
   * Returns the highest-priority driver providing the given capability.
   *
   * A step names the capability it needs and never a driver, which is what
   * keeps the shipped vocabulary portable: a project that registers its own
   * driver gets the step working the moment that driver implements the
   * interface.
   *
   * @param class-string<T> $capability
   *   The capability interface the caller needs.
   *
   * @return T
   *   The driver, bootstrapped.
   *
   * @throws \DrevOps\BehatSteps\Driver\Exception\UnsupportedDriverActionException
   *   When no driver in the scenario's order implements the capability.
   *
   * @template T of object
   */
  public function driverFor(string $capability): object {
    return $this->getDriverManager()->getDriverFor($capability);
  }

  /**
   * Returns the driver's random generator.
   */
  public function getRandom(): Random {
    return $this->driverFor(DriverInterface::class)->getRandom();
  }

  /**
   * Returns a trait option resolved for this context.
   *
   * The value is taken from the first of these that declares it: the scenario's
   * tags, the feature's tags, this context's 'config' argument, the extension's
   * 'steps' section, the declaration's own default. The two tag layers are read
   * only when a scope is passed, which a hook has and a step does not.
   *
   * @param string $group
   *   The trait group the option belongs to, such as 'javascript'.
   * @param string $key
   *   The option name within the group, such as 'fail_on_errors'.
   * @param \Behat\Behat\Hook\Scope\ScenarioScope|null $scope
   *   The scenario scope a hook received, to read the tag layers.
   *
   * @return mixed
   *   The resolved value.
   *
   * @throws \RuntimeException
   *   When no trait this context composes declares the option.
   */
  public function getOption(string $group, string $key, ?ScenarioScope $scope = NULL): mixed {
    $schema = $this->contextConfigDeclarations();

    if (!isset($schema[$group][$key])) {
      throw new \RuntimeException(sprintf('No trait in %s declares the option "%s.%s". Declared options: %s.', static::class, $group, $key, $this->contextConfigOptionList()));
    }

    $resolved = $this->contextConfigResolve();
    $value = $resolved[$group][$key];

    if (!$scope instanceof ScenarioScope) {
      return $value;
    }

    return $this->contextConfigApplyTags($group, $key, $value, $scope);
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
   * A trait that declares an 'enabled' option is also switched off by that
   * option, so a project turns the trait off for the whole profile or for one
   * context instead of tagging every feature file.
   *
   * @param string $name
   *   The hook method name or trait name the tag would carry.
   * @param \Behat\Behat\Hook\Scope\ScenarioScope $scope
   *   The scenario scope the hook received.
   *
   * @return bool
   *   TRUE when the scenario or its feature carries the skip tag, or the
   *   trait's 'enabled' option resolves to FALSE.
   */
  protected function skipTag(string $name, ScenarioScope $scope): bool {
    $tags = Tag::all($scope);

    if (in_array('behat-steps-skip:' . $name, $tags, TRUE)) {
      return TRUE;
    }

    $group = $this->contextConfigGroupFor($name);

    return $group !== NULL && $this->getOption($group, 'enabled', $scope) === FALSE;
  }

  /**
   * Collects the option declarations of every trait this context composes.
   *
   * A trait declares its options in a '<prefix>ConfigSchema()' method named by
   * the same prefix its other methods carry, so the group name falls out of the
   * method name and a consuming project's own trait participates without being
   * registered anywhere.
   *
   * @return array<string, array<string, array<string, mixed>>>
   *   Declarations keyed by group name and then by option name.
   *
   * @throws \RuntimeException
   *   When a declaration omits its default or its description.
   */
  protected function contextConfigDeclarations(): array {
    if (isset(self::$contextConfigDeclarationCache[static::class])) {
      return self::$contextConfigDeclarationCache[static::class];
    }

    $reflection = new \ReflectionClass(static::class);
    $schema = [];

    foreach ($reflection->getMethods() as $method) {
      if ($method->getNumberOfParameters() > 0 || preg_match('/^(.+)ConfigSchema$/', $method->getName(), $matches) !== 1) {
        continue;
      }

      $group = $this->contextConfigSnakeCase($matches[1]);
      $declarations = $method->invoke($this);

      if (!is_array($declarations)) {
        throw new \RuntimeException(sprintf('%s::%s() must return an array of option declarations.', static::class, $method->getName()));
      }

      foreach ($declarations as $key => $declaration) {
        if (!is_array($declaration) || !array_key_exists('default', $declaration) || !isset($declaration['description'])) {
          throw new \RuntimeException(sprintf('The "%s.%s" declaration in %s::%s() needs a "default" and a "description".', $group, $key, static::class, $method->getName()));
        }

        if (isset($declaration['tags']) && !is_array($declaration['tags'])) {
          throw new \RuntimeException(sprintf('The "%s.%s" declaration in %s::%s() lists its tags as a map of tag name to the value it sets.', $group, $key, static::class, $method->getName()));
        }
      }

      $schema[$group] = $declarations;
    }

    ksort($schema);
    self::$contextConfigDeclarationCache[static::class] = $schema;

    return $schema;
  }

  /**
   * Resolves every option against the extension and this context's overrides.
   *
   * The extension's 'steps' section arrives through 'setParameters()', which
   * Behat calls after it has constructed the context, so the resolution is
   * deferred to the first read and memoised from there.
   *
   * @return array<string, array<string, mixed>>
   *   Resolved values keyed by group name and then by option name.
   */
  protected function contextConfigResolve(): array {
    if ($this->contextConfigResolved !== NULL) {
      return $this->contextConfigResolved;
    }

    $resolved = $this->contextConfigDefaults();

    $steps = $this->getParameter('steps');

    // A group under 'steps' may name a trait only one of the registered
    // contexts composes, so an unservable group is skipped rather than
    // rejected.
    if (is_array($steps)) {
      $resolved = $this->contextConfigMerge($resolved, $steps, FALSE);
    }

    $this->contextConfigResolved = $this->contextConfigMerge($resolved, $this->contextConfig, TRUE);

    return $this->contextConfigResolved;
  }

  /**
   * Returns every declared option at its default value.
   *
   * @return array<string, array<string, mixed>>
   *   Default values keyed by group name and then by option name.
   */
  protected function contextConfigDefaults(): array {
    $defaults = [];

    foreach ($this->contextConfigDeclarations() as $group => $declarations) {
      foreach ($declarations as $key => $declaration) {
        $defaults[$group][$key] = $declaration['default'];
      }
    }

    return $defaults;
  }

  /**
   * Layers one set of overrides over the resolved values.
   *
   * @param array<string, array<string, mixed>> $resolved
   *   The values resolved so far.
   * @param array<string, mixed> $overrides
   *   The overrides to apply.
   * @param bool $strict
   *   Reject a group or an option no trait declares, rather than skipping it.
   *
   * @return array<string, array<string, mixed>>
   *   The values with the overrides applied.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When a group or an option is undeclared under a strict merge, a group
   *   does not hold a map of options, or a value does not match the type its
   *   declaration defaults to.
   */
  protected function contextConfigMerge(array $resolved, array $overrides, bool $strict): array {
    $schema = $this->contextConfigDeclarations();

    foreach ($overrides as $group => $options) {
      if (!isset($schema[$group])) {
        if ($strict) {
          throw new InvalidConfigurationException(sprintf('Unknown option group "%s" for context "%s". This context accepts: %s.', $group, static::class, implode(', ', array_keys($schema)) ?: 'nothing'));
        }

        continue;
      }

      if (!is_array($options)) {
        throw new InvalidConfigurationException(sprintf('The "%s" option group holds a map of options, but a %s was given.', $group, get_debug_type($options)));
      }

      foreach ($options as $key => $value) {
        if (!isset($schema[$group][$key])) {
          if ($strict) {
            throw new InvalidConfigurationException(sprintf('Unknown option "%s.%s" for context "%s". The "%s" group accepts: %s.', $group, $key, static::class, $group, implode(', ', array_keys($schema[$group]))));
          }

          continue;
        }

        $resolved[$group][$key] = $this->contextConfigCast($value, $schema[$group][$key]['default'], $group, (string) $key);
      }
    }

    return $resolved;
  }

  /**
   * Reads a configured value as the type its declaration defaults to.
   *
   * @param mixed $value
   *   The configured value.
   * @param mixed $default
   *   The declared default, whose type the value has to match.
   * @param string $group
   *   The group name, for the failure message.
   * @param string $key
   *   The option name, for the failure message.
   *
   * @return mixed
   *   The value, cast where a numeric form is unambiguous.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When the value cannot be read as the declared type.
   */
  protected function contextConfigCast(mixed $value, mixed $default, string $group, string $key): mixed {
    $expected = get_debug_type($default);

    if ($expected === 'bool' && is_bool($value)) {
      return $value;
    }

    if ($expected === 'int' && (is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1))) {
      return (int) $value;
    }

    if ($expected === 'float' && (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)))) {
      return (float) $value;
    }

    if ($expected === 'string' && (is_string($value) || is_int($value) || is_float($value))) {
      return (string) $value;
    }

    if ($expected === 'array' && is_array($value)) {
      return $value;
    }

    // A declaration defaulting to NULL names no type, so anything it is given
    // passes through.
    if (!in_array($expected, ['bool', 'int', 'float', 'string', 'array'], TRUE)) {
      return $value;
    }

    throw new InvalidConfigurationException(sprintf('The "%s.%s" option expects a %s, but a %s was given.', $group, $key, $expected, get_debug_type($value)));
  }

  /**
   * Applies the tag layers of one option.
   *
   * A declaration names the tags that set it and the value each one sets.
   * Feature tags are read before scenario tags, so the tag on the narrower node
   * settles the value.
   *
   * @param string $group
   *   The group the option belongs to.
   * @param string $key
   *   The option name.
   * @param mixed $value
   *   The value resolved from the configuration.
   * @param \Behat\Behat\Hook\Scope\ScenarioScope $scope
   *   The scenario scope a hook received.
   *
   * @return mixed
   *   The value, replaced by whatever the last matching tag sets.
   */
  protected function contextConfigApplyTags(string $group, string $key, mixed $value, ScenarioScope $scope): mixed {
    $tags = $this->contextConfigDeclarations()[$group][$key]['tags'] ?? [];

    // An 'enabled' option is also switched off by the library's one skip tag,
    // named after the trait the group belongs to.
    if ($key === 'enabled') {
      $tags['behat-steps-skip:' . ucfirst($this->contextConfigCamelCase($group)) . 'Trait'] = FALSE;
    }

    if ($tags === []) {
      return $value;
    }

    $scenario = $scope->getScenario();
    $lines = [Tag::on($scope->getFeature())];
    $lines[] = $scenario instanceof TaggedNodeInterface ? Tag::on($scenario) : [];

    foreach ($lines as $line) {
      foreach ($line as $tag) {
        if (array_key_exists($tag, $tags)) {
          $value = $tags[$tag];
        }
      }
    }

    return $value;
  }

  /**
   * Resolves the group a skip name belongs to.
   *
   * A name is either a trait name, which maps to its group directly, or a hook
   * method name, which carries its trait's prefix. The longest matching prefix
   * wins, so 'configOverrideBeforeStep' resolves to 'config_override' rather
   * than to 'config'.
   *
   * @param string $name
   *   The hook method name or trait name a skip tag would carry.
   *
   * @return string|null
   *   The group name, or NULL when no group with an 'enabled' option matches.
   */
  protected function contextConfigGroupFor(string $name): ?string {
    $groups = array_keys(array_filter($this->contextConfigDeclarations(), static fn(array $declarations): bool => isset($declarations['enabled'])));

    if (str_ends_with($name, 'Trait')) {
      $group = $this->contextConfigSnakeCase(substr($name, 0, -strlen('Trait')));

      return in_array($group, $groups, TRUE) ? $group : NULL;
    }

    $match = NULL;

    foreach ($groups as $group) {
      $prefix = $this->contextConfigCamelCase($group);

      if (!str_starts_with($name, $prefix) || !ctype_upper(substr($name, strlen($prefix), 1))) {
        continue;
      }

      if ($match === NULL || strlen($group) > strlen($match)) {
        $match = $group;
      }
    }

    return $match;
  }

  /**
   * Lists every declared option as a dotted path.
   */
  protected function contextConfigOptionList(): string {
    $paths = [];

    foreach ($this->contextConfigDeclarations() as $group => $declarations) {
      foreach (array_keys($declarations) as $key) {
        $paths[] = $group . '.' . $key;
      }
    }

    return implode(', ', $paths) ?: 'none';
  }

  /**
   * Converts a camel case method prefix to its snake case group name.
   */
  protected function contextConfigSnakeCase(string $prefix): string {
    return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $prefix));
  }

  /**
   * Converts a snake case group name to its camel case method prefix.
   */
  protected function contextConfigCamelCase(string $group): string {
    return lcfirst(str_replace('_', '', ucwords($group, '_')));
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

}
