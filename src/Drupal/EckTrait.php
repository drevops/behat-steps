<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\eck\EckEntityInterface;

/**
 * Manage Drupal ECK entities with custom type and bundle creation.
 *
 * - Create structured ECK entities with defined field values.
 * - Assert entity type registration and visit entity pages.
 * - Automatically clean up created entities after scenario completion.
 *
 * Skip processing with tag: `@behat-steps-skip:eckAfterScenario`
 */
trait EckTrait {

  /**
   * Custom eck content entities organised by entity type.
   *
   * @var array<string, array<int, \Drupal\eck\EckEntityInterface>>
   */
  protected $eckEntities = [];

  /**
   * Remove ECK types and entities.
   *
   * @AfterScenario
   */
  public function eckAfterScenario(AfterScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    $entity_ids_by_type = [];
    foreach ($this->eckEntities as $entity_type => $content_entities) {
      /** @var \Drupal\eck\EckEntityInterface $content_entity */
      foreach ($content_entities as $content_entity) {
        $entity_ids_by_type[$entity_type][] = $content_entity->id();
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
   * Create eck entities.
   *
   * @code
   * Given the following eck "contact" "contact_type" entities exist:
   * | title  | field_marine_animal     | field_fish_type | ... |
   * | Snook  | Fish                    | Marine fish     | 10  |
   * | ...    | ...                     | ...             | ... |
   * @endcode
   *
   * @Given the following eck :bundle :entity_type entities exist:
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
   * @code
   * Given the following eck "contact" "contact_type" entities do not exist:
   * | field        | value           |
   * | field_a      | Entity label    |
   * @endcode
   *
   * @Given the following eck :bundle :entity_type entities do not exist:
   */
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
   *
   * @When I visit eck :bundle :entity_type entity with the title :title
   */
  public function eckVisitEntityPageWithTitle(string $bundle, string $entity_type, string $title): void {
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_ids = $this->eckLoadMultiple($entity_type, $bundle, [
      'title' => $title,
    ]);

    if (empty($entity_ids)) {
      throw new \RuntimeException(sprintf('Unable to find "%s" page "%s"', $entity_type, $title));
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
   *
   * @When I edit eck :bundle :entity_type entity with the title :title
   */
  public function eckEditEntityWithTitle(string $bundle, string $entity_type, string $title): void {
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_ids = $this->eckLoadMultiple($entity_type, $bundle, [
      'title' => $title,
    ]);

    if (empty($entity_ids)) {
      throw new \RuntimeException(sprintf('Unable to find "%s" page "%s"', $entity_type, $title));
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
    if ($saved instanceof EckEntityInterface) {
      $this->eckEntities[$entity_type][] = $saved;
    }
  }

}
