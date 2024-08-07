<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

/**
 * Trait ResponseTrait.
 *
 * Response-related steps.
 *
 * @package DrevOps\BehatSteps
 */
trait ResponseTrait {

  /**
   * Assert that a response contains a header with specified name.
   *
   * @code
   * Then response contains header "Connection"
   * @endcode
   *
   * @Then response contains header :name
   */
  public function responseAssertContainsHeader(string $name): void {
    $header = $this->getSession()->getResponseHeader($name);

    if (!$header) {
      throw new \Exception(sprintf('Response does not contain header %s', $name));
    }
  }

  /**
   * Assert that a response does not contain a header with specified name.
   *
   * @code
   * Then response does not contain header "Connection"
   * @endcode
   *
   * @Then response does not contain header :name
   */
  public function responseAssertNotContainsHeader(string $name): void {
    $header = $this->getSession()->getResponseHeader($name);

    if ($header) {
      throw new \Exception(sprintf('Response contains header %s, but should not', $name));
    }
  }

  /**
   * Assert that a response contains a header with specified name and value.
   *
   * @code
   * Then response header "Connection" contains "Keep-Alive"
   * @endcode
   *
   * @Then response header :name contains :value
   */
  public function responseAssertHeaderContains(string $name, string $value): void {
    $this->responseAssertContainsHeader($name);
    $this->assertSession()->responseHeaderContains($name, $value);
  }

  /**
   * Assert a response does not contain a header with specified name and value.
   *
   * @code
   * Then response header "Connection" does not contain "Keep-Alive"
   * @endcode
   *
   * @Then response header :name does not contain :value
   */
  public function responseAssertHeaderNotContains(string $name, string $value): void {
    $this->responseAssertContainsHeader($name);
    $this->assertSession()->responseHeaderNotContains($name, $value);
  }

}
