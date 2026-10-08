<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Helper\Web\HeadingTrait;
use DrevOps\BehatSteps\Helper\Web\StringTrait;

/**
 * Interact with HTML elements using CSS selectors and DOM attributes.
 *
 * - Assert element visibility, attribute values, and viewport positioning.
 * - Execute JavaScript-based interactions with element state verification.
 * - Handle confirmation dialogs and scrolling operations.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait ElementTrait {

  use HeadingTrait;
  use StringTrait;

  /**
   * Accept confirmation dialogs appearing on the page.
   *
   * @code
   * Given confirmation dialogs are accepted
   * @endcode
   *
   * @javascript
   */
  #[Given('confirmation dialogs are accepted')]
  public function elementAcceptConfirmation(): void {
    $this->getSession()->getDriver()->executeScript('window.confirm = function(){return true;};');
  }

  /**
   * Do not accept confirmation dialogs appearing on the page.
   *
   * @code
   * Given confirmation dialogs are declined
   * @endcode
   *
   * @javascript
   */
  #[Given('confirmation dialogs are declined')]
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
    $this->elementGet($selector)->click();
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
  public function elementClickWithIndex(string $selector, string $index): void {
    $index = $this->stringParseInteger($index, 'index');

    $elements = $this->getSession()->getPage()->findAll('css', $selector);
    $this->elementGetNth($elements, $index, sprintf('element matching "%s"', $selector))->click();
  }

  /**
   * Follow the link at the 1-based index among all links with the text.
   *
   * @code
   * When I follow the link "Read more" with the index 2
   * @endcode
   */
  #[When('I follow the link :link with the index :index')]
  public function elementFollowLinkWithIndex(string $link, string $index): void {
    $index = $this->stringParseInteger($index, 'index');

    $elements = $this->getSession()->getPage()->findAll('named', ['link', $link]);
    $this->elementGetNth($elements, $index, sprintf('link "%s"', $link))->click();
  }

  /**
   * Press the button at the 1-based index among all buttons with the label.
   *
   * @code
   * When I press the button "Delete" with the index 2
   * @endcode
   */
  #[When('I press the button :button with the index :index')]
  public function elementPressButtonWithIndex(string $button, string $index): void {
    $index = $this->stringParseInteger($index, 'index');

    $elements = $this->getSession()->getPage()->findAll('named', ['button', $button]);
    $this->elementGetNth($elements, $index, sprintf('button "%s"', $button))->press();
  }

  /**
   * Trigger a JS event on the element defined by the selector.
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
   * Scroll to the element matching a CSS selector.
   *
   * The element is scrolled to the center of the viewport by default. An
   * elementGetScrollIntoViewCenter() override returning FALSE aligns it to
   * the top of the viewport instead.
   *
   * @code
   * When I scroll to the element "#footer"
   * @endcode
   */
  #[When('I scroll to the element :selector')]
  public function elementScrollTo(string $selector): void {
    if ($this->elementGetScrollIntoViewCenter()) {
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
    $this->elementGet($selector)->mouseOver();
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
    $this->elementGet($selector);

    $this->elementExecuteJs($selector, '{{ELEMENT}}.focus();');
  }

  /**
   * Assert that a heading with the text exists.
   *
   * Matches the text of any `h1` to `h6` element exactly.
   *
   * @code
   * Then the heading "Latest news" should exist
   * @endcode
   */
  #[Then('the heading :heading should exist')]
  public function elementAssertHeadingExists(string $heading): void {
    if (!$this->headingFind($this->getSession()->getPage(), $heading) instanceof NodeElement) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'heading', 'text', $heading);
    }
  }

  /**
   * Assert that no heading with the text exists.
   *
   * @code
   * Then the heading "Admin" should not exist
   * @endcode
   */
  #[Then('the heading :heading should not exist')]
  public function elementAssertHeadingNotExists(string $heading): void {
    if ($this->headingFind($this->getSession()->getPage(), $heading) instanceof NodeElement) {
      throw new ExpectationException(sprintf('The heading "%s" was found on the page "%s".', $heading, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a button exists.
   *
   * Matches by id, name, title, alt or value.
   *
   * @code
   * Then the button "Save" should exist
   * @endcode
   */
  #[Then('the button :button should exist')]
  public function elementAssertButtonExists(string $button): void {
    if (!$this->getSession()->getPage()->findButton($button) instanceof NodeElement) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'button', 'id|name|title|alt|value', $button);
    }
  }

  /**
   * Assert that a button does not exist.
   *
   * @code
   * Then the button "Delete" should not exist
   * @endcode
   */
  #[Then('the button :button should not exist')]
  public function elementAssertButtonNotExists(string $button): void {
    if ($this->getSession()->getPage()->findButton($button) instanceof NodeElement) {
      throw new ExpectationException(sprintf('The button "%s" was found on the page "%s".', $button, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
    }
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
    if ($this->elementGetPosition($selector1) <= $this->elementGetPosition($selector2)) {
      throw new ExpectationException(sprintf('The element "%s" appears before the element "%s".', $selector1, $selector2), $this->getSession()->getDriver());
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
      throw new ExpectationException(sprintf('The text "%s" was not found.', $text1), $this->getSession()->getDriver());
    }
    if ($pos2 === FALSE) {
      throw new ExpectationException(sprintf('The text "%s" was not found.', $text2), $this->getSession()->getDriver());
    }

    if ($pos1 <= $pos2) {
      throw new ExpectationException(sprintf('The text "%s" appears before the text "%s".', $text1, $text2), $this->getSession()->getDriver());
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
  public function elementAssertExistsWithAttributeValue(string $selector, string $attribute, string $value): void {
    $this->elementAssertAttributeWithValue($selector, $attribute, $value, TRUE, FALSE);
  }

  /**
   * Assert an element with selector and attribute containing a value exists.
   *
   * @code
   * Then the element "#main-content" with the attribute "class" and a value containing "content" should exist
   * @endcode
   */
  #[Then('the element :selector with the attribute :attribute and a value containing :partial_value should exist')]
  public function elementAssertExistsWithAttributeContainingValue(string $selector, string $attribute, string $partial_value): void {
    $this->elementAssertAttributeWithValue($selector, $attribute, $partial_value, FALSE, FALSE);
  }

  /**
   * Assert an element with selector and attribute with a value does not exist.
   *
   * @code
   * Then the element "#main-content" with the attribute "class" and the value "hidden" should not exist
   * @endcode
   */
  #[Then('the element :selector with the attribute :attribute and the value :value should not exist')]
  public function elementAssertNotExistsWithAttributeValue(string $selector, string $attribute, string $value): void {
    $this->elementAssertAttributeWithValue($selector, $attribute, $value, TRUE, TRUE);
  }

  /**
   * Assert an element with selector and attribute containing a value does not exist.
   *
   * @code
   * Then the element "#main-content" with the attribute "class" and a value containing "hidden" should not exist
   * @endcode
   */
  #[Then('the element :selector with the attribute :attribute and a value containing :partial_value should not exist')]
  public function elementAssertNotExistsWithAttributeContainingValue(string $selector, string $attribute, string $partial_value): void {
    $this->elementAssertAttributeWithValue($selector, $attribute, $partial_value, FALSE, TRUE);
  }

  /**
   * Assert an element has a computed CSS property with a value.
   *
   * The value is compared against the value computed by the browser, not
   * against the value written in the stylesheet. `color: red` computes to
   * `rgb(255, 0, 0)` and `margin: 1em` computes to a pixel length.
   *
   * The property name is accepted in either `background-color` or
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
  public function elementAssertCssPropertyEquals(string $selector, string $property, string $value): void {
    $this->elementAssertCssProperty($selector, $property, $value, TRUE, FALSE);
  }

  /**
   * Assert an element has a computed CSS property containing a value.
   *
   * Use for multi-part computed values, such as `box-shadow`, `font-family`
   * or `transition`, where an exact match is brittle.
   *
   * @code
   * Then the element ".card" should have the CSS property "box-shadow" with a value containing "rgb(0, 0, 0)"
   * @endcode
   *
   * @javascript
   */
  #[Then('the element :selector should have the CSS property :property with a value containing :partial_value')]
  public function elementAssertCssPropertyContains(string $selector, string $property, string $partial_value): void {
    $this->elementAssertCssProperty($selector, $property, $partial_value, FALSE, FALSE);
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
  public function elementAssertCssPropertyNotEquals(string $selector, string $property, string $value): void {
    $this->elementAssertCssProperty($selector, $property, $value, TRUE, TRUE);
  }

  /**
   * Assert an element does not have a computed CSS property containing a value.
   *
   * @code
   * Then the element ".card" should not have the CSS property "box-shadow" with a value containing "inset"
   * @endcode
   *
   * @javascript
   */
  #[Then('the element :selector should not have the CSS property :property with a value containing :partial_value')]
  public function elementAssertCssPropertyNotContains(string $selector, string $property, string $partial_value): void {
    $this->elementAssertCssProperty($selector, $property, $partial_value, FALSE, TRUE);
  }

  /**
   * Assert that one element stacks above another.
   *
   * Compares the effective paint order rather than the `z-index` property,
   * because a `z-index` is only meaningful within its own stacking context.
   * A child of a stacking-context-forming ancestor can carry a high `z-index`
   * and still paint below an element with a lower one.
   *
   * The comparison walks the stacking context chain of both elements, finds
   * the context they share, and compares the 2 participants that branch off
   * it. Document order breaks a tie; painting order within a single stacking
   * context (floats, inline content and positioned descendants) is not
   * modeled.
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
   * Assert that the element is at the top of the viewport.
   *
   * @code
   * Then the element "#header" should be at the top of the viewport
   * @endcode
   */
  #[Then('the element :selector should be at the top of the viewport')]
  public function elementAssertElementAtTopOfViewport(string $selector): void {
    $result = $this->elementExecuteJs($selector, 'var rect = {{ELEMENT}}.getBoundingClientRect(); return (rect.top >= 0 && rect.top <= window.innerHeight);');
    if (!$result) {
      throw new ExpectationException(sprintf('The element "%s" is not at the top of the viewport.', $selector), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the element is centered in the viewport.
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
    $result = $this->elementExecuteJs($selector, 'var rect = {{ELEMENT}}.getBoundingClientRect(); var elementCenter = rect.top + rect.height / 2; var viewportThird = window.innerHeight / 3; return (elementCenter >= viewportThird && elementCenter <= viewportThird * 2);');
    if (!$result) {
      throw new ExpectationException(sprintf('The element "%s" is not centered in the viewport.', $selector), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an element is pinned to the top of the viewport.
   *
   * The element's top edge must be within 2 pixels of the viewport top; the
   * tolerance covers the sub-pixel offsets that normal rendering produces.
   * Use the step with an explicit tolerance for layouts that require a
   * larger one.
   *
   * This asserts where the element currently renders. A scroll beforehand
   * tells a pinned element apart from one that starts at the top of the
   * document.
   *
   * @code
   * When I scroll to the element "#footer"
   * Then the element "#header" should be pinned to the top of the viewport
   * @endcode
   *
   * @javascript
   */
  #[Then('the element :selector should be pinned to the top of the viewport')]
  public function elementAssertPinnedToTop(string $selector): void {
    $this->elementAssertPinnedToTopWithin($selector, 2, FALSE);
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
  public function elementAssertPinnedToTopWithTolerance(string $selector, string $tolerance): void {
    $this->elementAssertPinnedToTopWithin($selector, $this->stringParseInteger($tolerance, 'tolerance'), FALSE);
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
  public function elementAssertNotPinnedToTop(string $selector): void {
    $this->elementAssertPinnedToTopWithin($selector, 2, TRUE);
  }

  /**
   * Assert that the element has keyboard focus.
   *
   * Verifies that the element matched by the selector is the current
   * `document.activeElement`. This is the canonical check for tab-order tests,
   * skip-link behavior, modal focus traps, autofocus, and focus-after-action
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
   * Assert that element with specified CSS is visible on page.
   *
   * @code
   * Then the element ".alert-success" should be displayed
   * @endcode
   */
  #[Then('the element :selector should be displayed')]
  public function elementAssertVisible(string $selector): void {
    $this->elementGetVisible($selector);
  }

  /**
   * Assert that element with specified CSS is not visible on page.
   *
   * @code
   * Then the element ".error-message" should not be displayed
   * @endcode
   */
  #[Then('the element :selector should not be displayed')]
  public function elementAssertNotVisible(string $selector): void {
    if ($this->elementFindVisible($selector) instanceof NodeElement) {
      throw new ExpectationException(sprintf('The element "%s" is visible on the page, but it should not be.', $selector), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that element with specified CSS is displayed within the viewport.
   *
   * @code
   * Then the element ".hero-banner" should be displayed within the viewport
   * @endcode
   */
  #[Then('the element :selector should be displayed within the viewport')]
  public function elementAssertVisuallyVisible(string $selector): void {
    $this->elementGetVisible($selector);

    if (!$this->elementIsVisuallyVisible($selector, 0)) {
      throw new ExpectationException(sprintf('The element "%s" is not displayed within the viewport.', $selector), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that element with specified CSS is displayed within the viewport with a top offset.
   *
   * @code
   * Then the element ".sticky-header" should be displayed within the viewport with a top offset of 50 pixels
   * @endcode
   */
  #[Then('the element :selector should be displayed within the viewport with a top offset of :offset pixels')]
  public function elementAssertVisuallyVisibleWithOffset(string $selector, string $offset): void {
    $offset = $this->stringParseInteger($offset, 'offset');

    $this->elementGetVisible($selector);

    if (!$this->elementIsVisuallyVisible($selector, $offset)) {
      throw new ExpectationException(sprintf('The element "%s" is not displayed within the viewport with a top offset of %d pixels.', $selector, $offset), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that element with specified CSS is not displayed within the viewport with a top offset.
   *
   * @code
   * Then the element ".below-fold-content" should not be displayed within the viewport with a top offset of 0 pixels
   * @endcode
   */
  #[Then('the element :selector should not be displayed within the viewport with a top offset of :offset pixels')]
  public function elementAssertNotVisuallyVisibleWithOffset(string $selector, string $offset): void {
    $offset = $this->stringParseInteger($offset, 'offset');

    if ($this->elementIsVisuallyVisible($selector, $offset)) {
      throw new ExpectationException(sprintf('The element "%s" is displayed within the viewport with a top offset of %d pixels, but it should not be.', $selector, $offset), $this->getSession()->getDriver());
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
   * Then the element ".visually-hidden" should not be displayed within the viewport
   * @endcode
   */
  #[Then('the element :selector should not be displayed within the viewport')]
  public function elementAssertNotVisuallyVisible(string $selector): void {
    if ($this->elementIsVisuallyVisible($selector, 0)) {
      throw new ExpectationException(sprintf('The element "%s" is displayed within the viewport, but it should not be.', $selector), $this->getSession()->getDriver());
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
  public function elementAssertChildElementCount(string $parent, string $count, string $selector): void {
    $count = $this->stringParseInteger($count, 'count', 0);

    $actual = count($this->elementGet($parent)->findAll('css', $selector));

    if ($actual !== $count) {
      throw new ExpectationException(sprintf('Expected the element "%s" to contain %d element(s) matching "%s", but found %d.', $parent, $count, $selector, $actual), $this->getSession()->getDriver());
    }
  }

  /**
   * Whether to scroll elements to the center of the viewport.
   *
   * Returns TRUE (default) to use scrollIntoView() with center alignment,
   * which positions the element in the middle of the viewport. This avoids
   * interaction failures caused by sticky headers, admin toolbars, or fixed
   * navigation.
   *
   * Returns FALSE to use the scrollIntoView(true) behavior, which aligns
   * the element to the top of the viewport.
   *
   * Override this method in the context class to change the behavior:
   * @code
   * class FeatureContext extends DrupalContext {
   *   use ElementTrait;
   *   public function elementGetScrollIntoViewCenter(): bool {
   *     return FALSE;
   *   }
   * }
   * @endcode
   */
  protected function elementGetScrollIntoViewCenter(): bool {
    return $this->getOptionBool('element', 'scroll_into_view_center');
  }

  /**
   * Get the first element matching a CSS selector.
   *
   * @param string $selector
   *   The CSS selector.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The element.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When no element matches the selector.
   */
  public function elementGet(string $selector): NodeElement {
    $element = $this->getSession()->getPage()->find('css', $selector);

    if ($element === NULL) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector);
    }

    return $element;
  }

  /**
   * Find the first visible element matching a CSS selector.
   *
   * @param string $selector
   *   The CSS selector.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The first visible element, or NULL when no matching element is visible.
   */
  public function elementFindVisible(string $selector): ?NodeElement {
    foreach ($this->getSession()->getPage()->findAll('css', $selector) as $element) {
      if ($element->isVisible()) {
        return $element;
      }
    }

    return NULL;
  }

  /**
   * Get the first visible element matching a CSS selector.
   *
   * @param string $selector
   *   The CSS selector.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The first visible element.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When no element matches the selector.
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When no matching element is visible.
   */
  public function elementGetVisible(string $selector): NodeElement {
    $element = $this->elementFindVisible($selector);

    if ($element instanceof NodeElement) {
      return $element;
    }

    if ($this->getSession()->getPage()->findAll('css', $selector) === []) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector);
    }

    throw new ExpectationException(sprintf('The element "%s" is not visible on the page.', $selector), $this->getSession()->getDriver());
  }

  /**
   * Get where an element's markup starts within the markup of the page.
   *
   * @param string $selector
   *   The CSS selector of the element.
   *
   * @return int
   *   The offset, in bytes, of the first element matching the selector.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When no element matches the selector.
   */
  public function elementGetPosition(string $selector): int {
    $element = $this->elementGet($selector);
    $position = strpos((string) $this->getSession()->getPage()->getOuterHtml(), (string) $element->getOuterHtml());

    // @codeCoverageIgnoreStart
    if ($position === FALSE) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector);
    }
    // @codeCoverageIgnoreEnd
    return $position;
  }

  /**
   * Assert an element with selector and attribute with a value.
   *
   * @param string $selector
   *   The CSS selector.
   * @param string $attribute
   *   The attribute name.
   * @param string $value
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
  protected function elementAssertAttributeWithValue(string $selector, string $attribute, string $value, bool $is_exact, bool $is_inverted): void {
    $page = $this->getSession()->getPage();
    $elements = $page->findAll('css', $selector);

    if (empty($elements)) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'element', 'css', $selector);
    }

    $attribute_found = FALSE;
    $attribute_value_found = FALSE;
    foreach ($elements as $element) {
      $attribute_value = (string) $element->getAttribute($attribute);
      if ($attribute_value !== '') {
        $attribute_found = TRUE;
        if ($is_exact) {
          if ($attribute_value === $value) {
            $attribute_value_found = TRUE;
            break;
          }
        }
        elseif (str_contains($attribute_value, $value)) {
          $attribute_value_found = TRUE;
          break;
        }
      }
    }

    if (!$attribute_found) {
      throw new ExpectationException(sprintf('The attribute "%s" does not exist on the element "%s".', $attribute, $selector), $this->getSession()->getDriver());
    }

    if ($is_inverted && $attribute_value_found) {
      $message = $is_exact
        ? sprintf('The attribute "%s" exists on the element "%s" with a value "%s", but it should not.', $attribute, $selector, $value)
        : sprintf('The attribute "%s" exists on the element "%s" with a value containing "%s", but it should not.', $attribute, $selector, $value);
      throw new ExpectationException($message, $this->getSession()->getDriver());
    }

    if (!$is_inverted && !$attribute_value_found) {
      $message = $is_exact
        ? sprintf('The attribute "%s" exists on the element "%s" with a value "%s", but it does not have a value "%s".', $attribute, $selector, $attribute_value, $value)
        : sprintf('The attribute "%s" exists on the element "%s" with a value "%s", but it does not contain a value "%s".', $attribute, $selector, $attribute_value, $value);
      throw new ExpectationException($message, $this->getSession()->getDriver());
    }
  }

  /**
   * Assert the computed value of a CSS property on an element.
   *
   * A property with an empty computed value is reported as an error in both
   * the positive and the inverted form. The realistic cause is a misspelled
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
    $this->elementGet($selector);

    $property_js = json_encode($this->elementNormalizeCssProperty($property), JSON_UNESCAPED_SLASHES);
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
  protected function elementNormalizeCssProperty(string $property): string {
    if (str_starts_with($property, '--')) {
      return $property;
    }

    return strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $property));
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
    $this->elementGet($selector1);
    $this->elementGet($selector2);

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

    throw new ExpectationException(sprintf('Expected the element "%s" to stack %s the element "%s", but it stacks %s it: %s.', $selector1, $is_above ? 'above' : 'below', $selector2, $is_above ? 'below' : 'above', $reason), $this->getSession()->getDriver());
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
   *   A pipe-delimited string of the order, the effective z-index of each
   *   compared participant, and the basis of the comparison. The order is `1`
   *   when the first element stacks above the second one, `-1` when it stacks
   *   below it, and `0` when both selectors match the same element.
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
          // when it carries a z-index, whether or not it is positioned.
          var parent = el.parentElement;
          if (parent && style.zIndex !== 'auto') {
            var parentDisplay = window.getComputedStyle(parent).display;
            if (['flex', 'inline-flex', 'grid', 'inline-grid'].indexOf(parentDisplay) !== -1) {
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
        // above it unless its z-index is negative.
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
  protected function elementAssertPinnedToTopWithin(string $selector, int $tolerance, bool $is_inverted): void {
    if ($tolerance < 0) {
      throw new \RuntimeException(sprintf('The tolerance must be 0 or greater, but "%d" was given.', $tolerance));
    }

    $this->elementGet($selector);

    $script = 'var rect = {{ELEMENT}}.getBoundingClientRect(); return rect.top + "|" + rect.height;';
    [$top, $height] = explode('|', (string) $this->elementExecuteJs($selector, $script), 2);

    // An element that is not rendered reports a 0 by 0 box at the origin,
    // which would otherwise read as pinned.
    if ((float) $height <= 0) {
      if ($is_inverted) {
        return;
      }

      throw new ExpectationException(sprintf('Expected the element "%s" to be pinned to the top of the viewport, but it is not rendered.', $selector), $this->getSession()->getDriver());
    }

    $is_pinned = abs((float) $top) <= $tolerance;

    if (!$is_inverted && !$is_pinned) {
      throw new ExpectationException(sprintf('Expected the element "%s" to be pinned to the top of the viewport within %d pixel(s), but its top edge is at %s pixels.', $selector, $tolerance, $top), $this->getSession()->getDriver());
    }

    if ($is_inverted && $is_pinned) {
      throw new ExpectationException(sprintf('Expected the element "%s" to not be pinned to the top of the viewport, but its top edge is at %s pixels.', $selector, $top), $this->getSession()->getDriver());
    }
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
    $this->elementGet($selector);

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
        ? sprintf('Expected the element "%s" to have keyboard focus, but no element is focused.', $selector)
        : sprintf('Expected the element "%s" to have keyboard focus, but focus is on: %s', $selector, $result);
      throw new ExpectationException($message, $this->getSession()->getDriver());
    }

    if ($result === '__OK__') {
      throw new ExpectationException(sprintf('Expected the element "%s" to not have keyboard focus, but it does.', $selector), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert visible focus indicator state for an element.
   *
   * An element has a visible focus indicator when its computed outline has
   * a non-`none` style with a width greater than 0. A computed box-shadow
   * that is not `none` also counts as a visible indicator.
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
    $this->elementGet($selector);

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
      throw new ExpectationException(sprintf('Expected the element "%s" to have a visible focus outline, but outline-style is "%s", outline-width is "%s", box-shadow is "%s".', $selector, $outline_style, $outline_width, $box_shadow), $this->getSession()->getDriver());
    }

    if ($is_inverted && $is_visible) {
      throw new ExpectationException(sprintf('Expected the element "%s" to not have a visible focus outline, but outline-style is "%s", outline-width is "%s", box-shadow is "%s".', $selector, $outline_style, $outline_width, $box_shadow), $this->getSession()->getDriver());
    }
  }

  /**
   * Check whether an element is displayed within the viewport.
   *
   * @param string $selector
   *   CSS query selector.
   * @param int $offset
   *   Vertical element offset in pixels.
   *
   * @return mixed
   *   The raw result of the browser evaluation, truthy when the element is
   *   displayed within the viewport.
   */
  public function elementIsVisuallyVisible(string $selector, int $offset) {
    $selector_js = json_encode($selector, JSON_UNESCAPED_SLASHES);
    // The contents of this JS function should be copied as-is from the <script>
    // section at the bottom of tests/behat/fixtures/elements_relative.html.
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
   *   When the index is beyond the number of matches.
   * @throws \RuntimeException
   *   When the index is below 1.
   */
  public function elementGetNth(array $elements, int $index, string $subject): NodeElement {
    if ($index < 1) {
      throw new \RuntimeException(sprintf('The index must be 1 or greater, but "%d" was given.', $index));
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
   *   The script to execute. The '{{ELEMENT}}' token in the script references
   *   the element.
   *
   * @return mixed
   *   The result of script evaluation. Script has to explicitly return a value.
   */
  public function elementExecuteJs(string $selector, string $script) {
    // @codeCoverageIgnoreStart
    if (!str_contains($script, '{{ELEMENT}}')) {
      throw new \RuntimeException('The script must contain the {{ELEMENT}} token to reference the element.');
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

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function elementConfigSchema(): array {
    return [
      new Option('scroll_into_view_center', default: TRUE, description: 'Center an element in the viewport when scrolling to it, rather than aligning it to the top.'),
    ];
  }

}
