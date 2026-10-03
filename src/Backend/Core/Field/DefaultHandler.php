<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Core\Field;

/**
 * Fallback handler for field types with no dedicated handler.
 *
 * Relays the normalised records to storage verbatim. An entity-reference
 * target or a complex/nested value is rejected during handler selection (see
 * 'FieldShapeClassifierInterface'), so every field this handler receives is
 * a plain-scalar shape.
 *
 * See 'src/Backend/Core/Field/README.md' for the full handler-selection
 * table.
 */
class DefaultHandler extends AbstractHandler {

  /**
   * {@inheritdoc}
   */
  protected function doExpand(array $records): array {
    return $records;
  }

}
