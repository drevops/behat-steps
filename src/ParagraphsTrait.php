<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\paragraphs\Entity\Paragraph;

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
   * @var array
   */
  protected static $paragraphs = [];

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

    foreach (static::$paragraphs as $paragraph) {
      try {
        $paragraph->delete();
      }
      catch (\Exception) {
        // Ignore the exception and move on.
        continue;
      }
    }
    static::$paragraphs = [];
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
    $entity = $this->paragraphsFindEntity([
      'field_value' => $entity_field_identifer,
      'field_name' => $entity_field_name,
      'bundle' => $bundle,
      'entity_type' => $entity_type,
    ]);

    // Get fields from scenario, parse them and expand values according to
    // field tables.
    $stub = (object) $fields->getRowsHash();
    $stub->type = $paragraph_type;
    $this->parseEntityFields('paragraph', $stub);
    $this->paragraphsExpandEntityFields('paragraph', $stub);

    // Attach paragraph from stub to node.
    $this->paragraphsAttachFromStubToEntity($entity, $field_name, $paragraph_type, $stub);
  }

  /**
   * Create a paragraphs item from a stub and attach it to an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Node to attach paragraph to.
   * @param string $entity_field_name
   *   Field name on the entity that refers paragraphs item.
   * @param string $paragraph_bundle
   *   Paragraphs item bundle name.
   * @param \StdClass $stub
   *   Standard object with filled-in fields. Fields are merged with created
   *   paragraphs item object.
   * @param bool $save_entity
   *   Flag to save node after attaching a paragraphs item. Defaults to TRUE.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph
   *   Created paragraphs item.
   */
  protected function paragraphsAttachFromStubToEntity(ContentEntityInterface $entity, string $entity_field_name, string $paragraph_bundle, \StdClass $stub, bool $save_entity = TRUE): Paragraph {
    $stub->type = $paragraph_bundle;
    $stub = (array) $stub;
    $paragraph = Paragraph::create($stub);
    $paragraph->setParentEntity($entity, $entity_field_name)->save();
    $existing_value = $entity->get($entity_field_name);
    $new_value = $existing_value->getValue();
    $new_value[] = [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
    $entity->set($entity_field_name, $new_value);

    if ($save_entity) {
      $entity->save();
    }

    static::$paragraphs[] = $paragraph;

    return $paragraph;
  }

  /**
   * Find entity using provided conditions.
   */
  protected function paragraphsFindEntity(array $conditions = []): ContentEntityInterface|null {
    $type = ($conditions['entity_type'] === 'taxonomy_term') ? 'vid' : 'type';
    $query = \Drupal::entityQuery($conditions['entity_type'])
      ->accessCheck(FALSE)
      ->condition($type, $conditions['bundle'])
      ->condition($conditions['field_name'], $conditions['field_value']);

    $entity_ids = $query->execute();

    if (empty($entity_ids)) {
      throw new \Exception(sprintf('Unable to find entity that matches conditions: "%s"', print_r($conditions, TRUE)));
    }

    $entity_id = array_pop($entity_ids);

    $entity = \Drupal::entityTypeManager()->getStorage($conditions['entity_type'])->load($entity_id);

    if (!$entity instanceof ContentEntityInterface) {
      throw new \Exception(sprintf('Unable to load entity "%s" with id "%s"', $conditions['entity_type'], $entity_id));
    }

    return $entity;
  }

  /**
   * Expand parsed fields into expected field values based on field type.
   */
  protected function paragraphsExpandEntityFields(string $entity_type, \StdClass $stub) {
    $core = $this->getDriver()->getCore();

    $class = new \ReflectionClass($core::class);
    $method = $class->getMethod('expandEntityFields');
    $method->setAccessible(TRUE);

    return $method->invokeArgs($core, func_get_args());
  }

  /**
   * Get a field name that references the paragraphs item.
   */
  protected function paragraphsValidateEntityFieldName(string $entity_type, string $bundle, string $field_name): void {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $field_info */
    $field_info = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type, $bundle);

    if (!array_key_exists($field_name, $field_info)) {
      throw new \RuntimeException(sprintf('"%s" "%s" does not have a field "%s"', $bundle, $entity_type, $field_name));
    }
  }

}
