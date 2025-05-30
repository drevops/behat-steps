<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

/**
 * Interact with HTML elements using CSS selectors and DOM attributes.
 *
 * - Assert element visibility, attribute values, and viewport positioning.
 * - Execute JavaScript-based interactions with element state verification.
 * - Handle confirmation dialogs and scrolling operations.
 */
trait ElementTrait {

  /**
   * Assert that one element appears after another on the page.
   *
   * @code
   * Then the element "body" should appear after the element "head"
   * @endcode
   *
   * @Then the element :selector1 should appear after the element :selector2
   */
  public function elementAssertAfterElement(string $selector1, string $selector2): void {
    $session = $this->getSession();
    $page = $session->getPage();

    $element1 = $page->find('css', $selector1);
    $element2 = $page->find('css', $selector2);

    if (!$element1) {
      throw new \Exception(sprintf("Element with selector '%s' not found.", $selector1));
    }
    if (!$element2) {
      throw new \Exception(sprintf("Element with selector '%s' not found.", $selector2));
    }

    $text1 = $element1->getOuterHtml();
    $text2 = $element2->getOuterHtml();
    $content = $this->getSession()->getPage()->getOuterHtml();

    $pos1 = strpos((string) $content, (string) $text1);
    $pos2 = strpos((string) $content, (string) $text2);

    if ($pos1 === FALSE) {
      throw new \Exception(sprintf("Element with selector '%s' not found.", $selector1));
    }
    if ($pos2 === FALSE) {
      throw new \Exception(sprintf("Element with selector '%s' not found.", $selector2));
    }

    if ($pos1 <= $pos2) {
      throw new \Exception(sprintf("Element '%s' appears before '%s'", $selector1, $selector2));
    }
  }

  /**
   * Assert that one text string appears after another on the page.
   *
   * @code
   * Then the text "Welcome" should appear after the text "Home"
   * @endcode
   *
   * @Then the text :text1 should appear after the text :text2
   */
  public function elementAssertTextAfterText(string $text1, string $text2): void {
    $content = $this->getSession()->getPage()->getText();

    $pos1 = strpos((string) $content, $text1);
    $pos2 = strpos((string) $content, $text2);

    if ($pos1 === FALSE) {
      throw new \Exception(sprintf("Text was not found: '%s'.", $text1));
    }
    if ($pos2 === FALSE) {
      throw new \Exception(sprintf("Text was not found: '%s'.", $text2));
    }

    if ($pos1 <= $pos2) {
      throw new \Exception(sprintf("Text '%s' appears before '%s'", $text1, $text2));
    }
  }

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
  protected function elementAssertAttributeWithValue(string $selector, string $attribute, mixed $value, bool $is_exact, bool $is_inverted): void {
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
   * Assert that element with specified CSS is visible on page.
   *
   * @code
   * Then the element ".alert-success" should be displayed
   * @endcode
   *
   * @Then the element :selector should be displayed
   */
  public function elementAssertIsVisible(string $selector): void {
    $page = $this->getSession()->getPage();
    $nodes = $page->findAll('css', $selector);

    if ($nodes === []) {
      throw new \Exception(sprintf(
        'Element defined by "%s" selector is not present on the page.',
        $selector
      ));
    }

    foreach ($nodes as $node) {
      if ($node->isVisible()) {
        // Success – at least one match is visible.
        return;
      }
    }

    throw new \Exception(sprintf(
      'None of the elements defined by "%s" selector are visible on the page.',
      $selector
    ));
  }

  /**
   * Assert that element with specified CSS is not visible on page.
   *
   * @code
   * Then the element ".error-message" should not be displayed
   * @endcode
   *
   * @Then the element :selector should not be displayed
   */
  public function elementAssertIsNotVisible(string $selector): void {
    $element = $this->getSession()->getPage();
    $nodes = $element->findAll('css', $selector);

    foreach ($nodes as $node) {
      if ($node->isVisible()) {
        throw new \Exception(sprintf('Element defined by "%s" selector is visible on the page, but should not be.', $selector));
      }
    }
  }

  /**
   * Assert that element with specified CSS is displayed within a viewport.
   *
   * @code
   * Then the element ".hero-banner" should be displayed within a viewport
   * @endcode
   *
   * @Then the element :selector should be displayed within a viewport
   */
  public function elementAssertIsVisuallyVisible(string $selector): void {
    $this->elementAssertIsVisible($selector);

    if (!$this->elementIsVisuallyVisible($selector, 0)) {
      throw new \Exception(sprintf('Element(s) defined by "%s" selector is not displayed within a viewport.', $selector));
    }
  }

  /**
   * Assert that element with specified CSS is displayed within a viewport with a top offset.
   *
   * @code
   * Then the element ".sticky-header" should be displayed within a viewport with a top offset of 50 pixels
   * @endcode
   *
   * @Then the element :selector should be displayed within a viewport with a top offset of :number pixels
   */
  public function elementAssertIsVisuallyVisibleWithOffset(string $selector, int $number): void {
    $this->elementAssertIsVisible($selector);
    if (!$this->elementIsVisuallyVisible($selector, $number)) {
      throw new \Exception(sprintf('Element(s) defined by "%s" selector is not displayed within a viewport with a top offset of %d pixels.', $selector, $number));
    }
  }

  /**
   * Assert that element with specified CSS is not displayed within a viewport with a top offset.
   *
   * @code
   * Then the element ".below-fold-content" should not be displayed within a viewport with a top offset of 0 pixels
   * @endcode
   *
   * @Then the element :selector should not be displayed within a viewport with a top offset of :number pixels
   */
  public function elementAssertIsNotVisuallyVisibleWithOffset(string $selector, int $number): void {
    if ($this->elementIsVisuallyVisible($selector, $number)) {
      throw new \Exception(sprintf('Element(s) defined by "%s" selector is displayed within a viewport with a top offset of %d pixels, but should not be.', $selector, $number));
    }
  }

  /**
   * Assert that element with specified CSS is visually hidden on page.
   *
   * Visually hidden means either:
   * - element is not rendered in the layout (i.e., CSS is "display: none").
   * - element is rendered in the layout, but not visible to the viewer (i.e.,
   *   when one of the screen reader-only techniques is used).
   *
   * @code
   * Then the element ".visually-hidden" should not be displayed within a viewport
   * @endcode
   *
   * @Then the element :selector should not be displayed within a viewport
   */
  public function elementAssertIsVisuallyHidden(string $selector, int $offset = 0): void {
    if ($this->elementIsVisuallyVisible($selector, $offset)) {
      throw new \Exception(sprintf('Element(s) defined by "%s" selector is displayed within a viewport, but should not be.', $selector));
    }
  }

  /**
   * Assert that an element is displayed within a viewport using different FE techniques.
   *
   * @param string $selector
   *   CSS query selector.
   * @param int $offset
   *   (optional) Vertical element offset in pixels. Defaults to 0.
   *
   * @return bool
   *   TRUE if an element is displayed within a viewport, FALSE if not.
   */
  protected function elementIsVisuallyVisible(string $selector, int $offset) {
    // The contents of this JS function should be copied as-is from the <script>
    // section in the bottom of the tests/behat/fixtures/relative.html file.
    $scriptFunction = <<<JS
      function isElemVisible(selector, offset = 0) {
        var failures = [];
        document.querySelectorAll(selector).forEach(function (el) {
          // Inject a style to disable scrollbars for more consistent results.
          if (document.querySelectorAll('head #relative_style').length === 0) {
            document.querySelector('head').insertAdjacentHTML(
              'beforeend',
              '<style id="relative_style" type="text/css">::-webkit-scrollbar{display: none;}</style>'
            );
          }

          // Scroll to the element top, accounting for an offset.
          window.scroll({ top: el.offsetTop + offset });

          // Gather visibility constraints.
          const isVisible  = !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
          const hasHeight  = el.clientHeight > 1 || el.offsetHeight > 1;
          const notClipped = !(
            getComputedStyle(el).clip === 'rect(0px 0px 0px 0px)' &&
            getComputedStyle(el).position === 'absolute'
          );
          const rect       = el.getBoundingClientRect();
          onScreen = !(
            rect.left + rect.width <= 0 ||
            rect.top + rect.height <= 0 ||
            rect.left >= window.innerWidth ||
            rect.top >= window.innerHeight
          );

          if (!isVisible || !hasHeight || !notClipped || !onScreen) {
            failures.push(el);
          }
        });

        return failures.length === 0;
      }
    JS;
    // Include and call visibility assertion function.
    $script = <<<JS
      (function() {
        {$scriptFunction}
        return isElemVisible('{$selector}', {$offset});
      })();
    JS;

    return $this->getSession()->evaluateScript($script);
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
