<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Core99;

use DrevOps\BehatSteps\Backend\Core\Core as BaseCore;

/**
 * Fixture: simulated Core99 override.
 *
 * Extends the default Core, used by lookup-chain tests to verify that
 * DrupalBackend::setCoreFromVersion() picks up version-specific overrides
 * when they exist.
 */
class Core extends BaseCore {

  /**
   * Marker so tests can identify which class was instantiated.
   */
  public const MARKER = 'Core99\\Core';

  /**
   * {@inheritdoc}
   */
  protected function getVersion(): int {
    return 99;
  }

}
