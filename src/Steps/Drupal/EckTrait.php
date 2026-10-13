<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Steps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Steps\Helper\Drupal\QueryTrait;

/**
 * Manage Drupal ECK entities with custom type and bundle creation.
 *
 * - Create structured ECK entities with defined field values.
 * - Visit ECK entity pages and their edit pages.
 * - Created entities are automatically removed at the end of the scenario.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait EckTrait {

  use EntityLifecycleTrait;
  use QueryTrait;

  /**
   * Create eck entities.
   *
   * @code
   * Given the following ECK "contact" "contact_type" entities exist:
   *   | title  | field_marine_animal     | field_fish_type | ... |
   *   | Snook  | Fish                    | Marine fish     | 10  |
   *   | ...    | ...                     | ...             | ... |
   * @endcode
   *
   * @actor:system
   */
  #[Given('the following ECK :bundle :entity_type entities exist:')]
  public function eckCreateMultiple(string $bundle, string $entity_type, TableNode $table): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    // An entity matching a row's first column is replaced, not duplicated.
    foreach ($table->getHash() as $entity_hash) {
      $this->eckDelete($entity_type, $bundle, array_slice($entity_hash, 0, 1, TRUE));
    }

    foreach ($table->getHash() as $entity_hash) {
      $this->eckCreate(new EntityStub($entity_type, $bundle, $entity_hash));
    }
  }

  /**
   * Remove custom entities by field.
   *
   * @code
   * Given the following ECK "contact" "contact_type" entities do not exist:
   *   | title           |
   *   | Entity label    |
   * @endcode
   *
   * @actor:system
   */
  #[Given('the following ECK :bundle :entity_type entities do not exist:')]
  public function eckDeleteMultiple(string $bundle, string $entity_type, TableNode $table): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    foreach ($table->getHash() as $entity_hash) {
      $this->eckDelete($entity_type, $bundle, $entity_hash);
    }
  }

  /**
   * Visit the page of the eck entity with the specified type and title.
   *
   * @code
   * When I visit the ECK "contact" "contact_type" entity page with the title "Test contact"
   * @endcode
   *
   * @actor:person
   */
  #[When('I visit the ECK :bundle :entity_type entity page with the title :title')]
  public function eckVisitEntityPageWithTitle(string $bundle, string $entity_type, string $title): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $this->getSession()->visit($this->eckGetEntityUrlByTitle($entity_type, $bundle, $title));
  }

  /**
   * Visit the edit page of the eck entity with the specified type and title.
   *
   * @code
   * When I visit the ECK "contact" "contact_type" entity edit page with the title "Test contact"
   * @endcode
   *
   * @actor:person
   */
  #[When('I visit the ECK :bundle :entity_type entity edit page with the title :title')]
  public function eckVisitEntityEditPageWithTitle(string $bundle, string $entity_type, string $title): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $this->getSession()->visit($this->eckGetEntityUrlByTitle($entity_type, $bundle, $title, 'edit-form'));
  }

  /**
   * Load multiple entities with specified type and conditions.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   * @param array<string, string> $conditions
   *   Conditions keyed by field names.
   *
   * @return array<int|string, \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface>
   *   A stub per matching entity carrying its 'id', keyed by ID, or an empty
   *   array when none match.
   */
  public function eckLoadMultiple(string $entity_type, string $bundle, array $conditions = []): array {
    return $this->queryEntityStubs($entity_type, 'id', $conditions, $bundle);
  }

  /**
   * Get an entity of a type and bundle by title.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   * @param string $title
   *   The entity title.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The stub of the entity created last among those with the title,
   *   carrying its 'id'.
   *
   * @throws \RuntimeException
   *   When no entity has the title.
   */
  public function eckGetEntityByTitle(string $entity_type, string $bundle, string $title): EntityStubInterface {
    $id = $this->queryFindNewestEntityId($entity_type, ['title' => $title], $bundle);

    if ($id === NULL) {
      throw new \RuntimeException(sprintf('Unable to find "%s" page "%s".', $entity_type, $title));
    }

    return new EntityStub($entity_type, $bundle, ['id' => $id]);
  }

  /**
   * Get the URL of a page of the entity of a type and bundle with a title.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   * @param string $title
   *   The entity title.
   * @param string $rel
   *   The link relationship, such as 'canonical' or 'edit-form'.
   *
   * @return string
   *   The URL, as a path relative to the site root, of the entity created
   *   last among those with the title.
   *
   * @throws \RuntimeException
   *   When no entity has the title, or the entity type has no such link.
   */
  public function eckGetEntityUrlByTitle(string $entity_type, string $bundle, string $title, string $rel = 'canonical'): string {
    return $this->backendFor(ContentCapabilityInterface::class)->getEntityUrl($this->eckGetEntityByTitle($entity_type, $bundle, $title), $rel);
  }

  /**
   * Delete the entities of a type and bundle that match conditions.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   * @param array<string, string> $conditions
   *   Conditions keyed by field names.
   */
  public function eckDelete(string $entity_type, string $bundle, array $conditions): void {
    $backend = $this->backendFor(ContentCapabilityInterface::class);

    foreach ($this->queryEntityIds($entity_type, $conditions, $bundle) as $id) {
      $backend->deleteEntity(new EntityStub($entity_type, $bundle, ['id' => $id]));
    }
  }

  /**
   * Create a single content entity.
   */
  public function eckCreate(EntityStub $stub): void {
    $this->entityLifecycleParseFields($stub);

    $created = $this->backendFor(ContentCapabilityInterface::class)->createEntity($stub);

    $this->entityLifecycleRegister($created, 'id');
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function eckPrerequisites(): array {
    return [
      Prerequisite::capability(CoreCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('eck'), 'the "eck" module from the "drupal/eck" package is enabled'),
    ];
  }

}
