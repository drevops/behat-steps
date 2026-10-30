<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Helper\Web;

use Behat\Mink\Exception\DriverException;

/**
 * Reads the last response of the REST session.
 *
 * The REST, JSON and XML steps read their response through these helpers, so
 * each one fails the same way when no REST request has been sent.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait RestResponseTrait {

  /**
   * Get the content of the last REST response.
   *
   * @return string
   *   The response body.
   *
   * @throws \RuntimeException
   *   When no REST request has been sent in the scenario.
   */
  public function restResponseGetContent(): string {
    try {
      return $this->httpRestSession()->getPage()->getContent();
    }
    catch (DriverException $exception) {
      throw $this->restResponseCreateMissingException($exception);
    }
  }

  /**
   * Get the status code of the last REST response.
   *
   * @return int
   *   The HTTP status code.
   *
   * @throws \RuntimeException
   *   When no REST request has been sent in the scenario.
   */
  public function restResponseGetStatusCode(): int {
    try {
      return $this->httpRestSession()->getStatusCode();
    }
    catch (DriverException $exception) {
      throw $this->restResponseCreateMissingException($exception);
    }
  }

  /**
   * Create the exception for a read before any REST request.
   *
   * @param \Behat\Mink\Exception\DriverException $previous
   *   The exception the browser driver threw.
   */
  protected function restResponseCreateMissingException(DriverException $previous): \RuntimeException {
    return new \RuntimeException('No REST request has been sent in this scenario. The REST, JSON and XML steps read the response of "When I send a REST :method request to :url", not the page the browser loaded.', 0, $previous);
  }

}
