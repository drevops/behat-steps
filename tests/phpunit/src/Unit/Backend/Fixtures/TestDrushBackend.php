<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures;

use DrevOps\BehatSteps\Backend\DrushBackend;

/**
 * Testable subclass that exposes protected helpers.
 */
class TestDrushBackend extends DrushBackend {

  /**
   * Exposes 'parseUserId()' for testing.
   */
  public function callParseUserId(string $info): ?int {
    return $this->parseUserId($info);
  }

}
