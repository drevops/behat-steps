<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Driver\Capability;

use DrevOps\BehatSteps\Driver\Core\CoreInterface;

/**
 * Capability: reach Drupal's API in this process.
 *
 * A driver providing this capability has Drupal bootstrapped once it is
 * resolved, so '\Drupal::' statics and the entity API are reachable. A step
 * that calls into Drupal directly asks for this capability rather than for a
 * driver class.
 */
interface CoreCapabilityInterface {

  /**
   * Returns the Core implementation the driver delegates to.
   */
  public function getCore(): CoreInterface;

  /**
   * Returns the major Drupal version detected at construction time.
   *
   * The version is captured once when the driver is instantiated; the
   * detection itself may throw BootstrapException, but this getter does not.
   *
   * @return int
   *   The major Drupal version.
   */
  public function getDrupalVersion(): int;

}
