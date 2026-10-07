<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Core\Field;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;
use Drupal\Core\TypedData\ListDataDefinitionInterface;

/**
 * Default value-shape classifier.
 *
 * See 'src/Backend/Core/Field/README.md' for the value-shape axis and how
 * 'Core' consumes it during handler selection.
 */
final class FieldShapeClassifier implements FieldShapeClassifierInterface {

  /**
   * {@inheritdoc}
   */
  public function fieldIsEntityReference(FieldStorageDefinitionInterface $storage): bool {
    foreach ($this->storedProperties($storage) as $definition) {
      if ($definition instanceof DataReferenceTargetDefinition) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldIsComplexValue(FieldStorageDefinitionInterface $storage): bool {
    foreach ($this->storedProperties($storage) as $definition) {
      if ($definition instanceof ComplexDataDefinitionInterface || $definition instanceof ListDataDefinitionInterface) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Yields a field's stored (non-computed) property definitions.
   *
   * Computed properties are storage-derived, not author-supplied, so they do
   * not affect the field's value shape.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $storage
   *   The field storage definition to inspect.
   *
   * @return iterable<\Drupal\Core\TypedData\DataDefinitionInterface>
   *   The stored property definitions.
   */
  protected function storedProperties(FieldStorageDefinitionInterface $storage): iterable {
    foreach ($storage->getPropertyDefinitions() as $definition) {
      if (!$definition->isComputed()) {
        yield $definition;
      }
    }
  }

}
