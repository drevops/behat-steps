<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Fixtures;

use DrevOps\BehatSteps\Extension\Browser\MinkAwareTrait;

/**
 * Minimal host for 'MinkAwareTrait'.
 */
class MinkAwareObject {

  use MinkAwareTrait;

}
