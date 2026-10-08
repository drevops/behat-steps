<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;

/**
 * Context whose declaration method names 1 option twice.
 */
class DuplicateOptionConfigContext extends WebRawContext {

  /**
   * Declares the same option name twice.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   2 options sharing a name.
   */
  protected function duplicateOptionConfigSchema(): array {
    return [
      new Option('label', default: 'first', description: 'The first declaration.'),
      new Option('label', default: 'second', description: 'The second declaration.'),
    ];
  }

}
