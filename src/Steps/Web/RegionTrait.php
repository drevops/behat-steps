<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Helper\Web\HeadingTrait;

/**
 * Interact with and assert against named page regions.
 *
 * - Click links, press buttons, fill fields and toggle checkboxes in a region.
 * - Assert text, headings, links, buttons and elements within a region.
 *
 * A region name resolves through the `region` Mink selector, which reads the
 * `regions:` map in the extension configuration. Every step throws when the
 * name is not mapped or the mapped selector matches nothing on the page.
 *
 * @phpstan-require-extends \Behat\MinkExtension\Context\RawMinkContext
 */
trait RegionTrait {

  use HeadingTrait;

  /**
   * Click a link within a region.
   *
   * @code
   * When I follow the link "Read more" in the "content" region
   * @endcode
   *
   * @actor:person
   */
  #[When('I follow the link :link in the :region region')]
  public function regionClickLink(string $link, string $region): void {
    $element = $this->regionGet($region)->findLink($link);

    if (!$element instanceof NodeElement) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), sprintf('link in the region "%s"', $region), 'id|title|alt|text', $link);
    }

    $element->click();
  }

  /**
   * Press a button within a region.
   *
   * @code
   * When I press the button "Save" in the "sidebar" region
   * @endcode
   *
   * @actor:person
   */
  #[When('I press the button :button in the :region region')]
  public function regionPressButton(string $button, string $region): void {
    $element = $this->regionGet($region)->findButton($button);

    if (!$element instanceof NodeElement) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), sprintf('button in the region "%s"', $region), 'id|name|title|alt|value', $button);
    }

    $element->press();
  }

  /**
   * Fill a field within a region.
   *
   * @code
   * When I fill in the field "Search" with "test" in the "header" region
   * @endcode
   *
   * @actor:person
   */
  #[When('I fill in the field :field with :value in the :region region')]
  public function regionFillField(string $field, string $value, string $region): void {
    $this->regionGet($region)->fillField($field, $value);
  }

  /**
   * Check a checkbox within a region.
   *
   * @code
   * When I check the checkbox "Published" in the "content" region
   * @endcode
   *
   * @actor:person
   */
  #[When('I check the checkbox :checkbox in the :region region')]
  public function regionCheckField(string $checkbox, string $region): void {
    $this->regionGet($region)->checkField($checkbox);
  }

  /**
   * Uncheck a checkbox within a region.
   *
   * @code
   * When I uncheck the checkbox "Promoted" in the "content" region
   * @endcode
   *
   * @actor:person
   */
  #[When('I uncheck the checkbox :checkbox in the :region region')]
  public function regionUncheckField(string $checkbox, string $region): void {
    $this->regionGet($region)->uncheckField($checkbox);
  }

  /**
   * Assert that the text of a region contains a string.
   *
   * @code
   * Then I should see "Welcome" in the "content" region
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should see :text in the :region region')]
  public function regionAssertContainsText(string $region, string $text): void {
    if (!str_contains($this->regionGet($region)->getText(), $text)) {
      throw new ExpectationException(sprintf('The text "%s" was not found in the region "%s" on the page "%s".', $text, $region, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the text of a region does not contain a string.
   *
   * @code
   * Then I should not see "Error" in the "content" region
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should not see :text in the :region region')]
  public function regionAssertNotContainsText(string $region, string $text): void {
    if (str_contains($this->regionGet($region)->getText(), $text)) {
      throw new ExpectationException(sprintf('The text "%s" was found in the region "%s" on the page "%s".', $text, $region, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a region contains the heading.
   *
   * @code
   * Then I should see the heading "Latest news" in the "sidebar" region
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should see the heading :heading in the :region region')]
  public function regionAssertContainsHeading(string $region, string $heading): void {
    if (!$this->headingFind($this->regionGet($region), $heading) instanceof NodeElement) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), sprintf('heading in the region "%s"', $region), 'text', $heading);
    }
  }

  /**
   * Assert that a region does not contain the heading.
   *
   * @code
   * Then I should not see the heading "Admin" in the "sidebar" region
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should not see the heading :heading in the :region region')]
  public function regionAssertNotContainsHeading(string $region, string $heading): void {
    if ($this->headingFind($this->regionGet($region), $heading) instanceof NodeElement) {
      throw new ExpectationException(sprintf('The heading "%s" was found in the region "%s" on the page "%s".', $heading, $region, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a region contains the link.
   *
   * @code
   * Then I should see the link "About us" in the "footer" region
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should see the link :link in the :region region')]
  public function regionAssertLinkExists(string $link, string $region): void {
    if (!$this->regionGet($region)->findLink($link) instanceof NodeElement) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), sprintf('link in the region "%s"', $region), 'id|title|alt|text', $link);
    }
  }

  /**
   * Assert that a region does not contain the link.
   *
   * @code
   * Then I should not see the link "Admin" in the "footer" region
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should not see the link :link in the :region region')]
  public function regionAssertLinkNotExists(string $link, string $region): void {
    if ($this->regionGet($region)->findLink($link) instanceof NodeElement) {
      throw new ExpectationException(sprintf('The link "%s" was found in the region "%s" on the page "%s".', $link, $region, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a region contains the button.
   *
   * @code
   * Then I should see the button "Save" in the "content" region
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should see the button :button in the :region region')]
  public function regionAssertButtonExists(string $button, string $region): void {
    if (!$this->regionGet($region)->findButton($button) instanceof NodeElement) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), sprintf('button in the region "%s"', $region), 'id|name|title|alt|value', $button);
    }
  }

  /**
   * Assert that a region does not contain the button.
   *
   * @code
   * Then I should not see the button "Delete" in the "content" region
   * @endcode
   *
   * @actor:person
   */
  #[Then('I should not see the button :button in the :region region')]
  public function regionAssertButtonNotExists(string $button, string $region): void {
    if ($this->regionGet($region)->findButton($button) instanceof NodeElement) {
      throw new ExpectationException(sprintf('The button "%s" was found in the region "%s" on the page "%s".', $button, $region, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a region contains an element matching the selector.
   *
   * @code
   * Then the element "blockquote" in the "content" region should exist
   * @endcode
   *
   * @actor:system
   */
  #[Then('the element :selector in the :region region should exist')]
  public function regionAssertElementExists(string $selector, string $region): void {
    if ($this->regionGet($region)->findAll('css', $selector) === []) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), sprintf('element in the region "%s"', $region), 'css', $selector);
    }
  }

  /**
   * Assert that a region contains no element matching the selector.
   *
   * @code
   * Then the element "blockquote" in the "content" region should not exist
   * @endcode
   *
   * @actor:system
   */
  #[Then('the element :selector in the :region region should not exist')]
  public function regionAssertElementNotExists(string $selector, string $region): void {
    if ($this->regionGet($region)->findAll('css', $selector) !== []) {
      throw new ExpectationException(sprintf('The element "%s" was found in the region "%s" on the page "%s".', $selector, $region, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an element in a region has text exactly equal to a value.
   *
   * @code
   * Then the element "h2" in the "content" region should have the value "News"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the element :selector in the :region region should have the value :value')]
  public function regionAssertElementEquals(string $selector, string $region, string $value): void {
    $this->regionGetElementByText($region, $selector, $value);
  }

  /**
   * Assert that no element in a region has text exactly equal to a value.
   *
   * @code
   * Then the element "h2" in the "content" region should not have the value "News"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the element :selector in the :region region should not have the value :value')]
  public function regionAssertElementNotEquals(string $selector, string $region, string $value): void {
    if ($this->regionFindElementByText($region, $selector, $value) instanceof NodeElement) {
      throw new ExpectationException(sprintf('The text "%s" was found in the element "%s" in the region "%s" on the page "%s".', $value, $selector, $region, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an element in a region has the attribute value.
   *
   * @code
   * Then the element "img" in the "content" region should have the attribute "alt" with the value "Logo"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the element :selector in the :region region should have the attribute :attribute with the value :value')]
  public function regionAssertElementAttributeEquals(string $selector, string $region, string $attribute, string $value): void {
    if ($this->regionFindElementWithAttribute($region, $selector, $attribute, $value) instanceof NodeElement) {
      return;
    }

    throw new ExpectationException(sprintf('The element "%s" in the region "%s" does not have the attribute "%s" with the value "%s" on the page "%s".', $selector, $region, $attribute, $value, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
  }

  /**
   * Assert that an element in a region with the text has the attribute value.
   *
   * @code
   * Then the element "a" with the text "Home" in the "header" region should have the attribute "href" with the value "/"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the element :selector with the text :text in the :region region should have the attribute :attribute with the value :value')]
  public function regionAssertElementAttributeEqualsWithText(string $selector, string $text, string $region, string $attribute, string $value): void {
    $element = $this->regionGetElementByText($region, $selector, $text);

    if ($element->getAttribute($attribute) !== $value) {
      throw new ExpectationException(sprintf('The element "%s" with the text "%s" in the region "%s" does not have the attribute "%s" with the value "%s" on the page "%s".', $selector, $text, $region, $attribute, $value, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an element in a region with the text has the CSS value.
   *
   * @code
   * Then the element "span" with the text "New" in the "content" region should have the CSS property "color" with the value "rgb(255, 0, 0)"
   * @endcode
   *
   * @actor:system
   * @javascript
   */
  #[Then('the element :selector with the text :text in the :region region should have the CSS property :property with the value :value')]
  public function regionAssertElementCssPropertyEqualsWithText(string $selector, string $text, string $region, string $property, string $value): void {
    $element = $this->regionGetElementByText($region, $selector, $text);
    $actual = $this->getSession()->getDriver()->evaluateScript(sprintf('window.getComputedStyle(document.evaluate(%s, document, null, 9, null).singleNodeValue).getPropertyValue(%s);', json_encode($element->getXpath()), json_encode($property)));

    if ($actual !== $value) {
      throw new ExpectationException(sprintf('The element "%s" with the text "%s" in the region "%s" has the CSS property "%s" with the value "%s", but "%s" was expected.', $selector, $text, $region, $property, is_scalar($actual) ? (string) $actual : get_debug_type($actual), $value), $this->getSession()->getDriver());
    }
  }

  /**
   * Return a named region on the current page.
   *
   * @param string $region
   *   The region name as configured in the `regions:` map.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The region element.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When the name is not mapped or the mapped selector matches nothing.
   */
  public function regionGet(string $region): NodeElement {
    $element = $this->getSession()->getPage()->find('region', $region);

    if (!$element instanceof NodeElement) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'region', 'name', $region);
    }

    return $element;
  }

  /**
   * Return an element in a region whose text matches exactly.
   *
   * @param string $region
   *   The region name.
   * @param string $selector
   *   The CSS selector for the element.
   * @param string $text
   *   The text to match.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The matched element.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When the region holds no element matching the selector.
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When no matching element carries the text.
   */
  public function regionGetElementByText(string $region, string $selector, string $text): NodeElement {
    $element = $this->regionFindElementByText($region, $selector, $text);

    if ($element instanceof NodeElement) {
      return $element;
    }

    if ($this->regionGet($region)->findAll('css', $selector) === []) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), sprintf('element in the region "%s"', $region), 'css', $selector);
    }

    throw new ExpectationException(sprintf('The text "%s" was not found in the element "%s" in the region "%s" on the page "%s".', $text, $selector, $region, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
  }

  /**
   * Find an element in a region whose attribute holds a value.
   *
   * @param string $region
   *   The region name.
   * @param string $selector
   *   The CSS selector for the element.
   * @param string $attribute
   *   The name of the attribute.
   * @param string $value
   *   The value the attribute holds exactly.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The first matching element, or NULL when none carries the value.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When the region name is not mapped or its selector matches nothing.
   */
  public function regionFindElementWithAttribute(string $region, string $selector, string $attribute, string $value): ?NodeElement {
    foreach ($this->regionGet($region)->findAll('css', $selector) as $element) {
      if ($element->getAttribute($attribute) === $value) {
        return $element;
      }
    }

    return NULL;
  }

  /**
   * Find an element in a region whose text matches exactly.
   *
   * @param string $region
   *   The region name.
   * @param string $selector
   *   The CSS selector for the element.
   * @param string $text
   *   The text to match, compared with the element's trimmed text.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The first matching element, or NULL when none carries the text.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When the region name is not mapped or its selector matches nothing.
   */
  public function regionFindElementByText(string $region, string $selector, string $text): ?NodeElement {
    foreach ($this->regionGet($region)->findAll('css', $selector) as $element) {
      if (trim($element->getText()) === $text) {
        return $element;
      }
    }

    return NULL;
  }

}
