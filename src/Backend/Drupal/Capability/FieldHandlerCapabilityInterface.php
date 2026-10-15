<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Capability;

/**
 * Capability: register the handlers that expand field values.
 */
interface FieldHandlerCapabilityInterface {

  /**
   * Registers a field handler class for a field type.
   *
   * A registration for a field type replaces the handler registered for it,
   * shipped or not.
   *
   * @param string $field_type
   *   The Drupal field type id, such as 'datetime' or a contrib module's own.
   * @param string $class
   *   The handler class. It exists and implements 'FieldHandlerInterface'.
   *
   * @throws \RuntimeException
   *   When the class does not exist, does not implement
   *   'FieldHandlerInterface' or is abstract.
   */
  public function registerFieldHandler(string $field_type, string $class): void;

}
