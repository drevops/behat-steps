<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\EntityDefinitionCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Steps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Steps\Helper\Drupal\QueryTrait;

/**
 * Manage Drupal paragraphs entities with structured field data.
 *
 * - Create paragraph items with type-specific field values.
 * - Test nested paragraph structures and reference field handling.
 * - Attach paragraphs to various entity types with parent-child relationships.
 * - Created paragraph items are automatically removed at the end of the scenario.
 *
 * @title Paragraphs
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait ParagraphsTrait {

  use EntityLifecycleTrait;
  use QueryTrait;

  /**
   * Create a paragraph of the given type with fields within an existing entity.
   *
   * @code
   * Given the following fields for the "text" paragraph exist in the field "field_component" within the "landing_page" "node" entity with the value "My landing page" in the field "title":
   *   | field_paragraph_title           | My paragraph title   |
   *   | field_paragraph_longtext:value  | My paragraph message |
   *   | field_paragraph_longtext:format | full_html            |
   *   | ...                             | ...                  |
   * @endcode
   *
   * @actor:system
   */
  #[Given('the following fields for the :paragraph_type paragraph exist in the field :parent_field within the :parent_bundle :parent_entity_type entity with the value :parent_lookup_value in the field :parent_lookup_field:')]
  public function paragraphsAddWithFields(string $parent_entity_type, string $parent_bundle, string $parent_field, string $parent_lookup_field, string $parent_lookup_value, string $paragraph_type, TableNode $fields): void {
    $this->backendFor(ContentCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $parent_entity = $this->paragraphsGetParentEntity($parent_entity_type, $parent_bundle, $parent_field, $parent_lookup_field, $parent_lookup_value);

    $this->paragraphsAttachFromStubToEntity($parent_entity, $parent_field, $paragraph_type, $this->paragraphsBuildStub($paragraph_type, $fields->getRowsHash()));
  }

  /**
   * Create a paragraphs item from a stub and attach it to an entity.
   *
   * The paragraphs item is created with the entity as its parent, then
   * appended to the field of the entity, which is saved.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $parent_entity
   *   The stub of the entity to attach the paragraphs item to, carrying its
   *   id under the id key of its entity type.
   * @param string $parent_field
   *   Field name on the entity that references the paragraphs item.
   * @param string $paragraph_type
   *   Paragraphs item bundle name.
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   Stub with filled-in fields. Fields are merged with created
   *   paragraphs item object.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The stub of the created paragraphs item, carrying its 'id'.
   */
  public function paragraphsAttachFromStubToEntity(EntityStubInterface $parent_entity, string $parent_field, string $paragraph_type, EntityStubInterface $stub): EntityStubInterface {
    $backend = $this->backendFor(ContentCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $parent_type = $parent_entity->getEntityType();
    $parent_id_key = $this->paragraphsGetIdKey($parent_type);
    $parent_id = $this->queryGetEntityId($parent_entity, $parent_id_key);

    $values = $stub->getValues();
    $values['type'] = $paragraph_type;
    $values['parent_type'] = $parent_type;
    $values['parent_id'] = $parent_id;
    $values['parent_field_name'] = $parent_field;

    $created = $backend->createEntity(new EntityStub('paragraph', $paragraph_type, $values));

    // Registered before the parent is saved, so a parent that fails to save
    // still leaves no paragraphs item behind.
    $this->entityLifecycleRegister($created, 'id');

    $references = $backend->getEntityValues($parent_type, $parent_id, [$parent_field])[$parent_field] ?? [];
    $references[] = ['target_id' => (int) $this->queryGetEntityId($created, 'id')];
    $parent_values = [$parent_id_key => $parent_id, $parent_field => $references];

    $backend->updateEntity(new EntityStub($parent_type, $parent_entity->getBundle(), $parent_values));

    return $created;
  }

  /**
   * Find entity.
   *
   * When several entities match, the newest is returned.
   *
   * @param string $entity_type
   *   Entity type.
   * @param string $bundle
   *   Bundle name.
   * @param string $field_name
   *   Field name.
   * @param string $field_value
   *   Field value.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface|null
   *   The stub of the entity, carrying its id under the id key of its entity
   *   type, or NULL if not found.
   */
  public function paragraphsFindEntity(string $entity_type, string $bundle, string $field_name, string $field_value): ?EntityStubInterface {
    $entity_id = $this->queryFindNewestEntityId($entity_type, [$field_name => $field_value], $bundle);

    return $entity_id === NULL ? NULL : new EntityStub($entity_type, $bundle, [$this->paragraphsGetIdKey($entity_type) => $entity_id]);
  }

  /**
   * Get the entity to attach a paragraph to.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle name.
   * @param string $field_name
   *   The field on the entity that references paragraphs.
   * @param string $lookup_field
   *   The field identifying the entity.
   * @param string $lookup_value
   *   The value of the identifying field.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The stub of the entity, carrying its id under the id key of its entity
   *   type.
   *
   * @throws \RuntimeException
   *   When the bundle has no such field, or no entity matches.
   */
  public function paragraphsGetParentEntity(string $entity_type, string $bundle, string $field_name, string $lookup_field, string $lookup_value): EntityStubInterface {
    $this->paragraphsValidateEntityHasField($entity_type, $bundle, $field_name);

    $entity = $this->paragraphsFindEntity($entity_type, $bundle, $lookup_field, $lookup_value);

    if ($entity === NULL) {
      throw new \RuntimeException(sprintf('The parent entity of type "%s" and bundle "%s" with the field "%s" and the value "%s" was not found.', $entity_type, $bundle, $lookup_field, $lookup_value));
    }

    return $entity;
  }

  /**
   * Build a paragraph stub from field values.
   *
   * @param string $paragraph_type
   *   The paragraph type.
   * @param array<string, mixed> $fields
   *   The field values, keyed by field name.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStub
   *   The stub, with its field values parsed. The backend expands them when
   *   it creates the paragraphs item.
   */
  public function paragraphsBuildStub(string $paragraph_type, array $fields): EntityStub {
    $stub = new EntityStub('paragraph', $paragraph_type, $fields);
    $this->entityLifecycleParseFields($stub);

    return $stub;
  }

  /**
   * Get the key an entity type stores its id under.
   *
   * @param string $entity_type
   *   The entity type.
   *
   * @return string
   *   The id key, such as 'nid' for a node.
   */
  protected function paragraphsGetIdKey(string $entity_type): string {
    return $this->backendFor(EntityDefinitionCapabilityInterface::class)->getEntityIdKey($entity_type);
  }

  /**
   * Validate that an entity has a field.
   *
   * @param string $entity_type
   *   Entity type.
   * @param string $bundle
   *   Bundle name.
   * @param string $field_name
   *   Field name.
   *
   * @throws \RuntimeException
   *   If the field does not exist on the entity.
   */
  protected function paragraphsValidateEntityHasField(string $entity_type, string $bundle, string $field_name): void {
    $field_types = $this->backendFor(EntityDefinitionCapabilityInterface::class)->getEntityFieldTypes($entity_type, $bundle);

    if (!array_key_exists($field_name, $field_types)) {
      throw new \RuntimeException(sprintf('The entity type "%s" with bundle "%s" does not have a field "%s".', $entity_type, $bundle, $field_name));
    }
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function paragraphsPrerequisites(): array {
    return [
      Prerequisite::capability(ContentCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('paragraphs'), 'the "paragraphs" module from the "drupal/paragraphs" package is enabled'),
    ];
  }

}
