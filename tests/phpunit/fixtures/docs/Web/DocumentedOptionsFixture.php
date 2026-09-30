<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Web;

use DrevOps\BehatSteps\Behat\Config\Option;

/**
 * Trait declaring its options in the method its own name derives.
 */
trait DocumentedOptionsTrait {

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function documentedOptionsConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Whether the documented hook runs.'),
      new Option('limit', default: 7, description: 'An integer option.'),
    ];
  }

}

/**
 * Class composing the trait that declares options.
 */
class DocumentedOptionsContext {

  use DocumentedOptionsTrait;

}
