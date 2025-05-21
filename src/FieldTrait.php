<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementHtmlException;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\UnsupportedDriverActionException;

/**
 * Manipulate form fields and verify widget functionality.
 *
 * - Set field values for various input types including selects and WYSIWYG.
 * - Assert field existence, state, and selected options.
 * - Support for specialized widgets like color pickers and rich text editors.
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
   * Assert that a select has an option.
   *
   * @code
   * Then the option "Administrator" should exist within the select element "edit-roles"
   * @endcode
   *
   * @Then the option :option should exist within the select element :selector
   */
  public function fieldAssertSelectOptionExists(string $selector, string $option): void {
    $selectElement = $this->getSession()->getPage()->findField($selector);
    if (is_null($selectElement)) {
      throw new \InvalidArgumentException(sprintf('Element "%s" is not found.', $selector));
    }

    $optionElement = $selectElement->find('named', ['option', $option]);
    if (is_null($optionElement)) {
      throw new \InvalidArgumentException(sprintf('Option "%s" is not found in select "%s".', $option, $selector));
    }
  }

  /**
   * Assert that a select does not have an option.
   *
   * @code
   * Then the option "Guest" should not exist within the select element "edit-roles"
   * @endcode
   *
   * @Then the option :option should not exist within the select element :selector
   */
  public function fieldAssertSelectOptionNotExists(string $selector, string $option): void {
    $selectElement = $this->getSession()->getPage()->findField($selector);
    if (is_null($selectElement)) {
      throw new \InvalidArgumentException(sprintf('Element "%s" is not found.', $selector));
    }

    $optionElement = $selectElement->find('named', ['option', $option]);
    if (!is_null($optionElement)) {
      throw new \InvalidArgumentException(sprintf('Option "%s" is found in select "%s", but should not.', $option, $selector));
    }
  }

  /**
   * Assert that a select option is selected.
   *
   * @code
   * Then the option "Administrator" should be selected within the select element "edit-roles"
   * @endcode
   *
   * @Then the option :option should be selected within the select element :selector
   */
  public function fieldAssertSelectOptionSelected(string $value, string $selector): void {
    $selectField = $this->getSession()->getPage()->findField($selector);
    $currentUrl = $this->getSession()->getCurrentUrl();
    $path = parse_url((string) $currentUrl, PHP_URL_PATH);

    if (!$selectField) {
      throw new \Exception(sprintf('The select "%s" was not found on the page %s', $selector, $path));
    }

    $optionField = $selectField->find('named', [
      'option',
      $value,
    ]);

    if (!$optionField) {
      throw new \Exception(sprintf('No option is selected in the %s select on the page %s', $selector, $path));
    }

    if (!$optionField->isSelected()) {
      throw new \Exception(sprintf('The option "%s" was not selected on the page %s', $value, $path));
    }
  }

  /**
   * Assert that a select option is not selected.
   *
   * @code
   * Then the option "Editor" should not be selected within the select element "edit-roles"
   * @endcode
   *
   * @Then the option :option should not be selected within the select element :selector
   */
  public function fieldAssertSelectOptionNotSelected(string $value, string $selector): void {
    $selectField = $this->getSession()->getPage()->findField($selector);
    $currentUrl = $this->getSession()->getCurrentUrl();
    $path = parse_url((string) $currentUrl, PHP_URL_PATH);

    if (!$selectField) {
      throw new \Exception(sprintf('The select "%s" was not found on the page %s', $selector, $path));
    }

    $optionField = $selectField->find('named', ['option', $value]);

    if (!$optionField) {
      throw new \Exception(sprintf('The option "%s" was not found in the select "%s" on the page %s', $value, $selector, $path));
    }

    if ($optionField->isSelected()) {
      throw new \Exception(sprintf('The option "%s" was selected in the select "%s" on the page %s, but should not be', $value, $selector, $path));
    }
  }

  /**
   * Check the checkbox.
   *
   * @param string $selector
   *   The checkbox input id, name or label.
   *
   * @code
   *   When I check the checkbox "Checkbox label"
   *   When I check the checkbox "edit-field-terms-0-value"
   * @endcode
   *
   * @When I check the checkbox :selector
   */
  public function fieldCheckboxCheck(string $selector): void {
    $selector = $this->fieldFixStepArgument($selector);

    $this->getSession()->getPage()->checkField($selector);
  }

  /**
   * Uncheck the checkbox.
   *
   * @param string $selector
   *   The checkbox input id, name or label.
   *
   * @code
   *   When I uncheck the checkbox "Checkbox label"
   *   When I uncheck the checkbox "edit-field-terms-0-value"
   * @endcode
   *
   * @When I uncheck the checkbox :selector
   */
  public function fieldCheckboxUncheck(string $selector): void {
    $selector = $this->fieldFixStepArgument($selector);

    $this->getSession()->getPage()->uncheckField($selector);
  }

  /**
   * Return fixed step argument (with \" replaced back to ").
   */
  protected function fieldFixStepArgument(string $argument): string {
    return str_replace('\\"', '"', $argument);
  }

  /**
   * Disable browser validation for the form for validating errors.
   *
   * @Given browser validation for the form :selector is disabled
   */
  public function disableFormBrowserValidation(string $selector): void {
    $escapedSelector = addslashes($selector);

    $js = <<<JS
        var form = document.querySelector("{$escapedSelector}");
        if (form) {
            form.setAttribute('novalidate', 'novalidate');
        } else {
            throw new Error("Form with selector {$escapedSelector} not found");
        }
JS;
    $this->getSession()->executeScript($js);
  }

}
