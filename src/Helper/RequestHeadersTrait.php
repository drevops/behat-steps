<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper;

use DrevOps\BehatSteps\Attribute\Helper;

/**
 * Holds the request headers shared by the traits that issue HTTP requests.
 *
 * One bag serves every composer, so a header set by one trait reaches the
 * trait that sends the request whether or not the context composes both.
 */
#[Helper]
trait RequestHeadersTrait {

  /**
   * Request headers shared by the traits that issue their own HTTP requests.
   *
   * @var array<string, string>
   */
  protected array $requestHeaders = [];

  /**
   * Set a request header for subsequent requests.
   *
   * @param string $name
   *   The header name.
   * @param string $value
   *   The header value.
   *
   * @code
   *   $this->setRequestHeader('X-Acme-Token', 'secret');
   * @endcode
   */
  public function setRequestHeader(string $name, string $value): void {
    $this->requestHeaders[$name] = $value;
  }

  /**
   * Drop a request header from subsequent requests.
   */
  protected function unsetRequestHeader(string $name): void {
    unset($this->requestHeaders[$name]);
  }

  /**
   * Read the accumulated request headers.
   *
   * @return array<string, string>
   *   Header values keyed by header name.
   */
  protected function getRequestHeaders(): array {
    return $this->requestHeaders;
  }

  /**
   * Drop every accumulated request header.
   */
  protected function resetRequestHeaders(): void {
    $this->requestHeaders = [];
  }

}
