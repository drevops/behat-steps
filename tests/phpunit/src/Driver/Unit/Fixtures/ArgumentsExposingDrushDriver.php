<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Driver\Unit\Fixtures;

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
   * @return string
   *   The CLI option string produced by 'parseArguments()'.
   */
  public static function expose(array $arguments): string {
    return self::parseArguments($arguments);
  }

}
