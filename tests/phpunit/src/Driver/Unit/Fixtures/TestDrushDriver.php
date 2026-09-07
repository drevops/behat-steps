<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Driver\Unit\Fixtures;

use DrevOps\BehatSteps\Driver\DrushDriver;

/**
 * Testable subclass that exposes protected helpers.
 */
class TestDrushDriver extends DrushDriver {

  /**
   * Exposes 'parseUserId()' for testing.
   */
  public function callParseUserId(string $info): ?int {
    return $this->parseUserId($info);
  }

}
