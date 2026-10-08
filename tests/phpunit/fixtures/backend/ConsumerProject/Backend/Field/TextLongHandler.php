<?php

declare(strict_types=1);

namespace ConsumerProject\Backend\Field;

use DrevOps\BehatSteps\Backend\Core\Field\AbstractHandler;

/**
 * Fixture: consumer handler for the 'text_long' field type.
 *
 * 'text_long' is served by 'DefaultHandler' when nothing is registered, and
 * 'getFieldHandler()' prefers a registered handler to that fallback.
 * 'ConsumerCore::registerDefaultFieldHandlers()' calls the parent first and
 * then re-scans its own 'Field/' directory, so this registration takes
 * effect.
 */
class TextLongHandler extends AbstractHandler {

  /**
   * Marker value the test asserts appears in storage.
   */
  public const MARKER = 'consumer text_long override';

  /**
   * {@inheritdoc}
   */
  protected function doExpand(array $records): array {
    $emitted = [];

    foreach ($records as $record) {
      $record['value'] = self::MARKER;
      $emitted[] = $record;
    }

    return $emitted;
  }

}
