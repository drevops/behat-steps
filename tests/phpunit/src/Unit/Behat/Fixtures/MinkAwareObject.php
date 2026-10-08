<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\Mink\MinkAwareTrait;

/**
 * Minimal host for 'MinkAwareTrait'.
 */
class MinkAwareObject {

  use MinkAwareTrait;

}
