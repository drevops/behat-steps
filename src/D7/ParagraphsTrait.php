<?php

namespace DrevOps\BehatSteps\D7;

use Behat\Gherkin\Node\TableNode;

/**
 * Trait ParagraphsTrait.
 *
 * Steps to work with paragraphs for Drupal 7.
 *
 * @package DrevOps\BehatSteps\D7
 */
trait ParagraphsTrait {

  /**
   * Creates paragraphs of the given type with fields for existing node.
   *
   * Paragraph fields are specified in the same way as for nodeCreate().
   *
   * @code
   * When "field_row" in "Test article" node of type "article" has "wysiwyg" paragraph:
   * | field_paragraph_title           | My paragraph title   |
   * | field_paragraph_longtext:value  | My paragraph message |
   * | field_paragraph_longtext:format | full_html            |
   * | ...                             | ...                  |
   * @endcode
   *
   * @When :field_name in :node_title node of type :node_type has :paragraph_type paragraph:
   */
  public function paragraphsAddToNodeWithFields($node_title, $node_type, $field_name, $paragraph_type, TableNode $fields) {
    // Get paragraph field name for this node type.
    $paragraph_node_field_name = $this->paragraphsCheckNodeFieldName($node_type, $field_name);

    // Find previously created node by type and title.
    $node = $this->paragraphsFindNode([
      'title' => $node_title,
      'type' => $node_type,
    ]);

    // Get fields from scenario, parse them and expand values according to
    // field tables.
    $stub = (object) $fields->getRowsHash();
    $this->parseEntityFields('paragraphs_item', $stub);
    $this->paragraphsExpandEntityFields('paragraphs_item', $stub);

    // Attach paragraph from stub to node.
    $this->paragraphsAttachFromStubToNode($node, $paragraph_node_field_name, $paragraph_type, $stub);
  }

  /**
   * Stub processing before parsing into fields.
   */
  protected function paragraphsProcessStub($stub) {
    foreach ((array) $stub as $name => $value) {
      // Process fid replacements.
      if (preg_match('/file.*\:fid$/', $name)) {
        $found_files = file_load_multiple([], ['filename' => $value]);
        if (!empty($found_files)) {
          $found_file = reset($found_files);
          $stub->{$name} = $found_file->fid;
        }
      }
    }
  }

  /**
   * Create a paragraphs item from a stub and attach it to a node.
   *
   * @param object $node
   *   Node to attach paragraph to.
   * @param string $node_field_name
   *   Field name on the node that refers paragraphs item.
   * @param string $bundle
   *   Paragraphs item bundle name.
   * @param object $stub
   *   Standard object with filled-in fields. Fields are merged with created
   *   paragraphs item object.
   * @param bool $save_node
   *   Flag to save node after attaching a paragraphs item. Defaults to TRUE.
   *
   * @return object
   *   Create paragraphs item.
   */
  protected function paragraphsAttachFromStubToNode($node, $node_field_name, $bundle, $stub, $save_node = TRUE) {
    $paragraph_item = entity_create('paragraphs_item', [
      'field_name' => $node_field_name,
      'bundle' => $bundle,
    ]);

    foreach ((array) $stub as $field_name => $field_value) {
      $paragraph_item->{$field_name} = $field_value;
    }

    $paragraph_item->setHostEntity('node', $node);
    $paragraph_item->save();

    if ($save_node) {
      node_save($node);
    }

    return $paragraph_item;
  }

  /**
   * Find node using provided conditions.
   */
  protected function paragraphsFindNode($conditions) {
    $nodes = node_load_multiple(NULL, $conditions);

    if (empty($nodes)) {
      throw new \Exception(sprintf('Unable to find node that matches conditions: "%s"', print_r($conditions, TRUE)));
    }

    $node = current($nodes);

    return $node;
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
   * Get a name of the field that references paragraphs item on node type.
   */
  protected function paragraphsCheckNodeFieldName($node_type, $field) {
    $info = field_info_instances('node', $node_type);
    if (!array_key_exists($field, $info)) {
      throw new \RuntimeException(sprintf('Node bundle "%s" does not have a field "%s"', $node_type, $field));
    }

    return $field;
  }

}
