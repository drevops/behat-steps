<?php

namespace IntegratedExperts\BehatSteps;

/**
 * Trait JsTrait.
 *
 * @package IntegratedExperts\BehatSteps
 */
trait JsTrait {

  /**
   * @When I accept confirmation dialogs
   *
   * @javascript
   */
  public function jsAcceptConfirmation() {
    $this->getSession()
      ->getDriver()
      ->executeScript('window.confirm = function(){return true;}');
  }

  /**
   * @When I do not accept confirmation dialogs
   *
   * @javascript
   */
  public function jsAcceptNotConfirmation() {
    $this->getSession()
      ->getDriver()
      ->executeScript('window.confirm = function(){return false;}');
  }

  /**
   * @When /^(?:|I )click (an?|on) "(?P<element>[^"]*)" element$/
   *
   * @javascript
   */
  public function jsClickOnElement($element) {
    $session = $this->getSession();
    $xpath = $session->getSelectorsHandler()->selectorToXpath('css', $element);

    $this->getSession()->getDriver()->click($xpath);
  }

}
