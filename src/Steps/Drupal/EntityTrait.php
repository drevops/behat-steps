<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Helper\Drupal\EntityLifecycleTrait;

/**
 * Create entities of a type that has no dedicated trait.
 *
 * - Create entities of any type from a table of field values.
 *
 * Covers types such as `commerce_product` or `group`, where a dedicated trait
 * would add vocabulary without adding behavior. Entities created here are
 * removed after the scenario along with every other entity the scenario
 * created.
 *
 * Skip cleanup for 1 type with tag:
 * `@behat-steps-entity-cleanup-skip:commerce_product`.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait EntityTrait {

  use EntityLifecycleTrait;

  /**
   * Create entities of a type from a table of field values.
   *
   * Each row becomes 1 entity; each column is a base property or a field.
   *
   * @code
   *   Given the following "commerce_product" entities exist:
   *     | title | type    | status |
   *     | TNT   | product | 1      |
   *     | Anvil | product | 1      |
   * @endcode
   */
  #[Given('the following :entity_type entities exist:')]
  public function entityCreateMultiple(string $entity_type, TableNode $table): void {
    foreach ($table->getHash() as $values) {
      $this->entityLifecycleCreate(new EntityStub($entity_type, NULL, $values));
    }
  }

}
