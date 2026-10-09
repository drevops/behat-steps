<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Capability;

/**
 * Capability: clear Drupal and static caches.
 */
interface CacheCapabilityInterface {

  /**
   * Clears every Drupal cache.
   */
  public function cacheClear(): void;

  /**
   * Clears static caches.
   */
  public function cacheClearStatic(): void;

}
