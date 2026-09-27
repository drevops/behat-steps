<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper;

use Behat\Hook\AfterScenario;
use DrevOps\BehatSteps\Attribute\Helper;
use DrevOps\BehatSteps\Driver\Capability\CacheCapabilityInterface;

/**
 * Clears the static caches a scenario left behind.
 *
 * Only a scenario that reached a cache-capable driver can have left one, so
 * the teardown resolves nothing on a scenario that never touched a cache.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
#[Helper]
trait StaticCacheTrait {

  /**
   * Clears static caches.
   *
   * Only a scenario that reached a cache-capable driver can have left a static
   * cache behind, so a scenario that never touched one is left alone.
   */
  #[AfterScenario]
  public function clearStaticCaches(): void {
    $this->getDriverManager()->getResolvedDriverFor(CacheCapabilityInterface::class)?->cacheClearStatic();
  }

}
