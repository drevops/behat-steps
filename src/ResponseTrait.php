<?php

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
  public function responseAssertContainsHeader($name) {
    $header = $this->getSession()->getResponseHeader($name);

    if (!$header) {
      throw new \Exception(sprintf('Response does not contain header %s', $name), $this->getSession()->getDriver());
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
  public function responseAssertNotContainsHeader($name) {
    $header = $this->getSession()->getResponseHeader($name);

    if ($header) {
      throw new \Exception(sprintf('Response contains header %s, but should not', $name), $this->getSession()->getDriver());
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
  public function responseAssertHeaderContains($name, $value) {
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
  public function responseAssertHeaderNotContains($name, $value) {
    $this->responseAssertContainsHeader($name);
    $this->assertSession()->responseHeaderNotContains($name, $value);
  }

}
