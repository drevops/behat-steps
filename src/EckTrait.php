<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;

/**
 * Trait EckTrait.
 *
 * Entity Contraction Kit-related steps.
 *
 * @package DrevOps\BehatSteps
 */
trait EckTrait {

  /**
   * Custom eck content entities organised by entity type.
   *
   * @var array
   */
  protected $eckEntities = [];

  /**
   * Create eck entities.
   *
   * Provide entity data in the following format:
   * | title  | field_marine_animal     | field_fish_type | ... |
   * | Snook  | Fish                    | Marine fish     | 10  |
   * | ...    | ...                     | ...         | ...     |
   *
   * @Given :bundle :entity_type entities:
   */
  public function eckEntitiesCreate(string $bundle, string $entity_type, TableNode $table): void {
    $filtered_table = TableNode::fromList($table->getColumn(0));
    // Delete entities before creating them.
    $this->eckDeleteEntities($bundle, $entity_type, $filtered_table);
    $this->eckCreateEntities($entity_type, $bundle, $table);
  }

  /**
   * Remove custom entities by field.
   *
   * Provide custom entity data in the following format:
   *
   * | field        | value           |
   * | field_a      | Entity label    |
   *
   * @Given no :bundle :entity_type entities:
   */
  public function eckDeleteEntities(string $bundle, string $entity_type, TableNode $table): void {
    foreach ($table->getHash() as $nodeHash) {
      $entity_ids = $this->eckEntityLoadMultiple($entity_type, $bundle, $nodeHash);

      $controller = \Drupal::entityTypeManager()->getStorage($entity_type);
      $entities = $controller->loadMultiple($entity_ids);
      foreach ($entities as $entity) {
        $entity->delete();
      }
    }
  }

  /**
   * Remove ECK types and entities.
   *
   * @AfterScenario
   */
  public function eckEntitiesCleanAll(AfterScenarioScope $scope): void {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    $entity_ids_by_type = [];
    foreach ($this->eckEntities as $entity_type => $content_entities) {
      foreach ($content_entities as $content_entity) {
        $entity_ids_by_type[$entity_type][] = $content_entity->id;
      }
    }

    foreach ($entity_ids_by_type as $entity_type => $entity_ids) {
      $controller = \Drupal::entityTypeManager()->getStorage($entity_type);
      $entities = $controller->loadMultiple($entity_ids);
      $controller->delete($entities);
    }

    $this->eckEntities = [];
  }

  /**
   * Load multiple entities with specified type and conditions.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   * @param array $conditions
   *   Conditions keyed by field names.
   *
   * @return array
   *   Array of entity ids.
   */
  protected function eckEntityLoadMultiple(string $entity_type, string $bundle, array $conditions = []) {
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
  protected function eckCreateEntities(string $entity_type, string $bundle, TableNode $table) {
    foreach ($table->getHash() as $entity_hash) {
      $entity = (object) $entity_hash;
      $entity->type = $bundle;
      $this->eckCreateEntity($entity_type, $entity);
    }
  }

  /**
   * Create a single content entity.
   */
  protected function eckCreateEntity(string $entity_type, \StdClass $entity): void {
    $this->parseEntityFields($entity_type, $entity);
    $saved = $this->getDriver()->createEntity($entity_type, $entity);
    $this->eckEntities[$entity_type][] = $saved;
  }

  /**
   * Navigate to view eck entity page with specified type and title.
   *
   * @code
   * When I edit "contact" "contact_type" with title "Test contact"
   * @endcode
   *
   * @When I edit :bundle :entity_type with title :label
   */
  public function eckEditEntityWithTitle(string $bundle, string $entity_type, string $label): void {
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_ids = $this->eckEntityLoadMultiple($entity_type, $bundle, [
      'title' => $label,
    ]);

    if (empty($entity_ids)) {
      throw new \RuntimeException(sprintf('Unable to find %s page "%s"', $entity_type, $label));
    }

    $entity_id = current($entity_ids);
    $entity = $entity_type_manager->getStorage($entity_type)->load($entity_id);
    $path = $entity->toUrl('edit-form')->toString();
    print $path;

    $this->getSession()->visit($path);
  }

  /**
   * Navigate to entity page with specified type and title.
   *
   * @code
   * When I visit "contact" "contact_type" with title "Test contact"
   * @endcode
   *
   * @When I visit :bundle :entity_type with title :label
   */
  public function eckVisitEntityPageWithTitle(string $bundle, string $entity_type, string $label): void {
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_ids = $this->eckEntityLoadMultiple($entity_type, $bundle, [
      'title' => $label,
    ]);

    if (empty($entity_ids)) {
      throw new \RuntimeException(sprintf('Unable to find %s page "%s"', $entity_type, $label));
    }

    $entity_id = current($entity_ids);
    $entity = $entity_type_manager->getStorage($entity_type)->load($entity_id);
    $path = $entity->toUrl('canonical')->toString();
    print $path;

    $this->getSession()->visit($path);
  }

}
