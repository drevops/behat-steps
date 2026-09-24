<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Web;

use DrevOps\BehatSteps\Attribute\Steps;

/**
 * Sample trait whose helper carries no summary.
 */
#[Steps]
trait HelperNoSummaryTrait {

  public function helperNoSummaryValue(): string {
    return '';
  }

}
