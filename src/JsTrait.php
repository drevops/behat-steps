<?php

namespace IntegratedExperts\BehatSteps;

/**
 * Trait JsTrait.
 *
 * @package IntegratedExperts\BehatSteps
 */
trait JsTrait {

  /**
   * Accept confirmation dialogs appearing on the page.
   *
   * @code
   * When I accept confirmation dialogs
   * @endcode
   *
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
   * Do not accept confirmation dialogs appearing on the page.
   *
   * @code
   * When I do not accept confirmation dialogs
   * @endcode
   *
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
   * Click on the element defined by the selector.
   *
   * @code
   * When I click on ".button" element
   * When I click ".button" element
   * When click ".button" element
   * @endcode
   *
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
