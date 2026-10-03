<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Helper\Drupal\QueryTrait;
use Drupal\Core\Entity\EntityInterface;

/**
 * Manage Drupal ECK entities with custom type and bundle creation.
 *
 * - Create structured ECK entities with defined field values.
 * - Visit and edit ECK entity pages.
 * - Created entities are automatically removed at the end of the scenario.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait EckTrait {

  use EntityLifecycleTrait;
  use QueryTrait;

  /**
   * Create eck entities.
   *
   * @code
   * Given the following eck "contact" "contact_type" entities exist:
   *   | title  | field_marine_animal     | field_fish_type | ... |
   *   | Snook  | Fish                    | Marine fish     | 10  |
   *   | ...    | ...                     | ...             | ... |
   * @endcode
   */
  #[Given('the following eck :bundle :entity_type entities exist:')]
  public function eckEntitiesCreate(string $bundle, string $entity_type, TableNode $table): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $filtered_table = TableNode::fromList($table->getColumn(0));
    $this->eckDeleteEntities($bundle, $entity_type, $filtered_table);
    $this->eckCreateEntities($entity_type, $bundle, $table);
  }

  /**
   * Remove custom entities by field.
   *
   * @code
   * Given the following eck "contact" "contact_type" entities do not exist:
   *   | title           |
   *   | Entity label    |
   * @endcode
   */
  #[Given('the following eck :bundle :entity_type entities do not exist:')]
  public function eckDeleteEntities(string $bundle, string $entity_type, TableNode $table): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    foreach ($table->getHash() as $entity_hash) {
      $entities = $this->eckLoadMultiple($entity_type, $bundle, $entity_hash);

      foreach ($entities as $entity) {
        $entity->delete();
      }
    }
  }

  /**
   * Navigate to view entity page with specified type and title.
   *
   * @code
   * When I visit the eck "contact" "contact_type" entity with the title "Test contact"
   * @endcode
   */
  #[When('I visit the eck :bundle :entity_type entity with the title :title')]
  public function eckVisitEntityPageWithTitle(string $bundle, string $entity_type, string $title): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $entities = $this->eckLoadMultiple($entity_type, $bundle, [
      'title' => $title,
    ]);

    if (empty($entities)) {
      throw new \RuntimeException(sprintf('Unable to find "%s" page "%s".', $entity_type, $title));
    }

    $path = current($entities)->toUrl('canonical')->toString();

    $this->getSession()->visit($path);
  }

  /**
   * Navigate to edit eck entity page with specified type and title.
   *
   * @code
   * When I edit the eck "contact" "contact_type" entity with the title "Test contact"
   * @endcode
   */
  #[When('I edit the eck :bundle :entity_type entity with the title :title')]
  public function eckEditEntityWithTitle(string $bundle, string $entity_type, string $title): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $entities = $this->eckLoadMultiple($entity_type, $bundle, [
      'title' => $title,
    ]);

    if (empty($entities)) {
      throw new \RuntimeException(sprintf('Unable to find "%s" page "%s".', $entity_type, $title));
    }

    $path = current($entities)->toUrl('edit-form')->toString();

    $this->getSession()->visit($path);
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
   * @return array<int, \Drupal\Core\Entity\EntityInterface>
   *   The matching entities keyed by ID, or an empty array when none match.
   */
  public function eckLoadMultiple(string $entity_type, string $bundle, array $conditions = []): array {
    $ids = $this->queryEntityIds($entity_type, $conditions, $bundle);

    return $ids ? \Drupal::entityTypeManager()->getStorage($entity_type)->loadMultiple($ids) : [];
  }

  /**
   * Create custom content entities.
   *
   * @param string $entity_type
   *   The content entity type.
   * @param string $bundle
   *   The content entity bundle.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The TableNode of entity data.
   */
  protected function eckCreateEntities(string $entity_type, string $bundle, TableNode $table): void {
    foreach ($table->getHash() as $entity_hash) {
      $stub = new EntityStub($entity_type, $bundle, $entity_hash);
      $this->eckCreateEntity($stub);
    }
  }

  /**
   * Create a single content entity.
   */
  public function eckCreateEntity(EntityStub $stub): void {
    $this->entityLifecycleParseFields($stub);

    $this->backendFor(ContentCapabilityInterface::class)->entityCreate($stub);

    $saved = $stub->getSavedEntity();
    if ($saved instanceof EntityInterface) {
      $this->entityLifecycleRegister($saved);
    }
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function eckPrerequisites(): array {
    return [
      Prerequisite::capability(CoreCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('eck'), 'the "eck" module from the "drupal/eck" package is enabled'),
    ];
  }

}
