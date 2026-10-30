<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Fixtures;

use Behat\MinkExtension\Context\RawMinkContext;
use DrevOps\BehatSteps\Steps\Web\LinkTrait;
use DrevOps\BehatSteps\Steps\Web\PathTrait;
use DrevOps\BehatSteps\Steps\Web\RegionTrait;
use DrevOps\BehatSteps\Steps\Web\ResponseTrait;
use DrevOps\BehatSteps\Steps\Web\ResponsiveTrait;

/**
 * Composes every trait that runs on Mink's own base context.
 *
 * A project can compose each trait listed here without the library's own
 * context.
 */
class BareMinkContext extends RawMinkContext {

  use LinkTrait;
  use PathTrait;
  use RegionTrait;
  use ResponseTrait;
  use ResponsiveTrait;

}
