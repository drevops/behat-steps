<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Capability;

use DrevOps\BehatSteps\Backend\Core\CoreInterface;

/**
 * Capability: reach Drupal's API in this process.
 *
 * A backend providing this capability has Drupal bootstrapped once it is
 * resolved, so '\Drupal::' statics and the entity API are reachable. A step
 * that calls into Drupal directly requires this capability instead of a
 * backend class.
 */
interface CoreCapabilityInterface {

  /**
   * Returns the Core implementation the backend delegates to.
   */
  public function getCore(): CoreInterface;

  /**
   * Returns the major Drupal version detected at construction time.
   *
   * The version is captured once when the backend is instantiated; the
   * detection itself may throw BootstrapException, but this getter does not.
   *
   * @return int
   *   The major Drupal version.
   */
  public function getDrupalVersion(): int;

}
