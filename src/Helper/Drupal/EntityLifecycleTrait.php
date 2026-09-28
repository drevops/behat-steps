<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\ScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Hook\HookDispatcher;
use DrevOps\BehatSteps\Behat\Context\DriverAwareInterface;
use DrevOps\BehatSteps\Behat\Hook\Attribute\BeforeNodeCreate;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterEntityCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterLanguageCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterNodeCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\AfterTermCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeEntityCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeLanguageCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeNodeCreateScope;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeTermCreateScope;
use DrevOps\BehatSteps\Behat\Tag;
use DrevOps\BehatSteps\Driver\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\LanguageCapabilityInterface;
use DrevOps\BehatSteps\Driver\Core\Field\FieldClassifierInterface;
use DrevOps\BehatSteps\Driver\Core\Field\Parser\EntityFieldParser;
use DrevOps\BehatSteps\Driver\Core\Field\Parser\EntityFieldParserInterface;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Creates Drupal entities and removes them when the scenario ends.
 *
 * Holds the one registry every entity creation writes to, so the teardown
 * walks it in reverse and deletes a node before the term it references.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait EntityLifecycleTrait {

  /**
   * Tracks every entity stub created during a scenario for cleanup.
   *
   * Users are tracked in the user manager instead, because a user is looked
   * up by name.
   *
   * @var array<int, \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface>
   */
  protected array $createdStubs = [];

  /**
   * Converts textual node timestamps into the numeric form storage expects.
   *
   * @throws \RuntimeException
   *   When a timestamp value cannot be read as a date.
   */
  #[BeforeNodeCreate]
  public static function entityAlterNodeParameters(BeforeNodeCreateScope $scope): void {
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
   * '@behat-steps-skip:entityCleanAll' skips the whole pass, and
   * '@behat-steps-entity-cleanup-skip:<entity_type_id>' skips one entity
   * type.
   */
  #[AfterScenario]
  public function entityCleanAll(AfterScenarioScope $scope): void {
    if (!$this->shouldCleanup() || $this->skipTag('entityCleanAll', $scope)) {
      return;
    }

    if ($this->createdStubs === []) {
      return;
    }

    $skip_types = $this->entitySkippedCleanupTypes($scope);

    foreach (array_reverse($this->createdStubs) as $stub) {
      if (in_array($stub->getEntityType(), $skip_types, TRUE)) {
        continue;
      }

      $this->entityDeleteStub($stub);
    }

    $this->createdStubs = [];
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
  public function entityNodeCreate(EntityStubInterface $stub): EntityStubInterface {
    $this->entityDispatchHooks(BeforeNodeCreateScope::class, $stub);
    $this->entityDispatchHooks(BeforeEntityCreateScope::class, $stub);

    $driver = $this->entityGetContentDriver();
    $this->entityParseCreatedFields($stub, $driver, ['author']);

    $scalars = $this->entityCaptureScalarBaseFields($stub);
    $driver->nodeCreate($stub);
    $this->entityRestoreScalarBaseFields($stub, $scalars);

    // Register before the post-create hooks run: a hook that throws still
    // leaves the entity behind, and cleanup removes only registered stubs.
    $this->createdStubs[] = $stub;

    $this->entityDispatchHooks(AfterNodeCreateScope::class, $stub);
    $this->entityDispatchHooks(AfterEntityCreateScope::class, $stub);

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
  public function entityTermCreate(EntityStubInterface $stub): EntityStubInterface {
    // The driver loads vocabularies by machine name only, so a human label is
    // resolved to one first. The driver reports a clearer failure than this
    // code could, so the resolution is best-effort.
    $vocabulary = $stub->getValue('vocabulary_machine_name');

    if (!empty($vocabulary) && $this->getDriverManager()->hasCapability(CoreCapabilityInterface::class)) {
      $stub->setValue('vocabulary_machine_name', $this->entityResolveVocabularyMachineName((string) $vocabulary));
    }

    // The driver resolves 'parent' as a term name in the same vocabulary, so
    // it is passed through unchanged. An empty value is removed, because the
    // field pipeline would try to expand the empty string as an entity
    // reference.
    if ($stub->hasValue('parent') && empty($stub->getValue('parent'))) {
      $stub->removeValue('parent');
    }

    $this->entityDispatchHooks(BeforeTermCreateScope::class, $stub);
    $this->entityDispatchHooks(BeforeEntityCreateScope::class, $stub);

    $driver = $this->entityGetContentDriver();
    $this->entityParseCreatedFields($stub, $driver, ['vocabulary_machine_name']);

    $scalars = $this->entityCaptureScalarBaseFields($stub);
    $driver->termCreate($stub);
    $this->entityRestoreScalarBaseFields($stub, $scalars);

    // Register before the post-create hooks run: a hook that throws still
    // leaves the term behind, and cleanup removes only registered stubs.
    $this->createdStubs[] = $stub;

    $this->entityDispatchHooks(AfterTermCreateScope::class, $stub);
    $this->entityDispatchHooks(AfterEntityCreateScope::class, $stub);

    return $stub;
  }

  /**
   * Creates an entity of a type that has no dedicated method.
   *
   * The stub is added to 'createdStubs', so 'entityCleanAll()' removes it
   * after the scenario through the driver's 'entityDelete()' fallback.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The entity stub.
   *
   * @return \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface
   *   The same stub, now flagged as saved.
   */
  public function entityCreate(EntityStubInterface $stub): EntityStubInterface {
    $this->entityDispatchHooks(BeforeEntityCreateScope::class, $stub);

    $driver = $this->entityGetContentDriver();
    $this->entityParseCreatedFields($stub, $driver);

    $scalars = $this->entityCaptureScalarBaseFields($stub);
    $driver->entityCreate($stub);
    $this->entityRestoreScalarBaseFields($stub, $scalars);

    // Register before the post-create hook runs: a hook that throws still
    // leaves the entity behind, and cleanup removes only registered stubs.
    $this->createdStubs[] = $stub;

    $this->entityDispatchHooks(AfterEntityCreateScope::class, $stub);

    return $stub;
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
  public function entityLanguageCreate(EntityStubInterface $stub): EntityStubInterface|false {
    $this->entityDispatchHooks(BeforeLanguageCreateScope::class, $stub);

    $result = $this->driverFor(LanguageCapabilityInterface::class)->languageCreate($stub);

    if ($result === FALSE) {
      return FALSE;
    }

    // Register before the post-create hook runs: a hook that throws still
    // leaves the language behind, and cleanup removes only registered stubs.
    $this->createdStubs[] = $result;

    $this->entityDispatchHooks(AfterLanguageCreateScope::class, $result);

    return $result;
  }

  /**
   * Registers an entity saved outside the create pipeline for cleanup.
   *
   * An entity saved through Drupal's API rather than the driver is added to
   * the same reverse-order teardown. Only the type and id are kept, so
   * cleanup reloads the entity and tolerates a row the scenario already
   * deleted.
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
   * Expands a stub's raw Gherkin values into the storage field shape.
   *
   * A table cell is passed as written: a bare scalar, a comma-separated list,
   * or a compound 'key:"value"' cell. The parser resolves each against the
   * field's own definition before the driver saves the entity.
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
  public function entityParseFields(EntityStubInterface $stub, array $ignored_properties = []): void {
    $classifier = $this->driverFor(CoreCapabilityInterface::class)->getCore()->getFieldClassifier();

    $parser = $this->entityGetFieldParser($stub->getEntityType(), $classifier, $stub->getBundle());
    $parser->ignoring($ignored_properties);

    $stub->setValues($parser->parse($stub->getValues()));
  }

  /**
   * Routes a stub to the right per-type driver delete method.
   */
  protected function entityDeleteStub(EntityStubInterface $stub): void {
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
  protected function entitySkippedCleanupTypes(ScenarioScope $scope): array {
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
   *   The entity stub in the create pipeline.
   *
   * @throws \RuntimeException
   *   When the context has not been initialized by Behat.
   */
  protected function entityDispatchHooks(string $scope_class, EntityStubInterface $stub): void {
    if (!$this->dispatcher instanceof HookDispatcher) {
      throw new \RuntimeException('The hook dispatcher is available only after Behat has initialized the context.');
    }

    $environment = $this->getDriverManager()->getEnvironment();

    if (!$environment instanceof Environment) {
      throw new \RuntimeException('Hooks can be dispatched only once a scenario has started.');
    }

    $scope = new $scope_class($environment, $this, $stub);
    $call_results = $this->dispatcher->dispatchScopeHooks($scope);

    // The dispatcher collects exceptions rather than raising them, so the
    // first one is rethrown here.
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
   * with Drupal bootstrapped exposes.
   *
   * A driver that passes the values to the command line takes them as
   * written, so they are left unparsed. Parsing them would fail a creation
   * the driver can perform.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The stub, mutated in place.
   * @param object $driver
   *   The driver that will save the entity.
   * @param array<int, string> $ignored_properties
   *   Value names to leave untouched.
   */
  protected function entityParseCreatedFields(EntityStubInterface $stub, object $driver, array $ignored_properties = []): void {
    if (!$driver instanceof CoreCapabilityInterface) {
      return;
    }

    $this->entityParseFields($stub, $ignored_properties);
  }

  /**
   * Builds the entity-field parser for one parsing call.
   *
   * A consuming context overrides this method to supply its own
   * implementation.
   *
   * @param string $entity_type
   *   The entity type the values belong to.
   * @param \DrevOps\BehatSteps\Driver\Core\Field\FieldClassifierInterface $classifier
   *   The classifier resolving each value's field definition.
   * @param string|null $bundle
   *   The bundle, or NULL for an entity type without bundles.
   */
  protected function entityGetFieldParser(string $entity_type, FieldClassifierInterface $classifier, ?string $bundle = NULL): EntityFieldParserInterface {
    return new EntityFieldParser($entity_type, $classifier, $bundle);
  }

  /**
   * Resolves a vocabulary identifier to its machine name.
   *
   * Accepts either the machine name (returned as-is) or the human label
   * (looked up via the vocabulary storage). Falls back to the original value
   * when no label matches, leaving the driver to surface a not-found error.
   */
  protected function entityResolveVocabularyMachineName(string $identifier): string {
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
   * 'pass' to single-element arrays. Downstream code expects scalars, so the
   * values are captured before the driver call and restored after it.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The entity stub to inspect.
   *
   * @return array<string, scalar>
   *   The scalar values keyed by name.
   */
  protected function entityCaptureScalarBaseFields(EntityStubInterface $stub): array {
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
  protected function entityRestoreScalarBaseFields(EntityStubInterface $stub, array $scalars): void {
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
  protected function entityGetContentDriver(): ContentCapabilityInterface {
    return $this->driverFor(ContentCapabilityInterface::class);
  }

}
