<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures;

use DrevOps\BehatSteps\Backend\Experimental\ExperimentalAwareInterface;
use DrevOps\BehatSteps\Backend\Experimental\ExperimentalAwareTrait;

/**
 * Test fixture: a plain object composing the experimental-aware trait.
 */
final class ExperimentalAwareObject implements ExperimentalAwareInterface {

  use ExperimentalAwareTrait;

}
