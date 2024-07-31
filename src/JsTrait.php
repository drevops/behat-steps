<?php

declare(strict_types=1);

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
   * @param \Behat\Behat\Hook\Scope\BeforeScenarioScope $scope
   *   Scenario scope.
   *
   * @BeforeScenario
   */
  public function jsBeforeScenarioInit(BeforeScenarioScope $scope): void {
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
  public function jsAcceptConfirmation(): void {
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
  public function jsAcceptNotConfirmation(): void {
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
  public function jsClickOnElement(string $element): void {
    $element = $this
      ->getSession()
      ->getPage()
      ->find('css', $element);

    $element->click();
  }

  /**
   * Trigger an event on the specified element.
   *
   * @When I trigger JS :event event on :selector element
   */
  public function jsTriggerElementEvent(string $event, string $selector): void {
    $script = "return (function(el) {
            if (el) {
              el.{$event}();
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
   * Scroll to an element with ID.
   *
   * @Then /^I scroll to an? element with id "([^"]*)"$/
   */
  public function iScrollToElementWithId(string $id): void {
    $this->getSession()->executeScript("
      var element = document.getElementById('" . $id . "');
      element.scrollIntoView( true );
    ");
  }

  /**
   * Assert the element with id at the top of page.
   *
   * @Then the element with id :id should be at the top of the page
   */
  public function assertElementAtTopOfPage(string $id): void {
    $script = <<<JS
        (function() {
            var element = document.getElementById('$id');
            var rect = element.getBoundingClientRect();
            return (rect.top >= 0 && rect.top <= window.innerHeight);
        })();
JS;
    $result = $this->getSession()->evaluateScript($script);
    if (!$result) {
      throw new \Exception("Element with ID '$id' is not at the top of the page.");
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
  protected function jsExecute(string $selector, string $script) {
    $driver = $this->getSession()->getDriver();
    $scriptWrapper = "return (function() {
            {{SCRIPT}}
          }());";
    $script = str_replace('{{ELEMENT}}', sprintf("document.querySelector('%s')", $selector), $script);
    $script = str_replace('{{SCRIPT}}', $script, $scriptWrapper);

    return $driver->evaluateScript($script);
  }

}
