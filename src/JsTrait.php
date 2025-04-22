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
   * Given I accept all confirmation dialogs
   * @endcode
   *
   * @Given I accept all confirmation dialogs
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
   * Given I do not accept any confirmation dialogs
   * @endcode
   *
   * @Given I do not accept any confirmation dialogs
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
   * When I click on the element ".button"
   * @endcode
   *
   * @When I click on the element :selector
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
   * When I trigger the JS event :event on the element :selector.
   *
   * @When I trigger the JS event :event on the element :selector
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
   * @When I scroll to the element :selector
   */
  public function iScrollToElement(string $selector): void {
    $this->getSession()->executeScript("
      var element = document.querySelector('" . $selector . "');
      element.scrollIntoView( true );
    ");
  }

  /**
   * Assert the element :selector should be at the top of the viewport.
   *
   * @Then the element :selector should be at the top of the viewport
   */
  public function assertElementAtTopOfViewport(string $selector): void {
    $script = <<<JS
        (function() {
            var element = document.querySelector('{$selector}');
            var rect = element.getBoundingClientRect();
            return (rect.top >= 0 && rect.top <= window.innerHeight);
        })();
JS;
    $result = $this->getSession()->evaluateScript($script);
    if (!$result) {
      throw new \Exception(sprintf("Element with selector '%s' is not at the top of the viewport.", $selector));
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
