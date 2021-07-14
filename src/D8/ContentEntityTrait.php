<?php

namespace IntegratedExperts\BehatSteps\D8;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\user\Entity\User;

/**
 * Trait ContentEntityTrait.
 *
 * @package IntegratedExperts\BehatSteps\D8
 */
trait ContentEntityTrait {

  /**
   * Custom content entities organised by entity type.
   * @var array
   */
  protected $contentEntities = [];

  /**
   * Create custom entities
   *
   * Provide entity data in the following format:
   * | title  | parent | field_a     | field_b | ... |
   * | Snook  | Fish   | Marine fish | 10      | ... |
   * | ...    | ...    | ...         | ...     | ... |
   *
   * @Given :bundle :entity_type entities:
   */
  public function contentEntitiesCreate($bundle, $entity_type, TableNode $table) {
    $filtered_table = TableNode::fromList($table->getColumn(0));
    // Delete entities before creating them.
    $this->contentEntitiesDelete($bundle, $entity_type, $table);
    $this->createContentEntities($entity_type, $bundle, $table);
  }

  /**
   * Remove custom entities by field
   *
   * Provide custom entity data in the following format:
   *
   * | field        | value           |
   * | field_a      | Entity label    |
   *
   * @Given no :bundle :entity_type entities:
   */
  public function contentEntitiesDelete($bundle, $entity_type, TableNode $table) {
    foreach ($table->getHash() as $nodeHash) {
      $entity_ids = $this->contentEntityLoadMultiple($entity_type, $bundle, $nodeHash);

      $controller = \Drupal::entityTypeManager()->getStorage($entity_type);
      $entities = $controller->loadMultiple($entity_ids);
      $controller->delete($entities);
    }
  }

  /**
   * @AfterScenario
   */
  public function contentEntitiesCleanAll(AfterScenarioScope $scope) {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    $entity_ids_by_type = [];
    foreach ($this->contentEntities as $entity_type => $content_entities) {
      foreach ($content_entities as $content_entity) {
        $entity_ids_by_type[$entity_type][] = $content_entity->id;
      }
    }

    foreach ($entity_ids_by_type as $entity_type => $entity_ids) {
      $controller = \Drupal::entityTypeManager()->getStorage($entity_type);
      $entities = $controller->loadMultiple($entity_ids);
      $controller->delete($entities);
    }

    $this->contentEntities = [];
  }

  /**
   * Helper to load multiple nodes with specified type and conditions.
   *
   * @param string $type
   *   The node type.
   * @param array $conditions
   *   Conditions keyed by field names.
   *
   * @return array
   *   Array of node ids.
   */
  protected function contentEntityLoadMultiple($entity_type, $bundle, array $conditions = []) {
    $query = \Drupal::entityQuery($entity_type)
      ->condition('type', $bundle)
      ->addMetaData('account', User::load(1));

    foreach ($conditions as $k => $v) {
      $and = $query->andConditionGroup();
      $and->condition($k, $v);
      $query->condition($and);
    }

    return $query->execute();
  }

  /**
   * Helper to create custom content entities.
   *
   * @param $entity_type
   *   The content entity type.
   * @param $bundle
   *   The content entity bundle.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The TableNode of entity data.
   */
  protected function createContentEntities($entity_type, $bundle, TableNode $table) {
    foreach ($table->getHash() as $entity_hash) {
      $entity = (object) $entity_hash;
      $entity->type = $bundle;
      $this->contentEntityCreate($entity_type, $entity);
    }
  }

  /**
   * Helper to create a single content entity.
   *
   * @param $entity_type
   *   The content entity type.
   * @param $entity
   *   The entity object.
   */
  protected function contentEntityCreate($entity_type, $entity) {
    $this->parseEntityFields($entity_type, $entity);
    $saved = $this->getDriver()->createEntity($entity_type, $entity);
    $this->contentEntities[$entity_type][] = $saved;
  }
}
