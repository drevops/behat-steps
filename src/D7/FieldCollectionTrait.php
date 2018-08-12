<?php

namespace IntegratedExperts\BehatSteps\D7;

use Drupal\Driver\DrupalDriver;
use Drupal\DrupalExtension\Hook\Scope\AfterNodeCreateScope;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;

/**
 * Trait FieldCollectionTrait.
 *
 * @package IntegratedExperts\BehatSteps\D7
 */
trait FieldCollectionTrait {

  /**
   * @var Drupal\Driver\DrupalDriver
   *
   * Drupal core driver.
   */
  protected static $fieldCollectionCoreDriver;

  /**
   * @var array
   *
   * Field collection item fields extracted from the step definition.
   */
  protected static $fieldCollectionItemsFields;

  /**
   * @BeforeNodeCreate
   */
  public static function fieldCollectionParseFields(BeforeNodeCreateScope $scope) {
    $driver = $scope->getContext()->getDrupal()->getDriver();
    if (!$driver instanceof DrupalDriver) {
      return;
    }

    $api_version = $scope->getContext()->getDrupal()->getDriver()->version;
    if ($api_version != 7) {
      return;
    }
    self::$fieldCollectionCoreDriver = $driver;

    $node = $scope->getEntity();
    $fc_fields = [];
    foreach (clone $node as $field => $field_value) {
      // This is not a field that we are looking for.
      if (strpos($field, ':') === FALSE) {
        continue;
      }
      elseif (strpos($field, ':') === 0) {
        throw new \Exception('Field name missing for ' . $field);
      }

      list($field_name, $fc_field_name) = explode(':', $field, 2);
      $fc_field_names = explode(self::fieldCollectionGetInstanceDelimiter(), $fc_field_name);
      $node_field_types = self::$fieldCollectionCoreDriver->getCore()
        ->getEntityFieldTypes('node', $field_name);
      // Although node field parser may validate filed existence, we still need
      // to do it here before validating its type.
      if (!array_key_exists($field_name, $node_field_types)) {
        throw new \Exception(sprintf('Field "%s" does not exist in "node" entity.', $field_name));
      }

      if ($node_field_types[$field_name] !== 'field_collection') {
        // This field is not a field collection, but rather a multi-value field
        // that is handled by the drupal raw context, so we just let it through.
        continue;
      }

      $fc_field_values = explode(self::fieldCollectionGetInstanceDelimiter(), $field_value);

      if (count($fc_field_values) > count($fc_field_names)) {
        throw new \Exception(sprintf('Provided more field collection values for field "%s" then expected: provided %s, but expected %s', count($fc_field_values), count($fc_field_names), $field_name));
      }

      // Track fields for each found field collection.
      foreach ($fc_field_values as $fc_field_key => $fc_field_value) {
        $fc_fields[$field_name][0][$fc_field_names[$fc_field_key]] = trim($fc_field_values[$fc_field_key]);
      }
      unset($node->{$field});
    }

    self::$fieldCollectionItemsFields = $fc_fields;
  }

  /**
   * Attach previously parsed fields to currently processed entity.
   *
   * @AfterNodeCreate
   */
  public static function fieldCollectionAttach(AfterNodeCreateScope $scope) {
    if (empty(self::$fieldCollectionItemsFields)) {
      return;
    }

    $node = $scope->getEntity();
    if (!$node) {
      throw new \Exception('Failed to find a node in @afterNodeCreate hook.');
    }

    foreach (self::$fieldCollectionItemsFields as $field_name => $fc_fields) {
      foreach ($fc_fields as $fc_delta_fields) {
        // Create entity stub from field values.
        $fc_stub = self::fieldCollectionCreateStub($fc_delta_fields);
        // Attach field collection to the host entity.
        self::fieldCollectionAttachToEntity('node', $node, $field_name, $fc_stub);
      }
    }

    // Reset internal fields so that this hook's code does not run for node
    // creation without field collections.
    self::$fieldCollectionItemsFields = NULL;
  }

  /**
   * Create a stub of the field collection with properly processed fields.
   *
   * This uses similar approach as Drupal extension to guess field values.
   */
  protected static function fieldCollectionCreateStub($fields) {
    $fc_stub = (object) [];
    foreach ($fields as $field_name => $field_value) {
      $fc_stub->{$field_name} = $field_value;
    }

    self::fieldCollectionParseEntityFields('field_collection_item', $fc_stub);
    self::fieldCollectionExpandEntityFields('field_collection_item', $fc_stub);

    return $fc_stub;
  }

  /**
   * Attach field collection item instance to a provided entity.
   */
  protected static function fieldCollectionAttachToEntity($host_entity_type, $host_entity, $host_field_name, \stdClass $fc_stub, $save_host_entity = TRUE) {
    $entity_wrapper = entity_metadata_wrapper($host_entity_type, $host_entity);
    $fc = entity_create('field_collection_item', [
      'field_name' => $host_field_name,
      'bundle' => $entity_wrapper->getBundle(),
    ]);

    foreach ($fc_stub as $field_name => $field_value) {
      $fc->{$field_name} = $field_value;
    }

    $fc->setHostEntity($host_entity_type, $host_entity);
    $fc->save();

    if ($save_host_entity) {
      entity_save($host_entity_type, $host_entity);
    }
  }

  /**
   * Copy of DrupalContext::parseEntityFields().
   */
  protected static function fieldCollectionParseEntityFields($entity_type, \stdClass $entity) {
    $multicolumn_field = '';
    $multicolumn_fields = [];

    foreach (clone $entity as $field => $field_value) {
      // Reset the multi-column field if the field name does not contain
      // a column.
      if (strpos($field, ':') === FALSE) {
        $multicolumn_field = '';
      }
      // Start tracking a new multicolumn field if the field name contains a ':'
      // which is preceded by at least 1 character.
      elseif (strpos($field, ':', 1) !== FALSE) {
        list($multicolumn_field, $multicolumn_column) = explode(':', $field);
      }
      // If a field name starts with a ':' but we are not yet tracking a
      // multicolumn field we don't know to which field this belongs.
      elseif (empty($multicolumn_field)) {
        throw new \Exception('Field name missing for ' . $field);
      }
      // Update the column name if the field name starts with a ':' and we are
      // already tracking a multicolumn field.
      else {
        $multicolumn_column = substr($field, 1);
      }

      $is_multicolumn = $multicolumn_field && $multicolumn_column;
      $field_name = $multicolumn_field ?: $field;
      if (self::$fieldCollectionCoreDriver->isField($entity_type, $field_name)) {
        // Split up multiple values in multi-value fields.
        $values = [];
        foreach (explode(', ', $field_value) as $key => $value) {
          $columns = $value;
          // Split up field columns if the ' - ' separator is present.
          if (strstr($value, ' - ') !== FALSE) {
            $columns = [];
            foreach (explode(' - ', $value) as $column) {
              // Check if it is an inline named column.
              if (!$is_multicolumn && strpos($column, ': ', 1) !== FALSE) {
                list ($key, $column) = explode(': ', $column);
                $columns[$key] = $column;
              }
              else {
                $columns[] = $column;
              }
            }
          }
          // Use the column name if we are tracking a multicolumn field.
          if ($is_multicolumn) {
            $multicolumn_fields[$multicolumn_field][$key][$multicolumn_column] = $columns;
            unset($entity->$field);
          }
          else {
            $values[] = $columns;
          }
        }
        // Replace regular fields inline in the entity after parsing.
        if (!$is_multicolumn) {
          $entity->$field_name = $values;
          // Don't specify any value if the step author has left it blank.
          if ($field_value === '') {
            unset($entity->$field_name);
          }
        }
      }
    }

    // Add the multicolumn fields to the entity.
    foreach ($multicolumn_fields as $field_name => $columns) {
      // Don't specify any value if the step author has left it blank.
      $filtered = array_filter($columns, function ($var) {
        return ($var !== '');
      });
      if (count($filtered) > 0) {
        $entity->$field_name = $columns;
      }
    }
  }

  /**
   * Get the delimiter for multiple field collection instances.
   */
  protected static function fieldCollectionGetInstanceDelimiter() {
    return ';';
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
  private static function fieldCollectionExpandEntityFields($entity_type, $stub) {
    $core = self::$fieldCollectionCoreDriver->getCore();

    $class = new \ReflectionClass(get_class($core));
    $method = $class->getMethod('expandEntityFields');
    $method->setAccessible(TRUE);

    return $method->invokeArgs($core, func_get_args());
  }

}
