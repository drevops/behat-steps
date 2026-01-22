<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Mink\Exception\ExpectationException;

/**
 * Verify HTTP responses with status code and header checks.
 *
 * - Assert HTTP header presence and values.
 */
trait ResponseTrait {

  /**
   * Assert that a response contains a header with specified name.
   *
   * @code
   * Then the response should contain the header "Connection"
   * @endcode
   *
   * @Then the response should contain the header :header_name
   */
  public function responseAssertContainsHeader(string $header_name): void {
    $header = $this->getSession()->getResponseHeader($header_name);

    if (!$header) {
      throw new ExpectationException(sprintf('The response does not contain the header "%s".', $header_name), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a response does not contain a header with a specified name.
   *
   * @code
   * Then the response should not contain the header "Connection"
   * @endcode
   *
   * @Then the response should not contain the header :header_name
   */
  public function responseAssertNotContainsHeader(string $header_name): void {
    $header = $this->getSession()->getResponseHeader($header_name);

    if ($header) {
      throw new ExpectationException(sprintf('The response contains the header "%s", but should not.', $header_name), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a response contains a header with a specified name and value.
   *
   * @code
   * Then the response header "Connection" should contain the value "Keep-Alive"
   * @endcode
   *
   * @Then the response header :header_name should contain the value :header_value
   */
  public function responseAssertHeaderContains(string $header_name, string $header_value): void {
    $header = $this->getSession()->getResponseHeader($header_name);

    if (!$header) {
      throw new ExpectationException(sprintf('The response does not contain the header "%s".', $header_name), $this->getSession()->getDriver());
    }

    $this->assertSession()->responseHeaderContains($header_name, $header_value);
  }

  /**
   * Assert a response does not contain a header with a specified name and value.
   *
   * @code
   * Then the response header "Connection" should not contain the value "Keep-Alive"
   * @endcode
   *
   * @Then the response header :header_name should not contain the value :header_value
   */
  public function responseAssertHeaderNotContains(string $header_name, string $header_value): void {
    $header = $this->getSession()->getResponseHeader($header_name);

    if (!$header) {
      throw new ExpectationException(sprintf('The response does not contain the header "%s".', $header_name), $this->getSession()->getDriver());
    }

    $this->assertSession()->responseHeaderNotContains($header_name, $header_value);
  }

}
