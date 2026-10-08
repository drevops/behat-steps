<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Helper\Drupal\QueryTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Manage Drupal paragraphs entities with structured field data.
 *
 * - Create paragraph items with type-specific field values.
 * - Test nested paragraph structures and reference field handling.
 * - Attach paragraphs to various entity types with parent-child relationships.
 * - Created paragraph items are automatically removed at the end of the scenario.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait ParagraphsTrait {

  use EntityLifecycleTrait;
  use QueryTrait;

  /**
   * Create a paragraph of the given type with fields within an existing entity.
   *
   * @code
   * Given the following fields for the paragraph "text" exist in the field "field_component" within the "landing_page" "node" identified by the field "title" and the value "My landing page":
   *   | field_paragraph_title           | My paragraph title   |
   *   | field_paragraph_longtext:value  | My paragraph message |
   *   | field_paragraph_longtext:format | full_html            |
   *   | ...                             | ...                  |
   * @endcode
   */
  #[Given('the following fields for the paragraph :paragraph_type exist in the field :parent_field within the :parent_bundle :parent_entity_type identified by the field :parent_lookup_field and the value :parent_lookup_value:')]
  public function paragraphsAddWithFields(string $parent_entity_type, string $parent_bundle, string $parent_field, string $parent_lookup_field, string $parent_lookup_value, string $paragraph_type, TableNode $fields): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $parent_entity = $this->paragraphsGetParentEntity($parent_entity_type, $parent_bundle, $parent_field, $parent_lookup_field, $parent_lookup_value);

    $this->paragraphsAttachFromStubToEntity($parent_entity, $parent_field, $paragraph_type, $this->paragraphsBuildStub($paragraph_type, $fields->getRowsHash()));
  }

  /**
   * Create a paragraphs item from a stub and attach it to an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $parent_entity
   *   Entity to attach the paragraphs item to.
   * @param string $parent_field
   *   Field name on the entity that references the paragraphs item.
   * @param string $paragraph_type
   *   Paragraphs item bundle name.
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStub $stub
   *   Stub with filled-in fields. Fields are merged with created
   *   paragraphs item object.
   * @param bool $save_entity
   *   Flag to save the parent entity after attaching a paragraphs item.
   *   Defaults to TRUE.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   Created paragraphs item.
   */
  public function paragraphsAttachFromStubToEntity(ContentEntityInterface $parent_entity, string $parent_field, string $paragraph_type, EntityStub $stub, bool $save_entity = TRUE): ParagraphInterface {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $values = $stub->getValues();
    $values['type'] = $paragraph_type;

    $paragraph = Paragraph::create($values);
    $paragraph->setParentEntity($parent_entity, $parent_field)->save();

    $new_value = $parent_entity->get($parent_field)->getValue();
    $new_value[] = [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
    $parent_entity->set($parent_field, $new_value);

    if ($save_entity) {
      $parent_entity->save();
    }

    $this->entityLifecycleRegister($paragraph);

    return $paragraph;
  }

  /**
   * Find entity.
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
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   Found entity or NULL if not found.
   */
  public function paragraphsFindEntity(string $entity_type, string $bundle, string $field_name, string $field_value): ?ContentEntityInterface {
    $entity_ids = $this->queryEntityIds($entity_type, [$field_name => $field_value], $bundle);

    if (empty($entity_ids)) {
      return NULL;
    }

    $entity_id = array_pop($entity_ids);

    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);

    return $entity instanceof ContentEntityInterface ? $entity : NULL;
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
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   *
   * @throws \RuntimeException
   *   When the bundle has no such field, or no entity matches.
   */
  public function paragraphsGetParentEntity(string $entity_type, string $bundle, string $field_name, string $lookup_field, string $lookup_value): ContentEntityInterface {
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
   *   The stub, with its field values parsed and expanded.
   */
  public function paragraphsBuildStub(string $paragraph_type, array $fields): EntityStub {
    $stub = new EntityStub('paragraph', $paragraph_type, $fields);
    $this->entityLifecycleParseFields($stub);
    $this->paragraphsExpandEntityFields($stub);

    return $stub;
  }

  /**
   * Expand parsed fields into expected field values based on field type.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStub $stub
   *   Stub object.
   */
  protected function paragraphsExpandEntityFields(EntityStub $stub): void {
    $core = $this->backendFor(CoreCapabilityInterface::class)->getCore();

    $class = new \ReflectionClass($core::class);
    $method = $class->getMethod('expandEntityFields');

    $method->invokeArgs($core, [$stub]);
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
    $this->backendFor(CoreCapabilityInterface::class);

    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $field_info */
    $field_info = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type, $bundle);

    if (!array_key_exists($field_name, $field_info)) {
      throw new \RuntimeException(sprintf('The entity type "%s" with bundle "%s" does not have a field "%s".', $entity_type, $bundle, $field_name));
    }
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function paragraphsPrerequisites(): array {
    return [
      Prerequisite::capability(CoreCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('paragraphs'), 'the "paragraphs" module from the "drupal/paragraphs" package is enabled'),
    ];
  }

}
