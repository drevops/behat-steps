<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Step\When;

/**
 * Wait for Drupal's Batch API to finish.
 *
 * - Poll the batch progress element until it leaves the page.
 *
 * A batch page reloads itself until the operation completes, so a following
 * assertion would otherwise read the progress screen rather than the result.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\RawContext
 */
trait BatchTrait {

  /**
   * How long to wait for a batch job, in milliseconds.
   */
  protected const BATCH_WAIT_TIMEOUT = 180000;

  /**
   * Wait for the batch job to finish.
   *
   * @code
   * When I wait for the batch job to finish
   * @endcode
   *
   * @javascript
   */
  #[When('I wait for the batch job to finish')]
  public function batchWaitForCompletion(): void {
    if (!$this->getSession()->wait(self::BATCH_WAIT_TIMEOUT, 'document.getElementById("updateprogress") === null')) {
      throw new \RuntimeException(sprintf('The batch job did not finish within %d seconds.', self::BATCH_WAIT_TIMEOUT / 1000));
    }
  }

}
