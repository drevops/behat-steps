<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Capability;

/**
 * Capability: hand a request header to the site code a backend runs.
 *
 * A backend running the site in this process passes the header the way a web
 * server passes one to PHP. A backend starting a process per command passes it
 * to each command.
 *
 * A header set before the backend is bootstrapped applies from its first
 * request on.
 */
interface RequestEnvironmentCapabilityInterface {

  /**
   * Sets a request header, replacing the value set before.
   *
   * @param string $name
   *   The header name, such as 'X-Config-No-Override'.
   * @param string $value
   *   The header value.
   */
  public function requestHeaderSet(string $name, string $value): void;

  /**
   * Removes a request header.
   *
   * Does nothing when the header is not set.
   *
   * @param string $name
   *   The header name.
   */
  public function requestHeaderUnset(string $name): void;

}
