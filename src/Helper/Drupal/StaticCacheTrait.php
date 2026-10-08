<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Hook\AfterScenario;
use DrevOps\BehatSteps\Backend\Capability\CacheCapabilityInterface;

/**
 * Clears the static caches a scenario left behind.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait StaticCacheTrait {

  /**
   * Clears static caches.
   *
   * Only a scenario that resolved a cache-capable backend can have populated a
   * static cache, so no backend is resolved for any other scenario.
   */
  #[AfterScenario]
  public function staticCacheAfterScenario(AfterScenarioScope $scope): void {
    $this->getBackendRegistry()->getResolvedBackendFor(CacheCapabilityInterface::class)?->cacheClearStatic();
  }

}
