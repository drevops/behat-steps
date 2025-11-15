<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Mink\Element\NodeElement;
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
   * Assert that field is empty.
   *
   * @code
   * Then the field "Name" should be empty
   * @endcode
   *
   * @Then the field :field should be empty
   */
  public function fieldAssertEmpty(string $field): void {
    $field_element = $this->fieldAssertExists($field);

    $value = $field_element->getValue();

    if ($value !== NULL && $value !== '') {
      throw new \Exception(sprintf('The field "%s" is not empty, but should be.', $field));
    }
  }

  /**
   * Assert that a field is not empty.
   *
   * @code
   * Then the field "Name" should not be empty
   * @endcode
   *
   * @Then the field :field should not be empty
   */
  public function fieldAssertNotEmpty(string $field): void {
    $field_element = $this->fieldAssertExists($field);

    $value = $field_element->getValue();

    if ($value === NULL || $value === '') {
      throw new \Exception(sprintf('The field "%s" is empty, but should not be.', $field));
    }
  }

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
    $field = $field ?: $page->findById($name);

    if ($field === NULL) {
      $exception = new ElementNotFoundException($this->getSession()->getDriver(), 'form field', 'id|name|label|value', $name);
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
    $field = $field ?: $page->findById($name);

    if ($field !== NULL) {
      throw new \Exception(sprintf('A field "%s" appears on this page, but it should not.', $name));
    }
  }

  /**
   * Assert whether the field has a state.
   *
   * @code
   * Then the field "Body" should have "disabled" state
   * Then the field "field_body" should have "disabled" state
   * Then the field "Tags" should have "enabled" state
   * Then the field "field_tags" should have "not enabled" state
   * @endcode
   *
   * @Then the field :name should have :enabled_or_disabled state
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
    $field_js = json_encode($field, JSON_UNESCAPED_SLASHES);
    $value_js = json_encode($value, JSON_UNESCAPED_SLASHES);
    $script = <<<JS
      (function() {
        var element = document.querySelector({$field_js});
        if (!element) {
          throw new Error('Element not found: ' + {$field_js});
        }
        element.value = {$value_js};
        var event = new Event('change', { bubbles: true });
        element.dispatchEvent(event);
      })();
JS;
    return $this->getSession()->evaluateScript($script);
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
    $field_js = json_encode($field, JSON_UNESCAPED_SLASHES);
    $script = <<<JS
      (function() {
        var element = document.querySelector({$field_js});
        if (!element) {
          throw new Error('Element not found: ' + {$field_js});
        }
        return element.value;
      })();
JS;
    $actual = $this->getSession()->evaluateScript($script);

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
      $exception = new ElementNotFoundException($this->getSession()->getDriver(), 'form field', 'id|name|label|value|placeholder', $field);
      throw new \Exception($exception->getMessage());
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
      throw new \Exception('WYSIWYG field must have an ID attribute.');
    }

    $element_id_js = json_encode($element_id, JSON_UNESCAPED_SLASHES);
    $value_js = json_encode($value, JSON_UNESCAPED_SLASHES);

    $parent_element = $element->getParent();

    // Support CKEditor 4.
    $is_ckeditor_4 = !empty($driver->find($parent_element->getXpath() . "/div[contains(@class,'cke')]"));
    if ($is_ckeditor_4) {
      $script = <<<JS
        CKEDITOR.instances[{$element_id_js}].setData({$value_js});
JS;
    }
    // Support CKEditor 5.
    else {
      $script = <<<JS
        (function() {
          const element = document.querySelector('#' + {$element_id_js})?.nextElementSibling.querySelector('.ck-editor__editable');
          if (!element) {
            throw new Error('CKEditor editable area not found for element with ID ' + {$element_id_js});
          }
          if (!element.ckeditorInstance) {
            throw new Error('CKEditor instance not found for element with ID ' + {$element_id_js});
          }
          element.ckeditorInstance.setData({$value_js});
        })();
JS;
    }
    $this->getSession()->executeScript($script);
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
    $select_element = $this->getSession()->getPage()->findField($selector);
    if (is_null($select_element)) {
      throw new \InvalidArgumentException(sprintf('Element "%s" is not found.', $selector));
    }

    $option_element = $select_element->find('named', ['option', $option]);
    if (is_null($option_element)) {
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
    $select_element = $this->getSession()->getPage()->findField($selector);
    if (is_null($select_element)) {
      throw new \InvalidArgumentException(sprintf('Element "%s" is not found.', $selector));
    }

    $option_element = $select_element->find('named', ['option', $option]);
    if (!is_null($option_element)) {
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
    $select_field = $this->getSession()->getPage()->findField($selector);
    $current_url = $this->getSession()->getCurrentUrl();
    $path = parse_url((string) $current_url, PHP_URL_PATH);

    if (!$select_field) {
      throw new \Exception(sprintf('The select "%s" was not found on the page %s.', $selector, $path));
    }

    $option_field = $select_field->find('named', [
      'option',
      $value,
    ]);

    if (!$option_field) {
      throw new \Exception(sprintf('No option is selected in the %s select on the page %s.', $selector, $path));
    }

    if (!$option_field->isSelected()) {
      throw new \Exception(sprintf('The option "%s" was not selected on the page %s.', $value, $path));
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
    $select_field = $this->getSession()->getPage()->findField($selector);
    $current_url = $this->getSession()->getCurrentUrl();
    $path = parse_url((string) $current_url, PHP_URL_PATH);

    if (!$select_field) {
      throw new \Exception(sprintf('The select "%s" was not found on the page %s.', $selector, $path));
    }

    $option_field = $select_field->find('named', ['option', $value]);

    if (!$option_field) {
      throw new \Exception(sprintf('The option "%s" was not found in the select "%s" on the page %s.', $value, $selector, $path));
    }

    if ($option_field->isSelected()) {
      throw new \Exception(sprintf('The option "%s" was selected in the select "%s" on the page %s, but should not be.', $value, $selector, $path));
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
    $selector_js = json_encode($selector, JSON_UNESCAPED_SLASHES);

    $script = <<<JS
      (function() {
        var form = document.querySelector({$selector_js});
        if (!form) {
          throw new Error('Form with selector ' + {$selector_js} + ' not found');
        }
        form.setAttribute('novalidate', 'novalidate');
      })();
JS;
    $this->getSession()->executeScript($script);
  }

  /**
   * Fill in datetime field with date and optionally time.
   *
   * Leave time empty if not needed.
   *
   * @code
   * When I fill in the datetime field "Event date" with date "2024-01-15" and time "14:30:00"
   * When I fill in the datetime field "Event date" with date "2024-01-15" and time ""
   * @endcode
   *
   * @When I fill in the datetime field :label with date :date and time :time
   */
  public function fieldFillDatetime(string $label, string $date, string $time): void {
    $this->fieldFillDatetimeHelper($label, 'value', 'date', $date);
    if ($time !== '') {
      $this->fieldFillDatetimeHelper($label, 'value', 'time', $time);
    }
  }

  /**
   * Fill in the date part of a datetime field.
   *
   * @code
   * When I fill in the date part of the datetime field "Event date" with "2024-01-15"
   * @endcode
   *
   * @When I fill in the date part of the datetime field :label with :date
   */
  public function fieldFillDatetimeDate(string $label, string $date): void {
    $this->fieldFillDatetimeHelper($label, 'value', 'date', $date);
  }

  /**
   * Fill in the time part of a datetime field.
   *
   * @code
   * When I fill in the time part of the datetime field "Event date" with "14:30:00"
   * @endcode
   *
   * @When I fill in the time part of the datetime field :label with :time
   */
  public function fieldFillDatetimeTime(string $label, string $time): void {
    $this->fieldFillDatetimeHelper($label, 'value', 'time', $time);
  }

  /**
   * Fill in start datetime field with date and optionally time.
   *
   * For date range fields. Leave time empty if not needed.
   *
   * @code
   * When I fill in the start datetime field "Event period" with date "2024-01-15" and time "14:30:00"
   * When I fill in the start datetime field "Event period" with date "2024-01-15" and time ""
   * @endcode
   *
   * @When I fill in the start datetime field :label with date :date and time :time
   */
  public function fieldFillDatetimeStart(string $label, string $date, string $time): void {
    $this->fieldFillDatetimeHelper($label, 'value', 'date', $date);
    if ($time !== '') {
      $this->fieldFillDatetimeHelper($label, 'value', 'time', $time);
    }
  }

  /**
   * Fill in end datetime field with date and optionally time.
   *
   * For date range fields. Leave time empty if not needed.
   *
   * @code
   * When I fill in the end datetime field "Event period" with date "2024-01-20" and time "18:00:00"
   * When I fill in the end datetime field "Event period" with date "2024-01-20" and time ""
   * @endcode
   *
   * @When I fill in the end datetime field :label with date :date and time :time
   */
  public function fieldFillDatetimeEnd(string $label, string $date, string $time): void {
    $this->fieldFillDatetimeHelper($label, 'end_value', 'date', $date);
    if ($time !== '') {
      $this->fieldFillDatetimeHelper($label, 'end_value', 'time', $time);
    }
  }

  /**
   * Helper method to fill datetime field parts.
   *
   * @param string $label
   *   The field label text.
   * @param string $part
   *   The field part: 'value' for single/start or 'end_value' for end.
   * @param string $field
   *   The field type: 'date' or 'time'.
   * @param string $value
   *   The value to set.
   *
   * @throws \Exception
   */
  protected function fieldFillDatetimeHelper(string $label, string $part, string $field, string $value): void {
    // Try to find by label element first.
    $xpath = sprintf(
      '//label[contains(text(), "%s")]/..//input[contains(@name, "[%s][%s]")]',
      $label,
      $part,
      $field
    );

    $page = $this->getSession()->getPage();
    $element = $page->find('xpath', $xpath);

    // If not found, try to find by span (Drupal field label pattern).
    if ($element === NULL) {
      $xpath = sprintf(
        '//span[contains(text(), "%s")]/../..//input[contains(@name, "[%s][%s]")]',
        $label,
        $part,
        $field
      );
      $element = $page->find('xpath', $xpath);
    }

    // If still not found, try a more generic approach.
    if ($element === NULL) {
      $xpath = sprintf(
        '//*[contains(text(), "%s")]/ancestor::div[contains(@class, "field--")]//input[contains(@name, "[%s][%s]")]',
        $label,
        $part,
        $field
      );
      $element = $page->find('xpath', $xpath);
    }

    if ($element === NULL) {
      throw new \Exception(sprintf('Datetime field "%s" with part "%s" and field "%s" not found.', $label, $part, $field));
    }

    $element->setValue($value);
  }

}
