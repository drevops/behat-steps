<?php

namespace IntegratedExperts\BehatSteps\D8;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;

/**
 * Trait ContentEntityTrait.
 *
 * @package IntegratedExperts\BehatSteps\D8
 */
trait ContentEntityTrait {

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
  public function contentEntitiesDelete($bundle, $entity_type, $table) {

  }

  /**
   * @AfterScenario
   */
  public function contentEntitiesCleanAll(AfterScenarioScope $scope) {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }
    foreach ($this->contentEntities as $content_entity) {
      $content_entity->delete();
    }

    $this->contentEntities = [];
  }
}
