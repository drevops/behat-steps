<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Driver\Fixtures;

use DrevOps\BehatSteps\Driver\DrushDriver;

/**
 * Subclass of 'DrushDriver' that exposes the protected static parser.
 *
 * Used only by 'DrushDriverMethodsTest::testParseArguments()' to invoke the
 * protected 'parseArguments()' method directly without a Drush binary.
 */
class ArgumentsExposingDrushDriver extends DrushDriver {

  /**
   * Public wrapper over the protected static parser.
   *
   * @param array<string, string|bool|null> $arguments
   *   Argument map to serialise.
   *
   * @return array<int, string>
   *   The argv entries produced by 'parseArguments()'.
   */
  public static function expose(array $arguments): array {
    return self::parseArguments($arguments);
  }

}
