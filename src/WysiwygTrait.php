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

    $fieldId = $element->getAttribute('id');
    if (empty($fieldId)) {
      throw new ElementNotFoundException($this->getSession()->getDriver());
    }

    $parent_element = $element->getParent();

    // Support Ckeditor 4.
    $is_ckeditor_4 = !empty($driver->find($parent_element->getXpath() . "/div[contains(@class,'cke')]"));
    if ($is_ckeditor_4) {
      $this->getSession()
        ->executeScript("CKEDITOR.instances[\"$fieldId\"].setData(\"$value\");");

      return;
    }

    // Support Ckeditor 5.
    $ckeditor_5_element_selector = ".{$parent_element->getAttribute('class')} .ck-editor__editable";
    $this->getSession()
      ->executeScript(
        "
        const domEditableElement = document.querySelector(\"$ckeditor_5_element_selector\");
        if (domEditableElement.ckeditorInstance) {
          const editorInstance = domEditableElement.ckeditorInstance;
          if (editorInstance) {
            editorInstance.setData(\"$value\");
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
  protected function wysiwygFixStepArgument(string $argument): string|array {
    return str_replace('\\"', '"', $argument);
  }

}
