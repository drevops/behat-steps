<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\Config\Option;

/**
 * Trait declaring 1 option of each shape the resolution has to read.
 */
trait SampleConfigTrait {

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function sampleConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Whether the sample hook runs.', tags: ['sample-off' => FALSE, 'sample-on' => TRUE]),
      new Option('label', default: 'a default', description: 'A string option.'),
      new Option('limit', default: 7, description: 'An integer option.'),
      new Option('ratio', default: 0.5, description: 'A float option.'),
      new Option('selectors', default: ['.sample'], description: 'An array option.'),
      new Option('anything', default: NULL, description: 'An option whose declaration names no type.'),
    ];
  }

}
