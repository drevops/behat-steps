<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures;

use DrevOps\BehatSteps\Backend\DrushBackend;

/**
 * Subclass of 'DrushBackend' that exposes its protected parsers.
 *
 * Lets a test invoke 'parseArguments()' and 'parseUserId()' directly without
 * a Drush binary.
 */
class ParserExposingDrushBackend extends DrushBackend {

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

  /**
   * Exposes 'parseUserId()' for testing.
   */
  public function callParseUserId(string $info): ?int {
    return $this->parseUserId($info);
  }

}
