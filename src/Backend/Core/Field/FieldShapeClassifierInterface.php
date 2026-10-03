<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Core\Field;

use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Classifies a field's stored value shape for handler selection.
 *
 * 'FieldClassifierInterface' answers the pipeline-entry (F-row) question from
 * a field's origin and storage profile. This interface answers the orthogonal
 * value-shape question the README calls a "handler-selection input".
 *
 * A stored value is either a plain scalar the default handler can relay or a
 * shape that requires a dedicated handler. Both predicates read only the
 * storage definition's stored (non-computed) property definitions and
 * enumerate no field-type or data-type strings.
 *
 * A datetime, boolean, or list column is therefore neither an entity
 * reference nor complex: it is a plain scalar the default relays. Value
 * translation for such a column belongs in a dedicated handler.
 *
 * See 'src/Backend/Core/Field/README.md' for the value-shape axis and how
 * 'Core' consumes it during handler selection.
 */
interface FieldShapeClassifierInterface {

  /**
   * Whether a stored property references another entity by id.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $storage
   *   The field storage definition to inspect.
   *
   * @return bool
   *   TRUE when a non-computed property is a 'DataReferenceTargetDefinition'.
   */
  public function fieldIsEntityReference(FieldStorageDefinitionInterface $storage): bool;

  /**
   * Whether a stored property holds a complex or nested value.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $storage
   *   The field storage definition to inspect.
   *
   * @return bool
   *   TRUE when a non-computed property is a 'ComplexDataDefinitionInterface'
   *   (e.g. a map) or a 'ListDataDefinitionInterface'.
   */
  public function fieldIsComplexValue(FieldStorageDefinitionInterface $storage): bool;

}
