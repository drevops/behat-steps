<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Drupal\Capability\EntityDefinitionCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Steps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Steps\Helper\Drupal\QueryTrait;

/**
 * Create entities of a type that has no dedicated trait, and reference them.
 *
 * - Create entities of any type from a table of field values.
 * - Fill in an entity reference autocomplete field with an entity's label.
 *
 * Covers types such as `commerce_product` or `group`, where a dedicated trait
 * would add vocabulary without adding behavior. Entities created here are
 * removed after the scenario along with every other entity the scenario
 * created.
 *
 * Skip cleanup for 1 type with tag:
 * `@behat-steps-entity-cleanup-skip:commerce_product`.
 *
 * @title Entities
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait EntityTrait {

  use EntityLifecycleTrait;
  use QueryTrait;

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
   *
   * @actor:system
   */
  #[Given('the following :entity_type entities exist:')]
  public function entityCreateMultiple(string $entity_type, TableNode $table): void {
    foreach ($table->getHash() as $values) {
      $this->entityLifecycleCreate(new EntityStub($entity_type, NULL, $values));
    }
  }

  /**
   * Fill in an entity reference autocomplete field with an entity's label.
   *
   * The field receives the 'Label (id)' value the autocomplete widget accepts
   * without its suggestion list. The entity is matched in every bundle of its
   * type, which is the only form for a type without bundles, such as a user.
   * When several entities share the label, the newest is used.
   *
   * @code
   * When I fill in the entity reference field "Authored by" with the "user" entity with the label "jane"
   * @endcode
   *
   * @actor:person
   */
  #[When('I fill in the entity reference field :field with the :entity_type entity with the label :label')]
  public function entityFillReferenceFieldWithLabel(string $field, string $entity_type, string $label): void {
    $this->entityFillAutocompleteField($field, $entity_type, $label);
  }

  /**
   * Fill in an entity reference autocomplete field with a bundle entity.
   *
   * The field receives the 'Label (id)' value the autocomplete widget accepts
   * without its suggestion list. When several entities of the bundle share
   * the label, the newest is used.
   *
   * @code
   * When I fill in the entity reference field "Program" with the "program" "node" entity with the label "Morning show"
   * When I fill in the entity reference field "Tags" with the "tags" "taxonomy_term" entity with the label "Jazz"
   * @endcode
   *
   * @actor:person
   */
  #[When('I fill in the entity reference field :field with the :bundle :entity_type entity with the label :label')]
  public function entityFillReferenceFieldOfBundleWithLabel(string $field, string $bundle, string $entity_type, string $label): void {
    $this->entityFillAutocompleteField($field, $entity_type, $label, $bundle);
  }

  /**
   * Fill in an entity reference autocomplete field with an entity's label.
   *
   * The value replaces whatever the field holds, so a tags field ends up with
   * the 1 entity.
   *
   * @param string $field
   *   The field's id, name, label or placeholder.
   * @param string $entity_type
   *   The entity type id of the referenced entity.
   * @param string $label
   *   The label of the referenced entity.
   * @param string|null $bundle
   *   The bundle of the referenced entity, or NULL for every bundle.
   *
   * @throws \RuntimeException
   *   When the bundle does not exist or no entity has the label.
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When the page has no such field.
   */
  public function entityFillAutocompleteField(string $field, string $entity_type, string $label, ?string $bundle = NULL): void {
    $id = $this->entityGetIdByLabel($entity_type, $label, $bundle);

    $this->getSession()->getPage()->fillField($field, $this->entityFormatAutocompleteValue($label, $id));
  }

  /**
   * Get the id of the newest entity of a type with a label.
   *
   * @param string $entity_type
   *   The entity type id.
   * @param string $label
   *   The label to match exactly.
   * @param string|null $bundle
   *   The bundle to restrict the lookup to, or NULL for every bundle.
   *
   * @return string
   *   The id of the entity created last with the label.
   *
   * @throws \RuntimeException
   *   When the bundle does not exist or no entity has the label.
   */
  public function entityGetIdByLabel(string $entity_type, string $label, ?string $bundle = NULL): string {
    if ($bundle !== NULL && !$this->backendFor(EntityDefinitionCapabilityInterface::class)->bundleExists($entity_type, $bundle)) {
      throw new \RuntimeException(sprintf('The entity type "%s" has no bundle "%s".', $entity_type, $bundle));
    }

    $id = $this->queryFindNewestEntityIdByLabel($entity_type, $label, $bundle);

    if ($id === NULL) {
      $noun = $bundle === NULL ? sprintf('"%s"', $entity_type) : sprintf('"%s" "%s"', $bundle, $entity_type);

      throw new \RuntimeException(sprintf('The %s entity with the label "%s" does not exist.', $noun, $label));
    }

    return $id;
  }

  /**
   * Format the value an entity reference autocomplete widget accepts.
   *
   * A value holding a comma or a double quote is wrapped in double quotes with
   * each inner quote doubled, as Drupal's 'Tags::encode()' does, so the tags
   * widget reads it as 1 entity.
   *
   * @param string $label
   *   The entity label.
   * @param string $id
   *   The entity id.
   *
   * @return string
   *   The 'Label (id)' value.
   */
  protected function entityFormatAutocompleteValue(string $label, string $id): string {
    $value = sprintf('%s (%s)', $label, $id);

    if (!str_contains($value, ',') && !str_contains($value, '"')) {
      return $value;
    }

    return '"' . str_replace('"', '""', $value) . '"';
  }

}
