<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use Behat\MinkExtension\Context\RawMinkContext;
use DrevOps\BehatSteps\Steps\Web\CookieTrait;
use DrevOps\BehatSteps\Steps\Web\DropzoneTrait;
use DrevOps\BehatSteps\Steps\Web\IframeTrait;
use DrevOps\BehatSteps\Steps\Web\JsonTrait;
use DrevOps\BehatSteps\Steps\Web\KeyboardTrait;
use DrevOps\BehatSteps\Steps\Web\LinkTrait;
use DrevOps\BehatSteps\Steps\Web\MetatagTrait;
use DrevOps\BehatSteps\Steps\Web\PathTrait;
use DrevOps\BehatSteps\Steps\Web\RegionTrait;
use DrevOps\BehatSteps\Steps\Web\ResponseTrait;
use DrevOps\BehatSteps\Steps\Web\ResponsiveTrait;
use DrevOps\BehatSteps\Steps\Web\XmlTrait;

/**
 * Composes every trait that runs on Mink's own base context.
 *
 * A trait listed here promises a project that it works without the library's
 * own context. 'BareMinkCompositionTest' holds the list against the
 * annotations in the source.
 */
class BareMinkContext extends RawMinkContext {

  use CookieTrait;
  use DropzoneTrait;
  use IframeTrait;
  use JsonTrait;
  use KeyboardTrait;
  use LinkTrait;
  use MetatagTrait;
  use PathTrait;
  use RegionTrait;
  use ResponseTrait;
  use ResponsiveTrait;
  use XmlTrait;

}
