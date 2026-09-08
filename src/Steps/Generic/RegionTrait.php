<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Generic;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Then;
use Behat\Step\When;

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
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\RawContext
 */
trait RegionTrait {

  /**
   * Click a link within a region.
   *
   * @code
   * When I click the link "Read more" in the region "content"
   * @endcode
   */
  #[When('I click the link :link in the region :region')]
  public function regionClickLink(string $link, string $region): void {
    $element = $this->regionGet($region)->findLink($link);

    if (!$element instanceof NodeElement) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), sprintf('link in the "%s" region', $region), 'id|title|alt|text', $link);
    }

    $element->click();
  }

  /**
   * Press a button within a region.
   *
   * @code
   * When I press the button "Save" in the region "sidebar"
   * @endcode
   */
  #[When('I press the button :button in the region :region')]
  public function regionPressButton(string $button, string $region): void {
    $element = $this->regionGet($region)->findButton($button);

    if (!$element instanceof NodeElement) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), sprintf('button in the "%s" region', $region), 'id|name|title|alt|value', $button);
    }

    $element->press();
  }

  /**
   * Fill a field within a region.
   *
   * @code
   * When I fill in the field "Search" with "test" in the region "header"
   * @endcode
   */
  #[When('I fill in the field :field with :value in the region :region')]
  public function regionFillField(string $field, string $value, string $region): void {
    $this->regionGet($region)->fillField($field, $value);
  }

  /**
   * Check a checkbox within a region.
   *
   * @code
   * When I check the checkbox "Published" in the region "content"
   * @endcode
   */
  #[When('I check the checkbox :checkbox in the region :region')]
  public function regionCheckField(string $checkbox, string $region): void {
    $this->regionGet($region)->checkField($checkbox);
  }

  /**
   * Uncheck a checkbox within a region.
   *
   * @code
   * When I uncheck the checkbox "Promoted" in the region "content"
   * @endcode
   */
  #[When('I uncheck the checkbox :checkbox in the region :region')]
  public function regionUncheckField(string $checkbox, string $region): void {
    $this->regionGet($region)->uncheckField($checkbox);
  }

  /**
   * Assert that a region contains the text.
   *
   * @code
   * Then the region "content" should contain the text "Welcome"
   * @endcode
   */
  #[Then('the region :region should contain the text :text')]
  public function regionAssertContainsText(string $region, string $text): void {
    if (!str_contains($this->regionGet($region)->getText(), $text)) {
      throw new ExpectationException(sprintf('The text "%s" was not found in the "%s" region on the page %s.', $text, $region, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a region does not contain the text.
   *
   * @code
   * Then the region "content" should not contain the text "Error"
   * @endcode
   */
  #[Then('the region :region should not contain the text :text')]
  public function regionAssertNotContainsText(string $region, string $text): void {
    if (str_contains($this->regionGet($region)->getText(), $text)) {
      throw new ExpectationException(sprintf('The text "%s" was found in the "%s" region on the page %s.', $text, $region, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a region contains the heading.
   *
   * @code
   * Then the region "sidebar" should contain the heading "Latest news"
   * @endcode
   */
  #[Then('the region :region should contain the heading :heading')]
  public function regionAssertContainsHeading(string $region, string $heading): void {
    foreach ($this->regionGet($region)->findAll('css', 'h1, h2, h3, h4, h5, h6') as $element) {
      if (trim($element->getText()) === $heading) {
        return;
      }
    }

    throw new ElementNotFoundException($this->getSession()->getDriver(), sprintf('heading in the "%s" region', $region), 'text', $heading);
  }

  /**
   * Assert that a region does not contain the heading.
   *
   * @code
   * Then the region "sidebar" should not contain the heading "Admin"
   * @endcode
   */
  #[Then('the region :region should not contain the heading :heading')]
  public function regionAssertNotContainsHeading(string $region, string $heading): void {
    foreach ($this->regionGet($region)->findAll('css', 'h1, h2, h3, h4, h5, h6') as $element) {
      if (trim($element->getText()) === $heading) {
        throw new ExpectationException(sprintf('The heading "%s" was found in the "%s" region on the page %s.', $heading, $region, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
      }
    }
  }

  /**
   * Assert that a region contains the link.
   *
   * @code
   * Then the link "About us" should exist in the region "footer"
   * @endcode
   */
  #[Then('the link :link should exist in the region :region')]
  public function regionAssertLinkExists(string $link, string $region): void {
    if (!$this->regionGet($region)->findLink($link) instanceof NodeElement) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), sprintf('link in the "%s" region', $region), 'id|title|alt|text', $link);
    }
  }

  /**
   * Assert that a region does not contain the link.
   *
   * @code
   * Then the link "Admin" should not exist in the region "footer"
   * @endcode
   */
  #[Then('the link :link should not exist in the region :region')]
  public function regionAssertLinkNotExists(string $link, string $region): void {
    if ($this->regionGet($region)->findLink($link) instanceof NodeElement) {
      throw new ExpectationException(sprintf('The link "%s" was found in the "%s" region on the page %s.', $link, $region, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a region contains the button.
   *
   * @code
   * Then the button "Save" should exist in the region "content"
   * @endcode
   */
  #[Then('the button :button should exist in the region :region')]
  public function regionAssertButtonExists(string $button, string $region): void {
    if (!$this->regionGet($region)->findButton($button) instanceof NodeElement) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), sprintf('button in the "%s" region', $region), 'id|name|title|alt|value', $button);
    }
  }

  /**
   * Assert that a region does not contain the button.
   *
   * @code
   * Then the button "Delete" should not exist in the region "content"
   * @endcode
   */
  #[Then('the button :button should not exist in the region :region')]
  public function regionAssertButtonNotExists(string $button, string $region): void {
    if ($this->regionGet($region)->findButton($button) instanceof NodeElement) {
      throw new ExpectationException(sprintf('The button "%s" was found in the "%s" region on the page %s.', $button, $region, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a region contains an element matching the selector.
   *
   * @code
   * Then the element "blockquote" should exist in the region "content"
   * @endcode
   */
  #[Then('the element :selector should exist in the region :region')]
  public function regionAssertElementExists(string $selector, string $region): void {
    if ($this->regionGet($region)->findAll('css', $selector) === []) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), sprintf('element in the "%s" region', $region), 'css', $selector);
    }
  }

  /**
   * Assert that a region contains no element matching the selector.
   *
   * @code
   * Then the element "blockquote" should not exist in the region "content"
   * @endcode
   */
  #[Then('the element :selector should not exist in the region :region')]
  public function regionAssertElementNotExists(string $selector, string $region): void {
    if ($this->regionGet($region)->findAll('css', $selector) !== []) {
      throw new ExpectationException(sprintf('The element "%s" was found in the "%s" region on the page %s.', $selector, $region, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an element in a region has the exact text.
   *
   * @code
   * Then the element "h2" in the region "content" should have the text "News"
   * @endcode
   */
  #[Then('the element :selector in the region :region should have the text :text')]
  public function regionAssertElementText(string $selector, string $region, string $text): void {
    $this->regionFindElementByText($region, $selector, $text);
  }

  /**
   * Assert that no element in a region has the exact text.
   *
   * @code
   * Then the element "h2" in the region "content" should not have the text "News"
   * @endcode
   */
  #[Then('the element :selector in the region :region should not have the text :text')]
  public function regionAssertElementNotText(string $selector, string $region, string $text): void {
    foreach ($this->regionGet($region)->findAll('css', $selector) as $element) {
      if (trim($element->getText()) === $text) {
        throw new ExpectationException(sprintf('The text "%s" was found in the "%s" element in the "%s" region on the page %s.', $text, $selector, $region, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
      }
    }
  }

  /**
   * Assert that an element in a region has the attribute value.
   *
   * @code
   * Then the element "img" in the region "content" should have the attribute "alt" with the value "Logo"
   * @endcode
   */
  #[Then('the element :selector in the region :region should have the attribute :attribute with the value :value')]
  public function regionAssertElementAttribute(string $selector, string $region, string $attribute, string $value): void {
    foreach ($this->regionGet($region)->findAll('css', $selector) as $element) {
      if ($element->getAttribute($attribute) === $value) {
        return;
      }
    }

    throw new ExpectationException(sprintf('No "%s" element in the "%s" region has the attribute "%s" with the value "%s" on the page %s.', $selector, $region, $attribute, $value, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
  }

  /**
   * Assert that an element in a region with the text has the attribute value.
   *
   * @code
   * Then the element "a" with the text "Home" in the region "header" should have the attribute "href" with the value "/"
   * @endcode
   */
  #[Then('the element :selector with the text :text in the region :region should have the attribute :attribute with the value :value')]
  public function regionAssertElementTextAttribute(string $selector, string $text, string $region, string $attribute, string $value): void {
    $element = $this->regionFindElementByText($region, $selector, $text);

    if ($element->getAttribute($attribute) !== $value) {
      throw new ExpectationException(sprintf('The "%s" element with the text "%s" in the "%s" region does not have the attribute "%s" with the value "%s" on the page %s.', $selector, $text, $region, $attribute, $value, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an element in a region with the text has the CSS value.
   *
   * @code
   * Then the element "span" with the text "New" in the region "content" should have the CSS property "color" with the value "rgb(255, 0, 0)"
   * @endcode
   *
   * @javascript
   */
  #[Then('the element :selector with the text :text in the region :region should have the CSS property :property with the value :value')]
  public function regionAssertElementTextCssProperty(string $selector, string $text, string $region, string $property, string $value): void {
    $element = $this->regionFindElementByText($region, $selector, $text);
    $actual = $this->getSession()->getDriver()->evaluateScript(sprintf('window.getComputedStyle(document.evaluate(%s, document, null, 9, null).singleNodeValue).getPropertyValue(%s);', json_encode($element->getXpath()), json_encode($property)));

    if ($actual !== $value) {
      throw new ExpectationException(sprintf('The "%s" element with the text "%s" in the "%s" region has the CSS property "%s" with the value "%s", but "%s" was expected.', $selector, $text, $region, $property, is_scalar($actual) ? (string) $actual : gettype($actual), $value), $this->getSession()->getDriver());
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
  protected function regionGet(string $region): NodeElement {
    $element = $this->getSession()->getPage()->find('region', $region);

    if (!$element instanceof NodeElement) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'region', 'name', $region);
    }

    return $element;
  }

  /**
   * Find an element in a region whose text matches exactly.
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
  protected function regionFindElementByText(string $region, string $selector, string $text): NodeElement {
    $elements = $this->regionGet($region)->findAll('css', $selector);

    if ($elements === []) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), sprintf('element in the "%s" region', $region), 'css', $selector);
    }

    foreach ($elements as $element) {
      if (trim($element->getText()) === $text) {
        return $element;
      }
    }

    throw new ExpectationException(sprintf('The text "%s" was not found in the "%s" element in the "%s" region on the page %s.', $text, $selector, $region, $this->getSession()->getCurrentUrl()), $this->getSession()->getDriver());
  }

}
