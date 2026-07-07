<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\When;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Driver\Capability\ContentCapabilityInterface;
use Drupal\Driver\Entity\EntityStub;

/**
 * Manage Drupal ECK entities with custom type and bundle creation.
 *
 * - Create structured ECK entities with defined field values.
 * - Assert entity type registration and visit entity pages.
 * - Created entities are automatically removed at the end of the scenario.
 */
trait EckTrait {

  use HelperTrait;

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
    $filtered_table = TableNode::fromList($table->getColumn(0));
    // Delete entities before creating them.
    $this->eckDeleteEntities($bundle, $entity_type, $filtered_table);
    $this->eckCreateEntities($entity_type, $bundle, $table);
  }

  /**
   * Remove custom entities by field.
   *
   * @code
   * Given the following eck "contact" "contact_type" entities do not exist:
   *   | field        | value           |
   *   | field_a      | Entity label    |
   * @endcode
   */
  #[Given('the following eck :bundle :entity_type entities do not exist:')]
  public function eckDeleteEntities(string $bundle, string $entity_type, TableNode $table): void {
    foreach ($table->getHash() as $node_hash) {
      $entity_ids = $this->eckLoadMultiple($entity_type, $bundle, $node_hash);

      $controller = \Drupal::entityTypeManager()->getStorage($entity_type);
      $entities = $controller->loadMultiple($entity_ids);
      foreach ($entities as $entity) {
        $entity->delete();
      }
    }
  }

  /**
   * Navigate to view entity page with specified type and title.
   *
   * @code
   * When I visit eck "contact" "contact_type" entity with the title "Test contact"
   * @endcode
   */
  #[When('I visit eck :bundle :entity_type entity with the title :title')]
  public function eckVisitEntityPageWithTitle(string $bundle, string $entity_type, string $title): void {
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_ids = $this->eckLoadMultiple($entity_type, $bundle, [
      'title' => $title,
    ]);

    if (empty($entity_ids)) {
      throw new \RuntimeException(sprintf('Unable to find "%s" page "%s".', $entity_type, $title));
    }

    $entity_id = current($entity_ids);
    $entity = $entity_type_manager->getStorage($entity_type)->load($entity_id);
    $path = $entity->toUrl('canonical')->toString();

    $this->getSession()->visit($path);
  }

  /**
   * Navigate to edit eck entity page with specified type and title.
   *
   * @code
   * When I edit eck "contact" "contact_type" entity with the title "Test contact"
   * @endcode
   */
  #[When('I edit eck :bundle :entity_type entity with the title :title')]
  public function eckEditEntityWithTitle(string $bundle, string $entity_type, string $title): void {
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_ids = $this->eckLoadMultiple($entity_type, $bundle, [
      'title' => $title,
    ]);

    if (empty($entity_ids)) {
      throw new \RuntimeException(sprintf('Unable to find "%s" page "%s".', $entity_type, $title));
    }

    $entity_id = current($entity_ids);
    $entity = $entity_type_manager->getStorage($entity_type)->load($entity_id);
    $path = $entity->toUrl('edit-form')->toString();

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
   * @return array<int, string>
   *   Array of entity ids.
   */
  protected function eckLoadMultiple(string $entity_type, string $bundle, array $conditions = []): array {
    $query = \Drupal::entityQuery($entity_type)
      ->accessCheck(FALSE)
      ->condition('type', $bundle);

    foreach ($conditions as $k => $v) {
      $and = $query->andConditionGroup();
      $and->condition($k, $v);
      $query->condition($and);
    }

    return $query->execute();
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
  protected function eckCreateEntity(EntityStub $stub): void {
    $this->parseEntityFields($stub);

    $driver = $this->getDriver();
    if (!$driver instanceof ContentCapabilityInterface) {
      // @codeCoverageIgnoreStart
      throw new \RuntimeException(sprintf('The active Drupal driver "%s" does not support ECK entity creation.', $driver::class));
      // @codeCoverageIgnoreEnd
    }

    $driver->entityCreate($stub);

    $saved = $stub->getSavedEntity();
    if ($saved instanceof EntityInterface) {
      $this->entityRegister($saved);
    }
  }

}
