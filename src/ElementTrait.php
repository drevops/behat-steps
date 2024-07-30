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

}
