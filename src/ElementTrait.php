<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

/**
 * Trait Element.
 *
 * Steps to work with HTML element.
 *
 * @package DrevOps\BehatSteps
 */
trait ElementTrait {

  /**
   * Assert an element with selector and attribute with a value exists.
   *
   * @code
   * Then the element "#main-content" with the attribute "class" and the value "content-wrapper" should exist
   * @endcode
   *
   * @Then the element :selector with the attribute :attribute and the value :value should exist
   */
  public function elementAssertAttributeWithValueExists(string $selector, string $attribute, mixed $value): void {
    $this->elementAssertAttributeWithValue($selector, $attribute, $value, TRUE, FALSE);
  }

  /**
   * Assert an element with selector and attribute containing a value exists.
   *
   * @code
   * Then the element "#main-content" with the attribute "class" and the value containing "content" should exist
   * @endcode
   *
   * @Then the element :selector with the attribute :attribute and the value containing :value should exist
   */
  public function elementAssertAttributeContainingValueExists(string $selector, string $attribute, mixed $value): void {
    $this->elementAssertAttributeWithValue($selector, $attribute, $value, FALSE, FALSE);
  }

  /**
   * Assert an element with selector and attribute with a value exists.
   *
   * @code
   * Then the element "#main-content" with the attribute "class" and the value "hidden" should not exist
   * @endcode
   *
   * @Then the element :selector with the attribute :attribute and the value :value should not exist
   */
  public function elementAssertAttributeWithValueNotExists(string $selector, string $attribute, mixed $value): void {
    $this->elementAssertAttributeWithValue($selector, $attribute, $value, TRUE, TRUE);
  }

  /**
   * Assert an element with selector and attribute containing a value does not exist.
   *
   * @code
   * Then the element "#main-content" with the attribute "class" and the value containing "hidden" should not exist
   * @endcode
   *
   * @Then the element :selector with the attribute :attribute and the value containing :value should not exist
   */
  public function elementAssertAttributeContainingValueNotExists(string $selector, string $attribute, mixed $value): void {
    $this->elementAssertAttributeWithValue($selector, $attribute, $value, FALSE, TRUE);
  }

  /**
   * Assert an element with selector and attribute with a value.
   *
   * @param string $selector
   *   The CSS selector.
   * @param string $attribute
   *   The attribute name.
   * @param mixed $value
   *   The value to assert.
   * @param bool $is_exact
   *   Whether to assert the value exactly.
   * @param bool $is_inverted
   *   Whether to assert the value is not present.
   *
   * @throws \Exception
   */
  protected function elementAssertAttributeWithValue(string $selector, string $attribute, mixed $value, $is_exact, $is_inverted): void {
    $page = $this->getSession()->getPage();
    $elements = $page->findAll('css', $selector);

    if (empty($elements)) {
      throw new \Exception(sprintf('The "%s" element does not exist.', $selector));
    }

    $attr_found = FALSE;
    $attr_value_found = FALSE;
    foreach ($elements as $element) {
      $attr_value = (string) $element->getAttribute($attribute);
      if (!empty($attr_value)) {
        $attr_found = TRUE;
        if ($is_exact) {
          if ($attr_value === strval($value)) {
            $attr_value_found = TRUE;
            break;
          }
        }
        elseif (str_contains($attr_value, strval($value))) {
          $attr_value_found = TRUE;
          break;
        }
      }
    }

    if (!$attr_found) {
      throw new \Exception(sprintf('The "%s" attribute does not exist on the element "%s".', $attribute, $selector));
    }

    if ($is_inverted && $attr_value_found) {
      $message = $is_exact
        ? sprintf('The "%s" attribute exists on the element "%s" with a value "%s", but it should not.', $attribute, $selector, $value)
        : sprintf('The "%s" attribute exists on the element "%s" with a value containing "%s", but it should not.', $attribute, $selector, $value);
      throw new \Exception($message);
    }
    elseif (!$is_inverted && !$attr_value_found) {
      $message = $is_exact
        ? sprintf('The "%s" attribute exists on the element "%s" with a value "%s", but it does not have a value "%s".', $attribute, $selector, $attr_value, $value)
        : sprintf('The "%s" attribute exists on the element "%s" with a value "%s", but it does not contain a value "%s".', $attribute, $selector, $attr_value, $value);
      throw new \Exception($message);
    }
  }

  /**
   * Assert the element :selector should be at the top of the viewport.
   *
   * @code
   * Then the element "#header" should be at the top of the viewport
   * @endcode
   *
   * @Then the element :selector should be at the top of the viewport
   */
  public function elementAssertElementAtTopOfViewport(string $selector): void {
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
  public function elementAcceptConfirmation(): void {
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
  public function elementDeclineConfirmation(): void {
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
  public function elementClick(string $selector): void {
    $selector = $this
      ->getSession()
      ->getPage()
      ->find('css', $selector);

    if (!$selector) {
      throw new \RuntimeException(sprintf('Element with selector "%s" not found on the page', $selector));
    }

    $selector->click();
  }

  /**
   * When I trigger the JS event :event on the element :selector.
   *
   * @code
   * When I trigger the JS event "click" on the element "#submit-button"
   * @endcode
   *
   * @When I trigger the JS event :event on the element :selector
   */
  public function elementTriggerEvent(string $event, string $selector): void {
    $script = "return (function(el) {
            if (el) {
              el.{$event}();
              return true;
            }
            return false;
        })({{ELEMENT}});";

    $result = $this->elementExecuteJs($selector, $script);

    if (!$result) {
      throw new \RuntimeException(sprintf('Unable to trigger "%s" event on an element "%s" with JavaScript', $event, $selector));
    }
  }

  /**
   * Scroll to an element with ID.
   *
   * @code
   * When I scroll to the element "#footer"
   * @endcode
   *
   * @When I scroll to the element :selector
   */
  public function elementScrollTo(string $selector): void {
    $page = $this->getSession()->getPage();
    $element = $page->find('css', $selector);

    if (!$element) {
      throw new \RuntimeException(sprintf('Cannot scroll to element "%s" as it was not found on the page', $selector));
    }

    $this->getSession()->executeScript("
      var element = document.querySelector('" . $selector . "');
      element.scrollIntoView( true );
    ");
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
  protected function elementExecuteJs(string $selector, string $script) {
    $driver = $this->getSession()->getDriver();
    $scriptWrapper = "return (function() {
            {{SCRIPT}}
          }());";
    $script = str_replace('{{ELEMENT}}', sprintf("document.querySelector('%s')", $selector), $script);
    $script = str_replace('{{SCRIPT}}', $script, $scriptWrapper);

    return $driver->evaluateScript($script);
  }

}
