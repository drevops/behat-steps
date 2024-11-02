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
   * @Then the element :selector with the attribute :attribute and the value :value should exist
   */
  public function elementAssertAttributeWithValueExists(string $selector, string $attribute, mixed $value): void {
    $this->elementAssertAttributeWithValue($selector, $attribute, $value, TRUE, FALSE);
  }

  /**
   * Assert an element with selector and attribute containing a value exists.
   *
   * @Then the element :selector with the attribute :attribute and the value containing :value should exist
   */
  public function elementAssertAttributeContainingValueExists(string $selector, string $attribute, mixed $value): void {
    $this->elementAssertAttributeWithValue($selector, $attribute, $value, FALSE, FALSE);
  }

  /**
   * Assert an element with selector and attribute with a value exists.
   *
   * @Then the element :selector with the attribute :attribute and the value :value should not exist
   */
  public function elementAssertAttributeWithValueNotExists(string $selector, string $attribute, mixed $value): void {
    $this->elementAssertAttributeWithValue($selector, $attribute, $value, TRUE, TRUE);
  }

  /**
   * Assert an element with selector and attribute containing a value does not exist.
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

}
