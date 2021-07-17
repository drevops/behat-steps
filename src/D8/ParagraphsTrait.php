<?php

namespace IntegratedExperts\BehatSteps\D8;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Trait ParagraphsTrait.
 */
trait ParagraphsTrait {

  /**
   * Array of created paragraph entities.
   *
   * @var array
   */
  protected $paragraph = [];

  /**
   * Remove any created paragraph items.
   *
   * @AfterScenario
   */
  public function paragraphCleanAll(AfterScenarioScope $scope) {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    foreach ($this->paragraph as $paragraph) {
      try {
        $paragraph->delete();
      }
      catch (\Exception $exception) {
        // Ignore the exception and move on.
        continue;
      }
    }
    $this->paragraph = [];
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
   *
   * @usage "field_components" in "page_builder" "node" with "title" of "[TEST] Page Build node" has "basic_text"
   *   paragraph
   */
  public function paragraphsAddToEntityWithFields($field_name, $bundle, $entity_type, $entity_field_name, $entity_field_identifer, $paragraph_type, TableNode $fields) {
    // Get paragraph field name for this entity type.
    $paragraph_node_field_name = $this->paragraphsCheckEntityFieldName($entity_type, $bundle, $field_name);

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
    $this->paragraphsAttachFromStubToEntity($entity, $paragraph_node_field_name, $paragraph_type, $stub);
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
   * @param object $stub
   *   Standard object with filled-in fields. Fields are merged with created
   *   paragraphs item object.
   * @param bool $save_entity
   *   Flag to save node after attaching a paragraphs item. Defaults to TRUE.
   *
   * @return object
   *   Create paragraphs item.
   */
  protected function paragraphsAttachFromStubToEntity(ContentEntityInterface $entity, $entity_field_name, $paragraph_bundle, $stub, $save_entity = TRUE) {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
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
    $entity->set($entity_field_name, $new_value)->save();
    $this->paragraph[] = $paragraph;
    return $paragraph;
  }

  /**
   * Find node using provided conditions.
   */
  protected function paragraphsFindEntity($conditions = []) {
    $type = ($conditions['entity_type'] === 'taxonomy_term') ? 'vid' : 'type';
    $query = \Drupal::entityQuery($conditions['entity_type'])
      ->condition($type, $conditions['bundle'])
      ->condition($conditions['field_name'], $conditions['field_value']);

    $entity_ids = $query->execute();

    if (empty($entity_ids)) {
      throw new \Exception(sprintf('Unable to find entity that matches conditions: "%s"', print_r($conditions, TRUE)));
    }

    $entity_id = array_pop($entity_ids);
    return \Drupal::entityTypeManager()->getStorage($conditions['entity_type'])->load($entity_id);
  }

  /**
   * Expand parsed fields into expected field values based on field type.
   *
   * This is a re-use of the functionality provided by DrupalExtension.
   *
   * @param string $entity_type
   *   Entity type.
   * @param object $stub
   *   Stub stdClass object with fields and raw values.
   *
   * @return object
   *   Stub object with expanded fields.
   */
  protected function paragraphsExpandEntityFields($entity_type, $stub) {
    $core = $this->getDriver()->getCore();

    $class = new \ReflectionClass(get_class($core));
    $method = $class->getMethod('expandEntityFields');
    $method->setAccessible(TRUE);

    return $method->invokeArgs($core, func_get_args());
  }

  /**
   * Get a field name that references the paragraphs item.
   */
  protected function paragraphsCheckEntityFieldName($entity_type, $bundle, $field_name) {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $entity_field_manager */
    $field_info = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type, $bundle);

    if (!array_key_exists($field_name, $field_info)) {
      throw new \RuntimeException(sprintf('"%s" "%s" does not have a field "%s"', $bundle, $entity_type, $field_name));
    }

    return $field_name;
  }

}
