<?php

namespace DrevOps\BehatSteps\D8;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\user\Entity\User;

/**
 * Trait ContentEntityTrait.
 *
 * @package IntegratedExperts\BehatSteps\D8
 */
trait EckEntityTrait {

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
  public function eckEntitiesCreate($bundle, $entity_type, TableNode $table) {
    $filtered_table = TableNode::fromList($table->getColumn(0));
    // Delete entities before creating them.
    $this->eckEntitiesDelete($bundle, $entity_type, $filtered_table);
    $this->createContentEntities($entity_type, $bundle, $table);
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
  public function eckEntitiesDelete($bundle, $entity_type, TableNode $table) {
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
   * @AfterScenario
   */
  public function eckEntitiesCleanAll(AfterScenarioScope $scope) {
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
   * Helper to load multiple entities with specified type and conditions.
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
  protected function eckEntityLoadMultiple($entity_type, $bundle, array $conditions = []) {
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
   * @param string $entity_type
   *   The content entity type.
   * @param string $bundle
   *   The content entity bundle.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The TableNode of entity data.
   */
  protected function createContentEntities($entity_type, $bundle, TableNode $table) {
    foreach ($table->getHash() as $entity_hash) {
      $entity = (object) $entity_hash;
      $entity->type = $bundle;
      $this->eckEntityCreate($entity_type, $entity);
    }
  }

  /**
   * Helper to create a single content entity.
   *
   * @param string $entity_type
   *   The content entity type.
   * @param object $entity
   *   The entity object.
   */
  protected function eckEntityCreate($entity_type, $entity) {
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
  public function contentEditEckEntityWithTitle($bundle, $entity_type, $label) {
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
  public function contentVisitEckEntityPageWithTitle($bundle, $entity_type, $label) {
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
