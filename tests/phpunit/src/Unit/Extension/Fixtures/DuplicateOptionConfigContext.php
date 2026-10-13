<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Fixtures;

use DrevOps\BehatSteps\Extension\Config\Option;
use DrevOps\BehatSteps\WebRawContext;

/**
 * Context whose declaration method names 1 option twice.
 */
class DuplicateOptionConfigContext extends WebRawContext {

  /**
   * Declares the same option name twice.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Config\Option>
   *   2 options sharing a name.
   */
  protected function duplicateOptionConfigSchema(): array {
    return [
      new Option('label', default: 'first', description: 'The first declaration.'),
      new Option('label', default: 'second', description: 'The second declaration.'),
    ];
  }

}
