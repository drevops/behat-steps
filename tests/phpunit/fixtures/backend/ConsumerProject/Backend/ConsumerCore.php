<?php

declare(strict_types=1);

namespace ConsumerProject\Backend;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core as BaseCore;

/**
 * Fixture: a consumer project's Core subclass.
 *
 * Lives outside the 'DrevOps\BehatSteps\Backend' namespace on purpose:
 * 'DrupalBackend' takes a Core of any name and namespace. It checks only that
 * the nearest Core of this package the class extends is the Core for the
 * detected major.
 *
 * Registers consumer-owned field handlers by scanning its sibling 'Field/'
 * directory, the same mechanism the library's Core uses for its own
 * built-ins.
 */
class ConsumerCore extends BaseCore {

  /**
   * {@inheritdoc}
   */
  protected function registerDefaultFieldHandlers(): void {
    parent::registerDefaultFieldHandlers();
    $this->registerHandlersFromDirectory(__DIR__ . '/Field', __NAMESPACE__ . '\\Field');
  }

}
