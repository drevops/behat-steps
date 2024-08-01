<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Mink\Exception\ElementNotFoundException;

/**
 * Trait Element.
 *
 * Steps to work with HTML element.
 *
 * @package DrevOps\BehatSteps
 */
trait ElementTrait {

  /**
   * Assert that an element with selector and attribute with a value exists.
   *
   * @Then I( should) see the :selector element with the :attribute attribute set to :value
   */
  public function elementAssertAttributeHasValue(string $selector, string $attribute, mixed $value): void {
    $page = $this->getSession()->getPage();
    $elements = $page->findAll('css', $selector);

    if (empty($elements)) {
      throw new \Exception(sprintf('The "%s" element was not found on the page.', $selector));
    }

    $attr_found = FALSE;
    $attr_value_found = FALSE;
    foreach ($elements as $element) {
      $attr = $element->getAttribute($attribute);
      if (!empty($attr)) {
        $attr_found = TRUE;
        if (str_contains((string) $attr, strval($value))) {
          $attr_value_found = TRUE;
          break;
        }
      }
    }

    if (!$attr_value_found) {
      if (!$attr_found) {
        throw new \Exception(sprintf('The "%s" attribute was not found on the element "%s".', $attribute, $selector));
      }
      else {
        throw new \Exception(sprintf('The "%s" attribute was found on the element "%s", but does not contain a value "%s".', $attribute, $selector, $value));
      }
    }
  }

  /**
   * Assert element with wildcard pattern exists.
   *
   * Assert an element with selector and attribute with a value exists,
   * matching a wildcard pattern.
   *
   * @Then I( should) see the :selector element with a(n) :attribute attribute containing :value
   */
  public function elementAssertAttributeContains(string $selector, string $attribute, string $pattern): void {
    $page = $this->getSession()->getPage();
    $elements = $page->findAll('css', $selector);

    if (empty($elements)) {
      throw new \Exception(sprintf('The "%s" element was not found on the page.', $selector));
    }

    $attr_found = FALSE;
    $attr_pattern_matched = FALSE;

    foreach ($elements as $element) {
      $attr = $element->getAttribute($attribute);
      if (!empty($attr)) {
        $attr_found = TRUE;
        // Convert wildcard pattern to regex.
        $regex_pattern = '/' . str_replace(['*', '?'], ['.*', '.'], preg_quote($pattern, '/')) . '/';
        if (preg_match($regex_pattern, $attr)) {
          $attr_pattern_matched = TRUE;
          break;
        }
      }
    }

    if (!$attr_found) {
      throw new \Exception(sprintf('The "%s" attribute was not found on the element "%s".', $attribute, $selector));
    }

    if (!$attr_pattern_matched) {
      throw new \Exception(sprintf('No element with "%s" attribute matching the pattern "%s" found.', $attribute, $pattern));
    }
  }

  /**
   * Assert that an element with selector contains text.
   *
   * @Then I should see an element :selector using :type contains :text text
   */
  public function iShouldSeeAnElementUsingType(string $selector, string $type, string $text): void {
    if ($type === 'css') {
      $element = $this->getSession()->getPage()->find('css', $selector);
    }
    elseif ($type === 'xpath') {
      $element = $this->getSession()->getPage()->find('xpath', $selector);
    }
    else {
      throw new \Exception('Selector type must be "css" or "xpath".');
    }

    if (!$element) {
      $exception = new ElementNotFoundException($this->getSession()->getDriver(), NULL, $type, $selector);

      throw new \Exception($exception->getMessage());
    }

    if (!str_contains($element->getText(), $text)) {
      throw new \Exception(sprintf('The text "%s" was not found in the element "%s" using %s.', $text, $selector, $type));
    }
  }

}
