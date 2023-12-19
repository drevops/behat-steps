<?php

namespace DrevOps\BehatSteps;

use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\UnsupportedDriverActionException;

/**
 * Trait WysiwygTrait.
 *
 * Trait to handle WYSIWYG fields.
 */
trait WysiwygTrait {

  use KeyboardTrait;

  /**
   * Set value for WYSIWYG field.
   *
   * If used with Selenium driver, it will try to find associated WYSIWYG and
   * fill it in. If used with webdriver - it will fill in the field as normal.
   *
   * @When /^(?:|I )fill in WYSIWYG "(?P<field>(?:[^"]|\\")*)" with "(?P<value>(?:[^"]|\\")*)"$/
   */
  public function wysiwygFillField(string $field, string $value): void {
    $field = $this->wysiwygFixStepArgument($field);
    $value = $this->wysiwygFixStepArgument($value);

    $page = $this->getSession()->getPage();
    $element = $page->findField($field);

    if ($element === NULL) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'form field', 'id|name|label|value|placeholder', $field);
    }

    $driver = $this->getSession()->getDriver();
    try {
      $driver->evaluateScript('true');
    }
    catch (UnsupportedDriverActionException $exception) {
      // For non-JS drivers process field in a standard way.
      $element->setValue($value);

      return;
    }

    // For a JS-capable driver, try to find WYSIWYG iframe as a child of the
    // following sibling.
    $iframe_xpath = $element->getXpath() . "/following-sibling::div[contains(@class, 'cke')]//iframe";
    $page_iframe_elements = $driver->find($iframe_xpath);
    if (empty($page_iframe_elements[0])) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'WYSIWYG form field', 'id|name|label|value|placeholder', $field);
    }

    $iframe_element = reset($page_iframe_elements);

    // @note: Selenium's frame() expects frame id as an HTML element "id"
    // attribute value or as an 0-based index of the iframe on the page.
    // WYSIWYG iframe does not contain HTML "id" attribute, so we need to find
    // the index of the iframe on the page.
    //
    // Find all iframes on the page.
    $page_iframe_elements = $driver->find('//iframe');

    // Filter all iframes by finding parent WYSIWYG wrapper and comparing the
    // iframe element being filtered to the found per-field iframe element from
    // above.
    // Note that, at this point, we are guaranteed to find at least one matching
    // iframe element as an exception would be thrown otherwise.
    $index = 0;
    foreach ($page_iframe_elements as $page_iframe_element) {
      $wrapper_xpath = $page_iframe_element->getXpath() . "/ancestor::div[contains(@class, 'cke')]";
      $found_wrappers = $driver->find($wrapper_xpath);
      if (!empty($found_wrappers) && $page_iframe_element->getOuterHtml() == $iframe_element->getOuterHtml()) {
        break;
      }
      $index++;
    }

    // Select WYSIWYG iframe frame.
    $driver->switchToIFrame((string) $index);

    // Type value as keys into 'body' of iframe.
    foreach (str_split($value) as $char) {
      $this->keyboardTriggerKey('//body', $char);
    }

    // Reset frame to the default window.
    $driver->switchToIFrame(NULL);
  }

  /**
   * Returns fixed step argument (with \\" replaced back to ").
   */
  protected function wysiwygFixStepArgument(string $argument): string|array {
    return str_replace('\\"', '"', $argument);
  }

}
