<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\Context\WebRawContext;

/**
 * Context whose declarations carry no description.
 */
class UndocumentedConfigContext extends WebRawContext {

  /**
   * Declares an option without a description.
   *
   * @return array<string, array<string, mixed>>
   *   Option declarations keyed by option name.
   */
  protected function undocumentedConfigSchema(): array {
    return ['label' => ['default' => 'a default']];
  }

}
