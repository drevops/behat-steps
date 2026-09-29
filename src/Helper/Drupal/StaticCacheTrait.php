<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper\Drupal;

use Behat\Hook\AfterScenario;
use DrevOps\BehatSteps\Driver\Capability\CacheCapabilityInterface;

/**
 * Clears the static caches a scenario left behind.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait StaticCacheTrait {

  /**
   * Clears static caches.
   *
   * Only a scenario that resolved a cache-capable driver can have populated a
   * static cache, so no driver is resolved for a scenario that did not.
   */
  #[AfterScenario]
  public function staticCacheClear(): void {
    $this->getDriverRegistry()->getResolvedDriverFor(CacheCapabilityInterface::class)?->cacheClearStatic();
  }

}
