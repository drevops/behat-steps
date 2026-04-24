<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal\Field;

use Drupal\Driver\Core\Field\FieldHandlerInterface;

/**
 * Field handler for the 'text_long' field type.
 *
 * The driver ships dedicated handlers for 'text_with_summary' but not for
 * 'text_long', so the registry falls back to 'DefaultHandler'. The 3.x
 * 'DefaultHandler' refuses any field whose storage has more than one column
 * (or whose single column is not 'value'). 'text_long' has 'value' and
 * 'format' columns, so the fallback throws.
 *
 * Values arrive at this handler already in the correct storage shape (either
 * a scalar that becomes 'value', or an associative array with 'value' and
 * 'format' from the multicolumn-cell parser in 'parseEntityFields()'). The
 * handler does not need to massage anything - it just normalises a scalar to
 * a single-element array so the entity layer accepts it.
 */
class TextLongHandler implements FieldHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function expand(mixed $values): array {
    return (array) $values;
  }

}
