<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Mink\Exception\ElementHtmlException;
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
    catch (UnsupportedDriverActionException) {
      // For non-JS drivers process field in a standard way.
      $element->setValue($value);
      return;
    }

    $element_id = $element->getAttribute('id');
    if (empty($element_id)) {
      throw new ElementHtmlException('ID is empty', $driver, $element);
    }

    $parent_element = $element->getParent();

    // Support Ckeditor 4.
    $is_ckeditor_4 = !empty($driver->find($parent_element->getXpath() . "/div[contains(@class,'cke')]"));
    if ($is_ckeditor_4) {
      $this->getSession()
        ->executeScript(sprintf('CKEDITOR.instances["%s"].setData("%s");', $element_id, $value));

      return;
    }

    // Support Ckeditor 5.
    $this->getSession()
      ->executeScript(
        "
        const textareaElement = document.querySelector(\"#{$element_id}\");
        const domEditableElement = textareaElement.nextElementSibling.querySelector('.ck-editor__editable');
        if (domEditableElement.ckeditorInstance) {
          const editorInstance = domEditableElement.ckeditorInstance;
          if (editorInstance) {
            editorInstance.setData(\"{$value}\");
          } else {
            throw new Exception('Could not get the editor instance!');
          }
        } else {
          throw new Exception('Could not find the element!');
        }
        ");
  }

  /**
   * Returns fixed step argument (with \\" replaced back to ").
   */
  protected function wysiwygFixStepArgument(string $argument): string {
    return str_replace('\\"', '"', $argument);
  }

}
