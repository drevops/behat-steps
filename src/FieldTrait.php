<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementHtmlException;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\UnsupportedDriverActionException;

/**
 * Interacts with and validates form fields.
 *
 * Field-related steps.
 *
 * @package DrevOps\BehatSteps
 */
trait FieldTrait {

  use KeyboardTrait;

  /**
   * Assert that field exists on the page using id,name,label or value.
   *
   * @code
   * Then the field "Body" should exist
   * Then the field "field_body" should exist
   * @endcode
   *
   * @Then the field :name should exist
   */
  public function fieldAssertExists(string $name): NodeElement {
    $page = $this->getSession()->getPage();
    $field = $page->findField($name);
    // Try to resolve by ID.
    $field = $field ? $field : $page->findById($name);

    if ($field === NULL) {
      $exception = new ElementNotFoundException($this->getSession()
        ->getDriver(), 'form field', 'id|name|label|value', $name);

      throw new \Exception($exception->getMessage());
    }

    return $field;
  }

  /**
   * Assert that field does not exist on the page using id,name,label or value.
   *
   * @code
   * Then the field "Body" should not exist
   * Then the field "field_body" should not exist
   * @endcode
   *
   * @Then the field :name should not exist
   */
  public function fieldAssertNotExists(string $name): void {
    $page = $this->getSession()->getPage();
    $field = $page->findField($name);
    // Try to resolve by ID.
    $field = $field ? $field : $page->findById($name);

    if ($field !== NULL) {
      throw new \Exception(sprintf('A field "%s" appears on this page, but it should not.', $name));
    }
  }

  /**
   * Assert whether the field has a state.
   *
   * @code
   * Then the field "Body" should be "disabled"
   * Then the field "field_body" should be "disabled"
   * Then the field "Tags" should be "enabled"
   * Then the field "field_tags" should be "not enabled"
   * @endcode
   *
   * @Then the field :name should be :enabled_or_disabled
   */
  public function fieldAssertState(string $name, string $enabled_or_disabled): void {
    $field = $this->fieldAssertExists($name);

    if ($enabled_or_disabled === 'disabled' && !$field->hasAttribute('disabled')) {
      throw new \Exception(sprintf('A field "%s" should be disabled, but it is not.', $name));
    }
    elseif ($enabled_or_disabled !== 'disabled' && $field->hasAttribute('disabled')) {
      throw new \Exception(sprintf('A field "%s" should not be disabled, but it is.', $name));
    }
  }

  /**
   * Fill value for color field.
   *
   * @code
   * When I fill in the color field "#edit-text-color" with the value "#3366FF"
   * @endcode
   *
   * @When I fill in the color field :field with the value :value
   */
  public function fieldFillColor(string $field, ?string $value = NULL): mixed {
    $js = <<<JS
        (function() {
            var element = document.querySelector('{$field}');
            if (!element) {
                throw new Error('Element not found: {$field}');
            }
            element.value = '{$value}';
            var event = new Event('change', { bubbles: true });
            element.dispatchEvent(event);
        })();
JS;
    return $this->getSession()->evaluateScript($js);
  }

  /**
   * Assert that a color field has a value.
   *
   * @code
   * Then the color field "#edit-background-color" should have the value "#FF5733"
   * @endcode
   *
   * @Then the color field :field should have the value :value
   */
  public function fieldAssertColorFieldHasValue(string $field, string $value): void {
    $js = <<<JS
        (function() {
            var element = document.querySelector('{$field}');
            if (!element) {
                throw new Error('Element not found: {$field}');
            }
            return element.value;
        })();
JS;
    $actual = $this->getSession()->evaluateScript($js);

    if ($actual != $value) {
      throw new \Exception(sprintf('Color field "%s" expected a value "%s" but has a value "%s".', $field, $value, $actual));
    }
  }

  /**
   * Set value for WYSIWYG field.
   *
   * If used with Selenium driver, it will try to find associated WYSIWYG and
   * fill it in. If used with webdriver - it will fill in the field as normal.
   *
   * @code
   * When I fill in the WYSIWYG field "edit-body-0-value" with the "<p>This is a <strong>formatted</strong> paragraph.</p>"
   * @endcode
   *
   * @When I fill in the WYSIWYG field :field with the :value
   */
  public function fieldFillWysiwyg(string $field, string $value): void {
    $field = $this->fieldFixStepArgument($field);
    $value = $this->fieldFixStepArgument($value);

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
   * Return fixed step argument (with \\" replaced back to ").
   */
  protected function fieldFixStepArgument(string $argument): string {
    return str_replace('\\"', '"', $argument);
  }

}
