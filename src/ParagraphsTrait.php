<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Trait ParagraphsTrait.
 *
 * Paragraphs-related steps.
 *
 * @package DrevOps\BehatSteps
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
trait ParagraphsTrait {

  /**
   * Array of created paragraph entities.
   *
   * @var array<int,\Drupal\paragraphs\ParagraphInterface>
   */
  protected static $paragraphEntities = [];

  /**
   * Clean all paragraphs instances after scenario run.
   *
   * @AfterScenario
   */
  public function paragraphsCleanAll(AfterScenarioScope $scope): void {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    foreach (static::$paragraphEntities as $paragraph) {
      $paragraph->delete();
    }
    static::$paragraphEntities = [];
  }

  /**
   * Creates paragraphs of the given type with fields for existing entity.
   *
   * Paragraph fields are specified in the same way as for nodeCreate():
   * | field_paragraph_title           | My paragraph title   |
   * | field_paragraph_longtext:value  | My paragraph message |
   * | field_paragraph_longtext:format | full_html            |
   * | ...                             | ...                  |
   *
   * @When :field_name in :bundle :entity_type with :entity_field_name of :entity_field_identifer has :paragraph_type paragraph:
   */
  public function paragraphsAddToEntityWithFields(string $field_name, string $bundle, string $entity_type, string $entity_field_name, string $entity_field_identifer, string $paragraph_type, TableNode $fields): void {
    $this->paragraphsValidateEntityFieldName($entity_type, $bundle, $field_name);

    // Find previously created entity by entity_type, bundle and identifying
    // field value.
    $parent_entity = $this->paragraphsFindEntity($entity_type, $bundle, $entity_field_name, $entity_field_identifer);

    if (!$parent_entity) {
      throw new \RuntimeException(sprintf('Parent entity "%s" with field "%s" of value "%s" not found', $bundle, $entity_field_name, $entity_field_identifer));
    }

    // Get fields from scenario, parse them and expand values according to
    // field tables.
    $stub = (object) $fields->getRowsHash();
    $stub->type = $paragraph_type;
    $this->parseEntityFields('paragraph', $stub);
    $this->paragraphsExpandEntityFields('paragraph', $stub);

    $this->paragraphsAttachFromStubToEntity($parent_entity, $field_name, $paragraph_type, $stub);
  }

  /**
   * Create a paragraphs item from a stub and attach it to an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $parent_entity
   *   Node to attach paragraph to.
   * @param string $parent_entity_field_name
   *   Field name on the entity that refers paragraphs item.
   * @param string $paragraph_bundle
   *   Paragraphs item bundle name.
   * @param \StdClass $stub
   *   Standard object with filled-in fields. Fields are merged with created
   *   paragraphs item object.
   * @param bool $save_entity
   *   Flag to save node after attaching a paragraphs item. Defaults to TRUE.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   Created paragraphs item.
   */
  protected function paragraphsAttachFromStubToEntity(ContentEntityInterface $parent_entity, string $parent_entity_field_name, string $paragraph_bundle, \StdClass $stub, bool $save_entity = TRUE): ParagraphInterface {
    $stub->type = $paragraph_bundle;
    $stub = (array) $stub;

    $paragraph = Paragraph::create($stub);
    $paragraph->setParentEntity($parent_entity, $parent_entity_field_name)->save();

    $new_value = $parent_entity->get($parent_entity_field_name)->getValue();
    $new_value[] = [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
    $parent_entity->set($parent_entity_field_name, $new_value);

    if ($save_entity) {
      $parent_entity->save();
    }

    static::$paragraphEntities[] = $paragraph;

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
  protected function paragraphsFindEntity(string $entity_type, string $bundle, string $field_name, string $field_value): ?ContentEntityInterface {
    $query = \Drupal::entityQuery($entity_type)
      ->accessCheck(FALSE)
      ->condition($entity_type === 'taxonomy_term' ? 'vid' : 'type', $bundle)
      ->condition($field_name, $field_value);

    $entity_ids = $query->execute();

    if (empty($entity_ids)) {
      return NULL;
    }

    $entity_id = array_pop($entity_ids);

    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);

    return $entity instanceof ContentEntityInterface ? $entity : NULL;
  }

  /**
   * Expand parsed fields into expected field values based on field type.
   *
   * @param string $entity_type
   *   Entity type.
   * @param \StdClass $stub
   *   Stub object.
   */
  protected function paragraphsExpandEntityFields(string $entity_type, \StdClass $stub): void {
    $core = $this->getDriver()->getCore();

    $class = new \ReflectionClass($core::class);
    $method = $class->getMethod('expandEntityFields');
    $method->setAccessible(TRUE);

    $method->invokeArgs($core, func_get_args());
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
  protected function paragraphsValidateEntityFieldName(string $entity_type, string $bundle, string $field_name): void {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $field_info */
    $field_info = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type, $bundle);

    if (!array_key_exists($field_name, $field_info)) {
      throw new \RuntimeException(sprintf('"%s" "%s" does not have a field "%s"', $bundle, $entity_type, $field_name));
    }
  }

}
