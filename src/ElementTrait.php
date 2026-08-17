<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;

/**
 * Interact with HTML elements using CSS selectors and DOM attributes.
 *
 * - Assert element visibility, attribute values, and viewport positioning.
 * - Execute JavaScript-based interactions with element state verification.
 * - Handle confirmation dialogs and scrolling operations.
 */
trait ElementTrait {

  /**
   * Whether to scroll elements to the center of the viewport.
   *
   * Returns TRUE (default) to use scrollIntoView() with center alignment,
   * which positions the element in the middle of the viewport. This avoids
   * interaction failures caused by sticky headers, admin toolbars, or fixed
   * navigation.
   *
   * Returns FALSE to use the legacy scrollIntoView(true) behavior, which
   * aligns the element to the top of the viewport.
   *
   * Override this method in your context class to change the behavior:
   * @code
   * class FeatureContext extends DrupalContext {
   *   use ElementTrait;
   *   protected function elementScrollIntoViewCenter(): bool {
   *     return FALSE;
   *   }
   * }
   * @endcode
   */
  protected function elementScrollIntoViewCenter(): bool {
    return TRUE;
  }

  /**
   * Assert that one element appears after another on the page.
   *
   * @code
   * Then the element "body" should appear after the element "head"
   * @endcode
   */
  #[Then('the element :selector1 should appear after the element :selector2')]
  public function elementAssertAfterElement(string $selector1, string $selector2): void {
    $session = $this->getSession();
    $page = $session->getPage();

    $element1 = $page->find('css', $selector1);
    $element2 = $page->find('css', $selector2);

    if (!$element1) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector1);
    }
    if (!$element2) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector2);
    }

    $text1 = $element1->getOuterHtml();
    $text2 = $element2->getOuterHtml();
    $content = $this->getSession()->getPage()->getOuterHtml();

    $pos1 = strpos((string) $content, (string) $text1);
    $pos2 = strpos((string) $content, (string) $text2);

    // @codeCoverageIgnoreStart
    if ($pos1 === FALSE) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector1);
    }
    if ($pos2 === FALSE) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector2);
    }
    // @codeCoverageIgnoreEnd
    if ($pos1 <= $pos2) {
      throw new ExpectationException(sprintf('Element "%s" appears before "%s".', $selector1, $selector2), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that one text string appears after another on the page.
   *
   * @code
   * Then the text "Welcome" should appear after the text "Home"
   * @endcode
   */
  #[Then('the text :text1 should appear after the text :text2')]
  public function elementAssertTextAfterText(string $text1, string $text2): void {
    $content = $this->getSession()->getPage()->getText();

    $pos1 = strpos((string) $content, $text1);
    $pos2 = strpos((string) $content, $text2);

    if ($pos1 === FALSE) {
      throw new ExpectationException(sprintf('Text was not found: "%s".', $text1), $this->getSession()->getDriver());
    }
    if ($pos2 === FALSE) {
      throw new ExpectationException(sprintf('Text was not found: "%s".', $text2), $this->getSession()->getDriver());
    }

    if ($pos1 <= $pos2) {
      throw new ExpectationException(sprintf('Text "%s" appears before "%s".', $text1, $text2), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert an element with selector and attribute with a value exists.
   *
   * @code
   * Then the element "#main-content" with the attribute "class" and the value "content-wrapper" should exist
   * @endcode
   */
  #[Then('the element :selector with the attribute :attribute and the value :value should exist')]
  public function elementAssertAttributeWithValueExists(string $selector, string $attribute, mixed $value): void {
    $this->elementAssertAttributeWithValue($selector, $attribute, $value, TRUE, FALSE);
  }

  /**
   * Assert an element with selector and attribute containing a value exists.
   *
   * @code
   * Then the element "#main-content" with the attribute "class" and the value containing "content" should exist
   * @endcode
   */
  #[Then('the element :selector with the attribute :attribute and the value containing :value should exist')]
  public function elementAssertAttributeContainingValueExists(string $selector, string $attribute, mixed $value): void {
    $this->elementAssertAttributeWithValue($selector, $attribute, $value, FALSE, FALSE);
  }

  /**
   * Assert an element with selector and attribute with a value exists.
   *
   * @code
   * Then the element "#main-content" with the attribute "class" and the value "hidden" should not exist
   * @endcode
   */
  #[Then('the element :selector with the attribute :attribute and the value :value should not exist')]
  public function elementAssertAttributeWithValueNotExists(string $selector, string $attribute, mixed $value): void {
    $this->elementAssertAttributeWithValue($selector, $attribute, $value, TRUE, TRUE);
  }

  /**
   * Assert an element with selector and attribute containing a value does not exist.
   *
   * @code
   * Then the element "#main-content" with the attribute "class" and the value containing "hidden" should not exist
   * @endcode
   */
  #[Then('the element :selector with the attribute :attribute and the value containing :value should not exist')]
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
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   If no element matches the selector.
   * @throws \Behat\Mink\Exception\ExpectationException
   *   If the attribute or its value does not match the expectation.
   */
  protected function elementAssertAttributeWithValue(string $selector, string $attribute, mixed $value, bool $is_exact, bool $is_inverted): void {
    $page = $this->getSession()->getPage();
    $elements = $page->findAll('css', $selector);

    if (empty($elements)) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector);
    }

    $attribute_found = FALSE;
    $attribute_value_found = FALSE;
    foreach ($elements as $element) {
      $attribute_value = (string) $element->getAttribute($attribute);
      if (!empty($attribute_value)) {
        $attribute_found = TRUE;
        if ($is_exact) {
          if ($attribute_value === strval($value)) {
            $attribute_value_found = TRUE;
            break;
          }
        }
        elseif (str_contains($attribute_value, strval($value))) {
          $attribute_value_found = TRUE;
          break;
        }
      }
    }

    if (!$attribute_found) {
      throw new ExpectationException(sprintf('The "%s" attribute does not exist on the element "%s".', $attribute, $selector), $this->getSession()->getDriver());
    }

    if ($is_inverted && $attribute_value_found) {
      $message = $is_exact
        ? sprintf('The "%s" attribute exists on the element "%s" with a value "%s", but it should not.', $attribute, $selector, $value)
        : sprintf('The "%s" attribute exists on the element "%s" with a value containing "%s", but it should not.', $attribute, $selector, $value);
      throw new ExpectationException($message, $this->getSession()->getDriver());
    }

    if (!$is_inverted && !$attribute_value_found) {
      $message = $is_exact
        ? sprintf('The "%s" attribute exists on the element "%s" with a value "%s", but it does not have a value "%s".', $attribute, $selector, $attribute_value, $value)
        : sprintf('The "%s" attribute exists on the element "%s" with a value "%s", but it does not contain a value "%s".', $attribute, $selector, $attribute_value, $value);
      throw new ExpectationException($message, $this->getSession()->getDriver());
    }
  }

  /**
   * Assert an element has a computed CSS property with a value.
   *
   * The value is compared against the value computed by the browser, not
   * against the value written in the stylesheet: `color: red` computes to
   * `rgb(255, 0, 0)` and `margin: 1em` computes to a pixel length. The
   * property name is accepted in either `background-color` or
   * `backgroundColor` form; CSS custom properties are used verbatim. The
   * assertion applies to the first element matching the selector.
   *
   * @code
   * Then the element ".button" should have the CSS property "background-color" with the value "rgb(0, 0, 255)"
   * @endcode
   *
   * @javascript
   */
  #[Then('the element :selector should have the CSS property :property with the value :value')]
  public function elementAssertHasCssPropertyWithValue(string $selector, string $property, string $value): void {
    $this->elementAssertCssProperty($selector, $property, $value, TRUE, FALSE);
  }

  /**
   * Assert an element has a computed CSS property containing a value.
   *
   * Use for multi-part computed values, such as `box-shadow`, `font-family`
   * or `transition`, where an exact match is brittle.
   *
   * @code
   * Then the element ".card" should have the CSS property "box-shadow" with the value containing "rgb(0, 0, 0)"
   * @endcode
   *
   * @javascript
   */
  #[Then('the element :selector should have the CSS property :property with the value containing :value')]
  public function elementAssertHasCssPropertyContainingValue(string $selector, string $property, string $value): void {
    $this->elementAssertCssProperty($selector, $property, $value, FALSE, FALSE);
  }

  /**
   * Assert an element does not have a computed CSS property with a value.
   *
   * @code
   * Then the element ".button" should not have the CSS property "display" with the value "none"
   * @endcode
   *
   * @javascript
   */
  #[Then('the element :selector should not have the CSS property :property with the value :value')]
  public function elementAssertNotHasCssPropertyWithValue(string $selector, string $property, string $value): void {
    $this->elementAssertCssProperty($selector, $property, $value, TRUE, TRUE);
  }

  /**
   * Assert an element does not have a computed CSS property containing a value.
   *
   * @code
   * Then the element ".card" should not have the CSS property "box-shadow" with the value containing "inset"
   * @endcode
   *
   * @javascript
   */
  #[Then('the element :selector should not have the CSS property :property with the value containing :value')]
  public function elementAssertNotHasCssPropertyContainingValue(string $selector, string $property, string $value): void {
    $this->elementAssertCssProperty($selector, $property, $value, FALSE, TRUE);
  }

  /**
   * Assert the computed value of a CSS property on an element.
   *
   * A property with an empty computed value is reported as an error in both
   * the positive and the inverted form: the realistic cause is a misspelled
   * property name, which must not silently satisfy a negative assertion.
   *
   * @param string $selector
   *   The CSS selector.
   * @param string $property
   *   The CSS property name, in either kebab-case or camelCase.
   * @param string $value
   *   The value to assert.
   * @param bool $is_exact
   *   Whether to assert the value exactly.
   * @param bool $is_inverted
   *   Whether to assert the value is not present.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function elementAssertCssProperty(string $selector, string $property, string $value, bool $is_exact, bool $is_inverted): void {
    $element = $this->getSession()->getPage()->find('css', $selector);

    if (!$element) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector);
    }

    $property_js = json_encode($this->elementNormaliseCssProperty($property), JSON_UNESCAPED_SLASHES);
    $script = sprintf('return window.getComputedStyle({{ELEMENT}}).getPropertyValue(%s).trim();', $property_js);
    $actual = (string) $this->elementExecuteJs($selector, $script);

    if ($actual === '') {
      throw new ExpectationException(sprintf('The CSS property "%s" has no computed value on the element "%s".', $property, $selector), $this->getSession()->getDriver());
    }

    $is_found = $is_exact ? $actual === $value : str_contains($actual, $value);

    if ($is_inverted && $is_found) {
      $message = $is_exact
        ? sprintf('The CSS property "%s" on the element "%s" has a computed value "%s", but it should not.', $property, $selector, $actual)
        : sprintf('The CSS property "%s" on the element "%s" has a computed value "%s" containing "%s", but it should not.', $property, $selector, $actual, $value);
      throw new ExpectationException($message, $this->getSession()->getDriver());
    }

    if (!$is_inverted && !$is_found) {
      $message = $is_exact
        ? sprintf('The CSS property "%s" on the element "%s" has a computed value "%s", but it should have a value "%s".', $property, $selector, $actual, $value)
        : sprintf('The CSS property "%s" on the element "%s" has a computed value "%s", but it should contain a value "%s".', $property, $selector, $actual, $value);
      throw new ExpectationException($message, $this->getSession()->getDriver());
    }
  }

  /**
   * Convert a CSS property name to the form getPropertyValue() expects.
   *
   * @param string $property
   *   The CSS property name, in either kebab-case or camelCase.
   *
   * @return string
   *   The property name in kebab-case. Custom properties are returned as-is,
   *   because they are case-sensitive.
   */
  protected function elementNormaliseCssProperty(string $property): string {
    if (str_starts_with($property, '--')) {
      return $property;
    }

    return strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $property));
  }

  /**
   * Assert that one element stacks above another.
   *
   * Compares the effective paint order rather than the `z-index` property:
   * a `z-index` read from an element is only meaningful within its own
   * stacking context, so a child of a stacking-context-forming ancestor can
   * carry a high `z-index` and still paint below an element with a lower one.
   *
   * The comparison walks the stacking context chain of both elements, finds
   * the context they share, and compares the two participants that branch off
   * it, using document order to break a tie. Painting order within a single
   * stacking context (floats, inline content and positioned descendants) is
   * not modelled.
   *
   * @code
   * Then the element "#modal" should stack above the element "#page-header"
   * @endcode
   *
   * @javascript
   */
  #[Then('the element :selector1 should stack above the element :selector2')]
  public function elementAssertStacksAbove(string $selector1, string $selector2): void {
    $this->elementAssertStackingOrder($selector1, $selector2, TRUE);
  }

  /**
   * Assert that one element stacks below another.
   *
   * @code
   * Then the element "#page-header" should stack below the element "#modal"
   * @endcode
   *
   * @javascript
   */
  #[Then('the element :selector1 should stack below the element :selector2')]
  public function elementAssertStacksBelow(string $selector1, string $selector2): void {
    $this->elementAssertStackingOrder($selector1, $selector2, FALSE);
  }

  /**
   * Assert the stacking order of two elements.
   *
   * @param string $selector1
   *   The CSS selector of the first element.
   * @param string $selector2
   *   The CSS selector of the second element.
   * @param bool $is_above
   *   Whether the first element is expected to stack above the second one.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function elementAssertStackingOrder(string $selector1, string $selector2, bool $is_above): void {
    $page = $this->getSession()->getPage();

    if (!$page->find('css', $selector1)) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector1);
    }

    if (!$page->find('css', $selector2)) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector2);
    }

    [$order, $z1, $z2, $basis] = explode('|', $this->elementResolveStackingOrder($selector1, $selector2), 4);

    if ($basis === 'same-element') {
      throw new ExpectationException(sprintf('The selectors "%s" and "%s" match the same element.', $selector1, $selector2), $this->getSession()->getDriver());
    }

    $is_stacked_above = $order === '1';

    if ($is_stacked_above === $is_above) {
      return;
    }

    $reason = match ($basis) {
      'document-order' => sprintf('both have an effective z-index of %s and "%s" comes %s in the document', $z1, $selector2, $is_above ? 'later' : 'earlier'),
      'nesting-first' => sprintf('"%s" sits inside the stacking context of "%s" with an effective z-index of %s', $selector1, $selector2, $z1),
      'nesting-second' => sprintf('"%s" sits inside the stacking context of "%s" with an effective z-index of %s', $selector2, $selector1, $z2),
      default => sprintf('their effective z-indexes are %s and %s', $z1, $z2),
    };

    throw new ExpectationException(sprintf('Expected element "%s" to stack %s the element "%s", but it stacks %s it: %s.', $selector1, $is_above ? 'above' : 'below', $selector2, $is_above ? 'below' : 'above', $reason), $this->getSession()->getDriver());
  }

  /**
   * Resolve the stacking order of two elements in the browser.
   *
   * @param string $selector1
   *   The CSS selector of the first element.
   * @param string $selector2
   *   The CSS selector of the second element.
   *
   * @return string
   *   A pipe-delimited string of the order (`1` when the first element stacks
   *   above the second one, `-1` when it stacks below it, `0` when both
   *   selectors match the same element), the effective z-index of each
   *   compared participant, and the basis of the comparison.
   */
  protected function elementResolveStackingOrder(string $selector1, string $selector2): string {
    $selector1_js = json_encode($selector1, JSON_UNESCAPED_SLASHES);
    $selector2_js = json_encode($selector2, JSON_UNESCAPED_SLASHES);

    $script = <<<JS
      return (function() {
        function zIndexOf(el) {
          var parsed = parseInt(window.getComputedStyle(el).zIndex, 10);
          return isNaN(parsed) ? 0 : parsed;
        }

        function createsStackingContext(el) {
          if (el === document.documentElement) {
            return true;
          }

          var style = window.getComputedStyle(el);

          if (style.position === 'fixed' || style.position === 'sticky') {
            return true;
          }

          if (style.position !== 'static' && style.zIndex !== 'auto') {
            return true;
          }

          if (parseFloat(style.opacity) < 1 || style.mixBlendMode !== 'normal' || style.isolation === 'isolate') {
            return true;
          }

          var properties = ['transform', 'filter', 'perspective', 'clipPath', 'mask', 'backdropFilter'];
          for (var i = 0; i < properties.length; i++) {
            if (style[properties[i]] && style[properties[i]] !== 'none') {
              return true;
            }
          }

          if (/(^|\s)(layout|paint|strict|content)(\s|$)/.test(style.contain || '')) {
            return true;
          }

          if (/(transform|opacity|filter|perspective|clip-path|mask|backdrop-filter)/.test(style.willChange || '')) {
            return true;
          }

          // A flex or grid item takes part in its parent's stacking context
          // when it carries a z-index, without needing to be positioned.
          var parent = el.parentElement;
          if (parent && style.zIndex !== 'auto') {
            var parent_display = window.getComputedStyle(parent).display;
            if (['flex', 'inline-flex', 'grid', 'inline-grid'].indexOf(parent_display) !== -1) {
              return true;
            }
          }

          return false;
        }

        // The chain of stacking contexts the element sits in, from the root
        // element down, with the element itself as the last participant.
        function chainOf(el) {
          var chain = [el];
          var node = el.parentElement;

          while (node) {
            if (createsStackingContext(node)) {
              chain.unshift(node);
            }
            node = node.parentElement;
          }

          return chain;
        }

        var element1 = document.querySelector({$selector1_js});
        var element2 = document.querySelector({$selector2_js});

        if (element1 === element2) {
          return '0|0|0|same-element';
        }

        var chain1 = chainOf(element1);
        var chain2 = chainOf(element2);

        var index = 0;
        while (index < chain1.length && index < chain2.length && chain1[index] === chain2[index]) {
          index++;
        }

        // One element sits inside the other's stacking context, so it paints
        // above it unless it opted out with a negative z-index.
        if (index >= chain1.length) {
          var nested2 = zIndexOf(chain2[index]);
          return (nested2 < 0 ? '1' : '-1') + '|0|' + nested2 + '|nesting-second';
        }

        if (index >= chain2.length) {
          var nested1 = zIndexOf(chain1[index]);
          return (nested1 < 0 ? '-1' : '1') + '|' + nested1 + '|0|nesting-first';
        }

        var z1 = zIndexOf(chain1[index]);
        var z2 = zIndexOf(chain2[index]);

        if (z1 !== z2) {
          return (z1 > z2 ? '1' : '-1') + '|' + z1 + '|' + z2 + '|z-index';
        }

        // Equal z-indexes are resolved by document order: the participant that
        // comes later paints above the earlier one.
        var follows = chain1[index].compareDocumentPosition(chain2[index]) & Node.DOCUMENT_POSITION_FOLLOWING;

        return (follows ? '-1' : '1') + '|' + z1 + '|' + z2 + '|document-order';
      }());
JS;

    return (string) $this->getSession()->getDriver()->evaluateScript($script);
  }

  /**
   * Assert the element :selector should be at the top of the viewport.
   *
   * @code
   * Then the element "#header" should be at the top of the viewport
   * @endcode
   */
  #[Then('the element :selector should be at the top of the viewport')]
  public function elementAssertElementAtTopOfViewport(string $selector): void {
    $result = $this->elementExecuteJs($selector, 'var rect = {{ELEMENT}}.getBoundingClientRect(); return (rect.top >= 0 && rect.top <= window.innerHeight);');
    if (!$result) {
      throw new ExpectationException(sprintf('Element with selector "%s" is not at the top of the viewport.', $selector), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert the element :selector should be centered in the viewport.
   *
   * Checks that the vertical center of the element is within the middle third
   * of the viewport.
   *
   * @code
   * Then the element "#content" should be centered in the viewport
   * @endcode
   */
  #[Then('the element :selector should be centered in the viewport')]
  public function elementAssertElementCenteredInViewport(string $selector): void {
    $result = $this->elementExecuteJs($selector, 'var rect = {{ELEMENT}}.getBoundingClientRect(); var element_center = rect.top + rect.height / 2; var viewport_third = window.innerHeight / 3; return (element_center >= viewport_third && element_center <= viewport_third * 2);');
    if (!$result) {
      throw new ExpectationException(sprintf('Element with selector "%s" is not centered in the viewport.', $selector), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an element is pinned to the top of the viewport.
   *
   * The element's top edge has to sit within 2 pixels of the viewport top,
   * which absorbs the sub-pixel offsets that normal rendering produces. Use
   * the step with an explicit tolerance for layouts that need more slack.
   *
   * This asserts where the element currently renders, so scroll the page
   * first to tell a pinned element apart from one that merely starts at the
   * top of the document.
   *
   * @code
   * When I scroll to the element "#footer"
   * Then the element "#header" should be pinned to the top of the viewport
   * @endcode
   *
   * @javascript
   */
  #[Then('the element :selector should be pinned to the top of the viewport')]
  public function elementAssertIsPinnedToTop(string $selector): void {
    $this->elementAssertPinnedToTop($selector, 2, FALSE);
  }

  /**
   * Assert that an element is pinned to the top of the viewport within a tolerance.
   *
   * @code
   * Then the element "#header" should be pinned to the top of the viewport within 10 pixels
   * @endcode
   *
   * @javascript
   */
  #[Then('the element :selector should be pinned to the top of the viewport within :tolerance pixels')]
  public function elementAssertIsPinnedToTopWithTolerance(string $selector, int $tolerance): void {
    $this->elementAssertPinnedToTop($selector, $tolerance, FALSE);
  }

  /**
   * Assert that an element is not pinned to the top of the viewport.
   *
   * @code
   * When I scroll to the element "#footer"
   * Then the element "#header" should not be pinned to the top of the viewport
   * @endcode
   *
   * @javascript
   */
  #[Then('the element :selector should not be pinned to the top of the viewport')]
  public function elementAssertIsNotPinnedToTop(string $selector): void {
    $this->elementAssertPinnedToTop($selector, 2, TRUE);
  }

  /**
   * Assert that an element is pinned to the top of the viewport.
   *
   * @param string $selector
   *   The CSS selector.
   * @param int $tolerance
   *   The allowed distance, in pixels, between the top edge of the element
   *   and the top of the viewport.
   * @param bool $is_inverted
   *   Whether to assert that the element is not pinned to the top of the
   *   viewport.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function elementAssertPinnedToTop(string $selector, int $tolerance, bool $is_inverted): void {
    if ($tolerance < 0) {
      throw new ExpectationException(sprintf('The tolerance must be 0 or greater, but "%d" was given.', $tolerance), $this->getSession()->getDriver());
    }

    $element = $this->getSession()->getPage()->find('css', $selector);

    if (!$element) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector);
    }

    $script = 'var rect = {{ELEMENT}}.getBoundingClientRect(); return rect.top + "|" + rect.height;';
    [$top, $height] = explode('|', (string) $this->elementExecuteJs($selector, $script), 2);

    // An element that is not rendered reports a zero-sized box at the origin,
    // which would otherwise read as pinned.
    if ((float) $height <= 0) {
      if ($is_inverted) {
        return;
      }

      throw new ExpectationException(sprintf('Expected element "%s" to be pinned to the top of the viewport, but it is not rendered.', $selector), $this->getSession()->getDriver());
    }

    $is_pinned = abs((float) $top) <= $tolerance;

    if (!$is_inverted && !$is_pinned) {
      throw new ExpectationException(sprintf('Expected element "%s" to be pinned to the top of the viewport within %d pixel(s), but its top edge is at %s pixels.', $selector, $tolerance, $top), $this->getSession()->getDriver());
    }

    if ($is_inverted && $is_pinned) {
      throw new ExpectationException(sprintf('Expected element "%s" to not be pinned to the top of the viewport, but its top edge is at %s pixels.', $selector, $top), $this->getSession()->getDriver());
    }
  }

  /**
   * Accept confirmation dialogs appearing on the page.
   *
   * @code
   * Given I accept all confirmation dialogs
   * @endcode
   *
   * @javascript
   */
  #[Given('I accept all confirmation dialogs')]
  public function elementAcceptConfirmation(): void {
    $this->getSession()->getDriver()->executeScript('window.confirm = function(){return true;};');
  }

  /**
   * Do not accept confirmation dialogs appearing on the page.
   *
   * @code
   * Given I do not accept any confirmation dialogs
   * @endcode
   *
   * @javascript
   */
  #[Given('I do not accept any confirmation dialogs')]
  public function elementDeclineConfirmation(): void {
    $this->getSession()->getDriver()->executeScript('window.confirm = function(){return false;};');
  }

  /**
   * Click on the element defined by the selector.
   *
   * @code
   * When I click on the element ".button"
   * @endcode
   *
   * @javascript
   */
  #[When('I click on the element :selector')]
  public function elementClick(string $selector): void {
    $element = $this->getSession()->getPage()->find('css', $selector);

    if (!$element) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector);
    }

    $element->click();
  }

  /**
   * Click on the element at the 1-based index among all selector matches.
   *
   * Useful when a selector matches several repeated components (cards, rows,
   * menu items) and only the Nth one should be clicked.
   *
   * @code
   * When I click on the element ".card" with the index 2
   * @endcode
   *
   * @javascript
   */
  #[When('I click on the element :selector with the index :index')]
  public function elementClickByIndex(string $selector, int $index): void {
    $elements = $this->getSession()->getPage()->findAll('css', $selector);
    $this->elementFindNthOrFail($elements, $index, sprintf('element matching "%s"', $selector))->click();
  }

  /**
   * Follow the link at the 1-based index among all links with the text.
   *
   * @code
   * When I follow the link "Read more" with the index 2
   * @endcode
   */
  #[When('I follow the link :text with the index :index')]
  public function elementFollowLinkByIndex(string $text, int $index): void {
    $elements = $this->getSession()->getPage()->findAll('named', ['link', $text]);
    $this->elementFindNthOrFail($elements, $index, sprintf('link "%s"', $text))->click();
  }

  /**
   * Press the button at the 1-based index among all buttons with the label.
   *
   * @code
   * When I press the button "Delete" with the index 2
   * @endcode
   */
  #[When('I press the button :label with the index :index')]
  public function elementPressButtonByIndex(string $label, int $index): void {
    $elements = $this->getSession()->getPage()->findAll('named', ['button', $label]);
    $this->elementFindNthOrFail($elements, $index, sprintf('button "%s"', $label))->press();
  }

  /**
   * When I trigger the JS event :event on the element :selector.
   *
   * @code
   * When I trigger the JS event "click" on the element "#submit-button"
   * @endcode
   */
  #[When('I trigger the JS event :event on the element :selector')]
  public function elementTriggerEvent(string $event, string $selector): void {
    $event_js = json_encode($event, JSON_UNESCAPED_SLASHES);
    $this->elementExecuteJs($selector, sprintf('var event = new Event(%s, { bubbles: true }); {{ELEMENT}}.dispatchEvent(event); return true;', $event_js));
  }

  /**
   * Scroll to an element with ID.
   *
   * By default, scrolls the element to the center of the viewport. Override
   * the elementScrollIntoViewCenter() method to return FALSE to use the legacy
   * behavior that aligns the element to the top of the viewport.
   *
   * @code
   * When I scroll to the element "#footer"
   * @endcode
   */
  #[When('I scroll to the element :selector')]
  public function elementScrollTo(string $selector): void {
    if ($this->elementScrollIntoViewCenter()) {
      $this->elementExecuteJs($selector, '{{ELEMENT}}.scrollIntoView({ behavior: "auto", block: "center", inline: "center" });');
    }
    else {
      $this->elementExecuteJs($selector, '{{ELEMENT}}.scrollIntoView(true);');
    }
  }

  /**
   * Hover over an element identified by CSS selector.
   *
   * @code
   * When I hover over the element ".menu-item"
   * When I hover over the element "#tooltip-trigger"
   * @endcode
   */
  #[When('I hover over the element :selector')]
  public function elementHover(string $selector): void {
    $element = $this->getSession()->getPage()->find('css', $selector);

    if ($element === NULL) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector);
    }

    $element->mouseOver();
  }

  /**
   * Focus on an element by CSS selector.
   *
   * @code
   * When I focus on the element "#edit-name"
   * When I focus on the element ".form-text"
   * @endcode
   *
   * @javascript
   */
  #[When('I focus on the element :selector')]
  public function elementFocus(string $selector): void {
    $element = $this->getSession()->getPage()->find('css', $selector);

    if (!$element) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector);
    }

    $this->elementExecuteJs($selector, '{{ELEMENT}}.focus();');
  }

  /**
   * Assert that the element has keyboard focus.
   *
   * Verifies that the element matched by the selector is the current
   * `document.activeElement`. This is the canonical check for tab-order tests,
   * skip-link behaviour, modal focus traps, autofocus, and focus-after-action
   * flows.
   *
   * @code
   * Then the element "#edit-name" should have keyboard focus
   * @endcode
   *
   * @javascript
   */
  #[Then('the element :selector should have keyboard focus')]
  public function elementAssertHasKeyboardFocus(string $selector): void {
    $this->elementAssertKeyboardFocus($selector, FALSE);
  }

  /**
   * Assert that the element does not have keyboard focus.
   *
   * @code
   * Then the element "#edit-name" should not have keyboard focus
   * @endcode
   *
   * @javascript
   */
  #[Then('the element :selector should not have keyboard focus')]
  public function elementAssertNotHasKeyboardFocus(string $selector): void {
    $this->elementAssertKeyboardFocus($selector, TRUE);
  }

  /**
   * Assert that the element has a visible focus indicator.
   *
   * Verifies that the element renders a visible focus indicator via either
   * a CSS outline (non-`none` outline-style with a width greater than 0) or
   * a non-`none` box-shadow. Guards WCAG 2.4.7 (Focus Visible) and catches
   * accidental `outline: none` regressions introduced by stylesheet changes.
   *
   * @code
   * Then the element "#edit-name" should have a visible focus outline
   * @endcode
   *
   * @javascript
   */
  #[Then('the element :selector should have a visible focus outline')]
  public function elementAssertHasVisibleFocusOutline(string $selector): void {
    $this->elementAssertVisibleFocusOutline($selector, FALSE);
  }

  /**
   * Assert that the element does not have a visible focus indicator.
   *
   * @code
   * Then the element "#decorative-icon" should not have a visible focus outline
   * @endcode
   *
   * @javascript
   */
  #[Then('the element :selector should not have a visible focus outline')]
  public function elementAssertNotHasVisibleFocusOutline(string $selector): void {
    $this->elementAssertVisibleFocusOutline($selector, TRUE);
  }

  /**
   * Assert keyboard focus state for an element.
   *
   * @param string $selector
   *   The CSS selector.
   * @param bool $is_inverted
   *   Whether to assert that the element does not have keyboard focus.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function elementAssertKeyboardFocus(string $selector, bool $is_inverted): void {
    $element = $this->getSession()->getPage()->find('css', $selector);

    if (!$element) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector);
    }

    $script = <<<JS
      if ({{ELEMENT}} === document.activeElement) {
        return '__OK__';
      }
      if (!document.activeElement || document.activeElement === document.body) {
        return '__NONE__';
      }
      return document.activeElement.outerHTML.substring(0, 200);
JS;
    $result = (string) $this->elementExecuteJs($selector, $script);

    if (!$is_inverted) {
      if ($result === '__OK__') {
        return;
      }

      $message = $result === '__NONE__'
        ? sprintf('Expected element "%s" to have keyboard focus, but no element is focused.', $selector)
        : sprintf('Expected element "%s" to have keyboard focus, but focus is on: %s', $selector, $result);
      throw new ExpectationException($message, $this->getSession()->getDriver());
    }

    if ($result === '__OK__') {
      throw new ExpectationException(sprintf('Expected element "%s" to not have keyboard focus, but it does.', $selector), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert visible focus indicator state for an element.
   *
   * An element is considered to have a visible focus indicator when either
   * its computed outline has a non-`none` style with a width greater than 0,
   * or its computed box-shadow is not `none`.
   *
   * @param string $selector
   *   The CSS selector.
   * @param bool $is_inverted
   *   Whether to assert that the element does not have a visible focus
   *   indicator.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function elementAssertVisibleFocusOutline(string $selector, bool $is_inverted): void {
    $element = $this->getSession()->getPage()->find('css', $selector);

    if (!$element) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector);
    }

    $script = <<<JS
      var s = window.getComputedStyle({{ELEMENT}});
      return s.outlineStyle + '|' + s.outlineWidth + '|' + s.boxShadow;
JS;
    $result = (string) $this->elementExecuteJs($selector, $script);
    [$outline_style, $outline_width, $box_shadow] = explode('|', $result, 3);

    $has_outline = $outline_style !== 'none' && (float) $outline_width > 0;
    $has_shadow = $box_shadow !== 'none' && $box_shadow !== '';
    $is_visible = $has_outline || $has_shadow;

    if (!$is_inverted && !$is_visible) {
      throw new ExpectationException(sprintf('Expected element "%s" to have a visible focus outline, but outline-style is "%s", outline-width is "%s", box-shadow is "%s".', $selector, $outline_style, $outline_width, $box_shadow), $this->getSession()->getDriver());
    }

    if ($is_inverted && $is_visible) {
      throw new ExpectationException(sprintf('Expected element "%s" to not have a visible focus outline, but outline-style is "%s", outline-width is "%s", box-shadow is "%s".', $selector, $outline_style, $outline_width, $box_shadow), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that element with specified CSS is visible on page.
   *
   * @code
   * Then the element ".alert-success" should be displayed
   * @endcode
   */
  #[Then('the element :selector should be displayed')]
  public function elementAssertIsVisible(string $selector): void {
    $page = $this->getSession()->getPage();
    $elements = $page->findAll('css', $selector);

    if ($elements === []) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector);
    }

    foreach ($elements as $element) {
      if ($element->isVisible()) {
        // Success – at least one match is visible.
        return;
      }
    }

    throw new ExpectationException(sprintf('None of the elements defined by "%s" selector are visible on the page.', $selector), $this->getSession()->getDriver());
  }

  /**
   * Assert that element with specified CSS is not visible on page.
   *
   * @code
   * Then the element ".error-message" should not be displayed
   * @endcode
   */
  #[Then('the element :selector should not be displayed')]
  public function elementAssertIsNotVisible(string $selector): void {
    $page = $this->getSession()->getPage();
    $elements = $page->findAll('css', $selector);

    foreach ($elements as $element) {
      if ($element->isVisible()) {
        throw new ExpectationException(sprintf('Element defined by "%s" selector is visible on the page, but should not be.', $selector), $this->getSession()->getDriver());
      }
    }
  }

  /**
   * Assert that element with specified CSS is displayed within a viewport.
   *
   * @code
   * Then the element ".hero-banner" should be displayed within a viewport
   * @endcode
   */
  #[Then('the element :selector should be displayed within a viewport')]
  public function elementAssertIsVisuallyVisible(string $selector): void {
    $this->elementAssertIsVisible($selector);

    if (!$this->elementIsVisuallyVisible($selector, 0)) {
      throw new ExpectationException(sprintf('Element(s) defined by "%s" selector is not displayed within a viewport.', $selector), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that element with specified CSS is displayed within a viewport with a top offset.
   *
   * @code
   * Then the element ".sticky-header" should be displayed within a viewport with a top offset of 50 pixels
   * @endcode
   */
  #[Then('the element :selector should be displayed within a viewport with a top offset of :number pixels')]
  public function elementAssertIsVisuallyVisibleWithOffset(string $selector, int $number): void {
    $this->elementAssertIsVisible($selector);
    if (!$this->elementIsVisuallyVisible($selector, $number)) {
      throw new ExpectationException(sprintf('Element(s) defined by "%s" selector is not displayed within a viewport with a top offset of %d pixels.', $selector, $number), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that element with specified CSS is not displayed within a viewport with a top offset.
   *
   * @code
   * Then the element ".below-fold-content" should not be displayed within a viewport with a top offset of 0 pixels
   * @endcode
   */
  #[Then('the element :selector should not be displayed within a viewport with a top offset of :number pixels')]
  public function elementAssertIsNotVisuallyVisibleWithOffset(string $selector, int $number): void {
    if ($this->elementIsVisuallyVisible($selector, $number)) {
      throw new ExpectationException(sprintf('Element(s) defined by "%s" selector is displayed within a viewport with a top offset of %d pixels, but should not be.', $selector, $number), $this->getSession()->getDriver());
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
   */
  #[Then('the element :selector should not be displayed within a viewport')]
  public function elementAssertIsVisuallyHidden(string $selector, int $offset = 0): void {
    if ($this->elementIsVisuallyVisible($selector, $offset)) {
      throw new ExpectationException(sprintf('Element(s) defined by "%s" selector is displayed within a viewport, but should not be.', $selector), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert the number of elements matching a selector within a parent element.
   *
   * @code
   * Then the element "#main-nav" should contain 3 elements matching ".menu-item"
   * @endcode
   */
  #[Then('the element :parent should contain :count element(s) matching :selector')]
  public function elementAssertChildElementCount(string $parent, int $count, string $selector): void {
    $parent_element = $this->getSession()->getPage()->find('css', $parent);

    if (!$parent_element) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $parent);
    }

    $actual = count($parent_element->findAll('css', $selector));

    if ($actual !== $count) {
      throw new ExpectationException(sprintf('Expected the element "%s" to contain %d element(s) matching "%s", but found %d.', $parent, $count, $selector, $actual), $this->getSession()->getDriver());
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
    $selector_js = json_encode($selector, JSON_UNESCAPED_SLASHES);
    // The contents of this JS function should be copied as-is from the <script>
    // section in the bottom of the tests/behat/fixtures/relative.html file.
    $script_function = <<<JS
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
        {$script_function}
        return isElemVisible({$selector_js}, {$offset});
      })();
    JS;

    return $this->getSession()->evaluateScript($script);
  }

  /**
   * Return the element at a 1-based index or throw a clear error.
   *
   * @param \Behat\Mink\Element\NodeElement[] $elements
   *   The matched elements, in document order.
   * @param int $index
   *   The 1-based index of the element to return.
   * @param string $subject
   *   Human-readable description of what was searched for (for example,
   *   'link "Read more"'), used to build the error messages.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The element at the requested index.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When no elements matched.
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When the index is below 1 or beyond the number of matches.
   */
  protected function elementFindNthOrFail(array $elements, int $index, string $subject): NodeElement {
    if ($index < 1) {
      throw new ExpectationException(sprintf('The index must be 1 or greater, but "%d" was given.', $index), $this->getSession()->getDriver());
    }

    if ($elements === []) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), $subject);
    }

    if ($index > count($elements)) {
      throw new ExpectationException(sprintf('Cannot use the %s at index %d: only %d found.', $subject, $index, count($elements)), $this->getSession()->getDriver());
    }

    return $elements[$index - 1];
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
    // @codeCoverageIgnoreStart
    if (!str_contains($script, '{{ELEMENT}}')) {
      throw new \InvalidArgumentException('The script must contain the {{ELEMENT}} token to reference the element.');
    }
    // @codeCoverageIgnoreEnd
    $selector_js = json_encode($selector, JSON_UNESCAPED_SLASHES);

    $script_wrapper = <<<JS
      return (function() {
        var element = document.querySelector({$selector_js});
        if (!element) {
          throw new Error('Element with selector ' + {$selector_js} + ' not found.');
        }
        {{SCRIPT}}
      }());
JS;
    $script = str_replace('{{ELEMENT}}', 'element', $script);
    $script = str_replace('{{SCRIPT}}', $script, $script_wrapper);

    return $this->getSession()->getDriver()->evaluateScript($script);
  }

}
