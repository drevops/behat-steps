<?php

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;

/**
 * Trait JsTrait.
 *
 * JavaScript-related steps.
 *
 * @package DrevOps\BehatSteps
 */
trait JsTrait {

  /**
   * Init values required for javascript tagged scenarios.
   *
   * @param Behat\Behat\Hook\Scope\BeforeScenarioScope $scope
   *   Scenario scope.
   *
   * @BeforeScenario
   */
  public function jsBeforeScenarioInit(BeforeScenarioScope $scope) {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    if ($scope->getScenario()->hasTag('javascript')) {
      $session = $this->getSession();
      $driver = $session->getDriver();

      if (!$driver->isStarted()) {
        $driver->start();
      }

      $session->resizeWindow(1440, 900, 'current');
    }
  }

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
    $xpath = $this
      ->getSession()
      ->getSelectorsHandler()
      ->selectorToXpath('css', $element);

    $this
      ->getSession()
      ->getDriver()
      ->click($xpath);
  }

  /**
   * Trigger an event on the specified element.
   *
   * @When I trigger JS :event event on :selector element
   */
  public function jsTriggerElementEvent($event, $selector) {
    $script = "return (function(el) {
            if (el) {
              el.$event();
              return true;
            }            
            return false;              
        })({{ELEMENT}});";

    $result = $this->jsExecute($selector, $script);

    if (!$result) {
      throw new \RuntimeException(sprintf('Unable to trigger "%s" event on an element "%s" with JavaScript', $event, $selector));
    }
  }

  /**
   * Execute JS on an element provided by the selector.
   *
   * @param string $selector
   *   The CSS selector for an element.
   * @param string $script
   *   The script to execute. Note that '{{ELEMENT}}' is a token to use in
   *   the script to reference the element.
   *
   * @return mixed
   *   The result of script evaluation. Script has to explicitly return a value.
   */
  protected function jsExecute($selector, $script) {
    $driver = $this->getSession()->getDriver();
    $scriptWrapper = "return (function() {
            {{SCRIPT}}
          }());";
    $script = str_replace('{{ELEMENT}}', "document.querySelector('$selector')", $script);
    $script = str_replace('{{SCRIPT}}', $script, $scriptWrapper);

    return $driver->evaluateScript($script);
  }

}
