<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

/**
 * Trait whose prefix extends another group's, declaring no switch.
 */
trait OtherSampleConfigTrait {

  /**
   * Declares the options this trait reads.
   *
   * @return array<string, array<string, mixed>>
   *   Option declarations keyed by option name.
   */
  protected function otherSampleConfigSchema(): array {
    return [
      'selectors' => [
        'default' => ['.one', '.two'],
        'description' => 'A map option.',
      ],
    ];
  }

}
