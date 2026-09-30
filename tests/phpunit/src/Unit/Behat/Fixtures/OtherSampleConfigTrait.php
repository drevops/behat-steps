<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\Config\Option;

/**
 * Trait declaring a map option and no switch.
 */
trait OtherSampleConfigTrait {

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function otherSampleConfigSchema(): array {
    return [
      new Option('selectors', default: ['.one', '.two'], description: 'A map option.'),
    ];
  }

}
