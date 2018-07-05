<?php

namespace IntegratedExperts\BehatSteps;

/**
 * Trait ResponseTrait.
 *
 * @package IntegratedExperts\BehatSteps
 */
trait ResponseTrait {

  /**
   * @Then response contains header :name
   */
  public function responseAssertContainsHeader($name) {
    $header = $this->getSession()->getResponseHeader($name);

    if (!$header) {
      throw new \Exception(sprintf('Response does not contain header %s', $name), $this->getSession()->getDriver());
    }
  }

  /**
   * @Then response does not contain header :name
   */
  public function responseAssertNotContainsHeader($name) {
    $header = $this->getSession()->getResponseHeader($name);

    if ($header) {
      throw new \Exception(sprintf('Response contains header %s, but should not', $name), $this->getSession()->getDriver());
    }
  }

  /**
   * @Then response header :name contains :value
   */
  public function responseAssertHeaderContains($name, $value) {
    $this->responseAssertContainsHeader($name);
    $this->assertSession()->responseHeaderContains($name, $value);
  }

  /**
   * @Then response header :name does not contain :value
   */
  public function responseAssertHeaderNotContains($name, $value) {
    $this->responseAssertContainsHeader($name);
    $this->assertSession()->responseHeaderNotContains($name, $value);
  }

}
