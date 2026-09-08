<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Generic;

use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Then;

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
   */
  #[Then('the response should contain the header :name')]
  public function responseAssertHeaderExists(string $name): void {
    $header = $this->getSession()->getResponseHeader($name);

    if (!$header) {
      throw new ExpectationException(sprintf('The response does not contain the header "%s".', $name), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a response does not contain a header with a specified name.
   *
   * @code
   * Then the response should not contain the header "Connection"
   * @endcode
   */
  #[Then('the response should not contain the header :name')]
  public function responseAssertHeaderNotExists(string $name): void {
    $header = $this->getSession()->getResponseHeader($name);

    if ($header) {
      throw new ExpectationException(sprintf('The response contains the header "%s", but should not.', $name), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a response contains a header with a specified name and value.
   *
   * @code
   * Then the response header "Connection" should contain the value "Keep-Alive"
   * @endcode
   */
  #[Then('the response header :name should contain the value :value')]
  public function responseAssertHeaderContains(string $name, string $value): void {
    $header = $this->getSession()->getResponseHeader($name);

    if (!$header) {
      throw new ExpectationException(sprintf('The response does not contain the header "%s".', $name), $this->getSession()->getDriver());
    }

    $this->assertSession()->responseHeaderContains($name, $value);
  }

  /**
   * Assert a response does not contain a header with a specified name and value.
   *
   * @code
   * Then the response header "Connection" should not contain the value "Keep-Alive"
   * @endcode
   */
  #[Then('the response header :name should not contain the value :value')]
  public function responseAssertHeaderNotContains(string $name, string $value): void {
    $header = $this->getSession()->getResponseHeader($name);

    if (!$header) {
      throw new ExpectationException(sprintf('The response does not contain the header "%s".', $name), $this->getSession()->getDriver());
    }

    $this->assertSession()->responseHeaderNotContains($name, $value);
  }

}
