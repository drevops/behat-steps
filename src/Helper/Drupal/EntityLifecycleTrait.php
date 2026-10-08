<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\ScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Hook\HookDispatcher;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\LanguageCapabilityInterface;
use DrevOps\BehatSteps\Backend\Core\Field\FieldClassifierInterface;
use DrevOps\BehatSteps\Backend\Core\Field\Parser\EntityFieldParser;
use DrevOps\BehatSteps\Backend\Core\Field\Parser\EntityFieldParserInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Behat\Context\BackendAwareInterface;
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
   * The tag that names an entity type excluded from cleanup.
   */
  protected const string ENTITY_LIFECYCLE_CLEANUP_SKIP_TAG = 'behat-steps-entity-cleanup-skip';

  /**
   * Tracks every entity stub created during a scenario for cleanup.
   *
   * Users are tracked in the user registry instead, because a user is looked
   * up by name.
   *
   * @var array<int, \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface>
   */
  protected array $entityLifecycleCreatedStubs = [];

  /**
   * Converts textual node timestamps into the numeric form storage expects.
   *
   * @throws \RuntimeException
   *   When a timestamp value cannot be read as a date.
   */
  #[BeforeNodeCreate]
  public static function entityLifecycleBeforeNodeCreate(BeforeNodeCreateScope $scope): void {
    $stub = $scope->getStub();

    // A command-line backend takes the values as written, so string dates are
    // converted only for a backend that saves through Drupal's own storage.
    $context = $scope->getContext();

    if (!$context instanceof BackendAwareInterface) {
      return;
    }

    $registry = $context->getBackendRegistry();

    if (!$registry->hasCapability(ContentCapabilityInterface::class)) {
      return;
    }

    if (!$registry->getBackendFor(ContentCapabilityInterface::class) instanceof CoreCapabilityInterface) {
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
   * Walks 'entityLifecycleCreatedStubs' in reverse order, so a dependent
   * entity such as a node referencing a term is deleted before the entity it
   * references.
   *
   * '@behat-steps-skip:EntityLifecycleTrait' skips the whole pass, and
   * '@behat-steps-entity-cleanup-skip:<entity_type_id>' skips 1 entity
   * type.
   */
  #[AfterScenario]
  public function entityLifecycleAfterScenario(AfterScenarioScope $scope): void {
    if ($this->skipTag(__TRAIT__, $scope) || !$this->shouldCleanup()) {
      return;
    }

    if ($this->entityLifecycleCreatedStubs === []) {
      return;
    }

    $skip_types = $this->entityLifecycleSkippedCleanupTypes($scope);

    foreach (array_reverse($this->entityLifecycleCreatedStubs) as $stub) {
      if (in_array($stub->getEntityType(), $skip_types, TRUE)) {
        continue;
      }

      $this->entityLifecycleDeleteStub($stub);
    }

    $this->entityLifecycleCreatedStubs = [];
  }

  /**
   * Creates a node.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The node stub.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The same stub, now flagged as saved.
   */
  public function entityLifecycleCreateNode(EntityStubInterface $stub): EntityStubInterface {
    $this->entityLifecycleDispatchHooks(BeforeNodeCreateScope::class, $stub);
    $this->entityLifecycleDispatchHooks(BeforeEntityCreateScope::class, $stub);

    $backend = $this->entityLifecycleGetContentBackend();
    $this->entityLifecycleParseCreatedFields($stub, $backend, ['author']);

    $scalars = $this->entityLifecycleCaptureScalarBaseFields($stub);
    $backend->createNode($stub);
    $this->entityLifecycleRestoreScalarBaseFields($stub, $scalars);

    // Register before the post-create hooks run: a hook that throws still
    // leaves the entity behind, and cleanup removes only registered stubs.
    $this->entityLifecycleCreatedStubs[] = $stub;

    $this->entityLifecycleDispatchHooks(AfterNodeCreateScope::class, $stub);
    $this->entityLifecycleDispatchHooks(AfterEntityCreateScope::class, $stub);

    return $stub;
  }

  /**
   * Creates a taxonomy term.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The term stub.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The same stub, now flagged as saved.
   */
  public function entityLifecycleCreateTerm(EntityStubInterface $stub): EntityStubInterface {
    // The backend loads vocabularies by machine name only, so a human label is
    // resolved to one first. The backend reports a clearer failure than this
    // code could, so the resolution is best-effort.
    $vocabulary = $stub->getValue('vocabulary_machine_name');

    if (!empty($vocabulary) && $this->getBackendRegistry()->hasCapability(CoreCapabilityInterface::class)) {
      $stub->setValue('vocabulary_machine_name', $this->entityLifecycleResolveVocabularyMachineName((string) $vocabulary));
    }

    // The backend resolves 'parent' as a term name in the same vocabulary, so
    // it is passed through unchanged. An empty value is removed, because the
    // field pipeline would try to expand the empty string as an entity
    // reference.
    if ($stub->hasValue('parent') && empty($stub->getValue('parent'))) {
      $stub->removeValue('parent');
    }

    $this->entityLifecycleDispatchHooks(BeforeTermCreateScope::class, $stub);
    $this->entityLifecycleDispatchHooks(BeforeEntityCreateScope::class, $stub);

    $backend = $this->entityLifecycleGetContentBackend();
    $this->entityLifecycleParseCreatedFields($stub, $backend, ['vocabulary_machine_name']);

    $scalars = $this->entityLifecycleCaptureScalarBaseFields($stub);
    $backend->createTerm($stub);
    $this->entityLifecycleRestoreScalarBaseFields($stub, $scalars);

    // Register before the post-create hooks run: a hook that throws still
    // leaves the term behind, and cleanup removes only registered stubs.
    $this->entityLifecycleCreatedStubs[] = $stub;

    $this->entityLifecycleDispatchHooks(AfterTermCreateScope::class, $stub);
    $this->entityLifecycleDispatchHooks(AfterEntityCreateScope::class, $stub);

    return $stub;
  }

  /**
   * Creates an entity of a type that has no dedicated method.
   *
   * The stub is added to 'entityLifecycleCreatedStubs', so
   * 'entityLifecycleAfterScenario()' removes it after the scenario through the
   * backend's 'deleteEntity()' fallback.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The entity stub.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The same stub, now flagged as saved.
   */
  public function entityLifecycleCreate(EntityStubInterface $stub): EntityStubInterface {
    $this->entityLifecycleDispatchHooks(BeforeEntityCreateScope::class, $stub);

    $backend = $this->entityLifecycleGetContentBackend();
    $this->entityLifecycleParseCreatedFields($stub, $backend);

    $scalars = $this->entityLifecycleCaptureScalarBaseFields($stub);
    $backend->createEntity($stub);
    $this->entityLifecycleRestoreScalarBaseFields($stub, $scalars);

    // Register before the post-create hook runs: a hook that throws still
    // leaves the entity behind, and cleanup removes only registered stubs.
    $this->entityLifecycleCreatedStubs[] = $stub;

    $this->entityLifecycleDispatchHooks(AfterEntityCreateScope::class, $stub);

    return $stub;
  }

  /**
   * Creates a language.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   Language stub. Must carry a 'langcode' value.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The same stub. It stays unsaved, and out of the teardown, when the
   *   language already existed.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException
   *   When no backend in the scenario's order can manage languages.
   */
  public function entityLifecycleCreateLanguage(EntityStubInterface $stub): EntityStubInterface {
    $this->entityLifecycleDispatchHooks(BeforeLanguageCreateScope::class, $stub);

    $created = $this->backendFor(LanguageCapabilityInterface::class)->createLanguage($stub);

    if (!$created->isSaved()) {
      return $created;
    }

    // Register before the post-create hook runs: a hook that throws still
    // leaves the language behind, and cleanup removes only registered stubs.
    $this->entityLifecycleCreatedStubs[] = $created;

    $this->entityLifecycleDispatchHooks(AfterLanguageCreateScope::class, $created);

    return $created;
  }

  /**
   * Registers an entity saved outside the create pipeline for cleanup.
   *
   * An entity saved through Drupal's API rather than the backend is added to
   * the same reverse-order teardown. Only the type and id are kept, so
   * cleanup reloads the entity and tolerates a row the scenario already
   * deleted.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The saved entity.
   */
  public function entityLifecycleRegister(EntityInterface $entity): void {
    $id = $entity->id();
    $id_key = $entity->getEntityType()->getKey('id');

    if ($id === NULL || !is_string($id_key) || $id_key === '') {
      return;
    }

    $this->entityLifecycleCreatedStubs[] = new EntityStub($entity->getEntityTypeId(), $entity->bundle(), [$id_key => $id]);
  }

  /**
   * Expands a stub's raw Gherkin values into the storage field shape.
   *
   * A table cell is passed as written: a bare scalar, a comma-separated list,
   * or a compound 'key:"value"' cell. The parser resolves each against the
   * field's own definition before the backend saves the entity.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub, mutated in place.
   * @param array<int, string> $ignored_properties
   *   Value names to leave untouched, such as base properties the caller
   *   handles itself.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException
   *   When no backend in the scenario's order reaches Drupal's API.
   */
  public function entityLifecycleParseFields(EntityStubInterface $stub, array $ignored_properties = []): void {
    $classifier = $this->backendFor(CoreCapabilityInterface::class)->getCore()->getFieldClassifier();

    $parser = $this->entityLifecycleGetFieldParser($stub->getEntityType(), $classifier, $stub->getBundle());
    $parser->ignoring($ignored_properties);

    $stub->setValues($parser->parse($stub->getValues()));
  }

  /**
   * Routes a stub to the right per-type backend delete method.
   */
  protected function entityLifecycleDeleteStub(EntityStubInterface $stub): void {
    $type = $stub->getEntityType();
    $registry = $this->getBackendRegistry();

    if (in_array($type, ['language', 'configurable_language'], TRUE)) {
      if ($registry->hasCapability(LanguageCapabilityInterface::class)) {
        $this->backendFor(LanguageCapabilityInterface::class)->deleteLanguage($stub);
      }

      return;
    }

    if (!$registry->hasCapability(ContentCapabilityInterface::class)) {
      return;
    }

    $backend = $this->backendFor(ContentCapabilityInterface::class);

    match ($type) {
      'node' => $backend->deleteNode($stub),
      'taxonomy_term' => $backend->deleteTerm($stub),
      default => $backend->deleteEntity($stub),
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
  protected function entityLifecycleSkippedCleanupTypes(ScenarioScope $scope): array {
    return Tag::values($scope, self::ENTITY_LIFECYCLE_CLEANUP_SKIP_TAG);
  }

  /**
   * Dispatches the hooks registered for a scope.
   *
   * @param class-string<\DrevOps\BehatSteps\Behat\Hook\Scope\BaseEntityScope> $scope_class
   *   The fully-qualified scope class name.
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The entity stub in the create pipeline.
   *
   * @throws \RuntimeException
   *   When the context has not been initialized by Behat.
   */
  protected function entityLifecycleDispatchHooks(string $scope_class, EntityStubInterface $stub): void {
    if (!$this->hookDispatcher instanceof HookDispatcher) {
      throw new \RuntimeException('The hook dispatcher is available only after Behat has initialized the context.');
    }

    $environment = $this->getBackendRegistry()->getEnvironment();

    if (!$environment instanceof Environment) {
      throw new \RuntimeException('Hooks can be dispatched only once a scenario has started.');
    }

    $scope = new $scope_class($environment, $this, $stub);
    $call_results = $this->hookDispatcher->dispatchScopeHooks($scope);

    foreach ($call_results as $call_result) {
      $exception = $call_result->getException();

      if ($exception instanceof \Throwable) {
        throw $exception;
      }
    }
  }

  /**
   * Expands a stub's values during creation, if the backend can classify them.
   *
   * Classification reads the site's field definitions, which only a backend
   * with Drupal bootstrapped exposes.
   *
   * A backend that passes the values to the command line takes them as
   * written, so they are left unparsed. Parsing them would fail a creation
   * the backend can perform.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub, mutated in place.
   * @param object $backend
   *   The backend that will save the entity.
   * @param array<int, string> $ignored_properties
   *   Value names to leave untouched.
   */
  protected function entityLifecycleParseCreatedFields(EntityStubInterface $stub, object $backend, array $ignored_properties = []): void {
    if (!$backend instanceof CoreCapabilityInterface) {
      return;
    }

    $this->entityLifecycleParseFields($stub, $ignored_properties);
  }

  /**
   * Builds the entity-field parser for 1 parsing call.
   *
   * A consuming context overrides this method to supply its own
   * implementation.
   *
   * @param string $entity_type
   *   The entity type the values belong to.
   * @param \DrevOps\BehatSteps\Backend\Core\Field\FieldClassifierInterface $classifier
   *   The classifier resolving each value's field definition.
   * @param string|null $bundle
   *   The bundle, or NULL for an entity type without bundles.
   */
  public function entityLifecycleGetFieldParser(string $entity_type, FieldClassifierInterface $classifier, ?string $bundle = NULL): EntityFieldParserInterface {
    return new EntityFieldParser($entity_type, $classifier, $bundle);
  }

  /**
   * Resolves a vocabulary identifier to its machine name.
   *
   * Accepts either the machine name (returned as-is) or the human label
   * (looked up via the vocabulary storage). Falls back to the original value
   * when no label matches, so the backend reports a not-found error.
   */
  protected function entityLifecycleResolveVocabularyMachineName(string $identifier): string {
    $this->backendFor(CoreCapabilityInterface::class);

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
   * During create, the backend's field-handler pipeline casts scalar
   * base-field values such as 'title', 'name', 'mail' or 'pass' to
   * single-element arrays. Downstream code expects scalars, so the values are
   * captured before the backend call and restored after it.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The entity stub to inspect.
   *
   * @return array<string, scalar>
   *   The scalar values keyed by name.
   */
  protected function entityLifecycleCaptureScalarBaseFields(EntityStubInterface $stub): array {
    return array_filter($stub->getValues(), is_scalar(...));
  }

  /**
   * Restores scalar values previously captured.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The entity stub to mutate.
   * @param array<string, scalar> $scalars
   *   Map of value name to original scalar value.
   */
  protected function entityLifecycleRestoreScalarBaseFields(EntityStubInterface $stub, array $scalars): void {
    foreach ($scalars as $field => $value) {
      $stub->setValue($field, $value);
    }
  }

  /**
   * Resolves the backend that saves entities for this scenario.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException
   *   When no backend in the scenario's order can create content.
   */
  protected function entityLifecycleGetContentBackend(): ContentCapabilityInterface {
    return $this->backendFor(ContentCapabilityInterface::class);
  }

}
