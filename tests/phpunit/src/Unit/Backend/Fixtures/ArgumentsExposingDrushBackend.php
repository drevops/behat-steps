<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures;

use DrevOps\BehatSteps\Backend\DrushBackend;

/**
 * Subclass of 'DrushBackend' that exposes the protected static parser.
 *
 * Lets a test invoke the protected 'parseArguments()' method directly
 * without a Drush binary.
 */
class ArgumentsExposingDrushBackend extends DrushBackend {

  /**
   * Public wrapper over the protected static parser.
   *
   * @param array<string, string|bool|null> $arguments
   *   Argument map to serialize.
   *
   * @return array<int, string>
   *   The argv entries produced by 'parseArguments()'.
   */
  public static function callParseArguments(array $arguments): array {
    return static::parseArguments($arguments);
  }

}
