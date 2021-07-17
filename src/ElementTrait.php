<?php

namespace IntegratedExperts\BehatSteps;

/**
 * Trait Element.
 *
 * @package IntegratedExperts\BehatSteps
 */
trait ElementTrait {

  /**
   * @Then I( should) see the :selector element with the :attribute attribute set to :value
   */
  public function elementAssertAttributeHasValue($selector, $attribute, $value) {
    $page = $this->getSession()->getPage();
    $elements = $page->findAll('css', $selector);

    if (empty($elements)) {
      throw new \Exception(sprintf('The "%s" element was not found on the page.', $selector));
    }

    $found = FALSE;
    $attr_found = FALSE;
    foreach ($elements as $element) {
      $attr = $element->getAttribute($attribute);
      if (!empty($attr)) {
        $attr_found = TRUE;
        if (strpos($attr, strval($value)) !== FALSE) {
          $found = TRUE;
          break;
        }
      }
      $attr_found = FALSE;
    }

    if (!$found) {
      if (!$attr_found) {
        throw new \Exception(sprintf('The "%s" attribute was not found on the element "%s".', $attribute, $selector));
      }
      else {
        throw new \Exception(sprintf('The "%s" attribute was found on the element "%s", but does not contain a value "%s".', $attribute, $selector, $value));
      }
    }
  }

}
