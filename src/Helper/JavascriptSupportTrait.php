<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper;

use Behat\Mink\Exception\UnsupportedDriverActionException;

/**
 * Reports whether the running driver evaluates JavaScript.
 *
 * @phpstan-require-extends \Behat\MinkExtension\Context\RawMinkContext
 */
trait JavascriptSupportTrait {

  /**
   * Check if JavaScript is supported by the current driver.
   *
   * Ensures the driver is started before checking JavaScript capability.
   *
   * @return bool
   *   TRUE if JavaScript is supported, FALSE otherwise.
   *
   * @code
   * if (!$this->javascriptSupportAvailable()) {
   *   return;
   * }
   * @endcode
   */
  public function javascriptSupportAvailable(): bool {
    try {
      $driver = $this->getSession()->getDriver();
      if (!$driver->isStarted()) {
        $driver->start();
      }
      $driver->evaluateScript('true');
      return TRUE;
    }
    catch (UnsupportedDriverActionException | \Exception) {
      return FALSE;
    }
  }

}
