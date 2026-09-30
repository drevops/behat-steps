<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;

/**
 * Context whose declaration method builds an option that rejects itself.
 */
class InvalidOptionConfigContext extends WebRawContext {

  /**
   * Declares an option carrying no description.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   Deliberately unreachable: the option rejects its own declaration.
   */
  protected function invalidOptionConfigSchema(): array {
    return [
      new Option('label', default: 'a default', description: ''),
    ];
  }

}
