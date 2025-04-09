<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;

/**
 * Trait Field.
 *
 * Field-related steps.
 *
 * @package DrevOps\BehatSteps
 */
trait FieldTrait {

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
  public function fieldAssertExists(string $field_name): NodeElement {
    $page = $this->getSession()->getPage();
    $field = $page->findField($field_name);
    // Try to resolve by ID.
    $field = $field ? $field : $page->findById($field_name);

    if ($field === NULL) {
      $exception = new ElementNotFoundException($this->getSession()
        ->getDriver(), 'form field', 'id|name|label|value', $field_name);

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
  public function fieldAssertNotExists(string $field_name): void {
    $page = $this->getSession()->getPage();
    $field = $page->findField($field_name);
    // Try to resolve by ID.
    $field = $field ? $field : $page->findById($field_name);

    if ($field !== NULL) {
      throw new \Exception(sprintf('A field "%s" appears on this page, but it should not.', $field_name));
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
   * @Then the field :name should be :state
   */
  public function fieldAssertState(string $field_name, string $state): void {
    $field = $this->fieldAssertExists($field_name);

    if ($state === 'disabled' && !$field->hasAttribute('disabled')) {
      throw new \Exception(sprintf('A field "%s" should be disabled, but it is not.', $field_name));
    }
    elseif ($state !== 'disabled' && $field->hasAttribute('disabled')) {
      throw new \Exception(sprintf('A field "%s" should not be disabled, but it is.', $field_name));
    }
  }

  /**
   * Fills value for color field.
   *
   * @When /^(?:|I )fill color in "(?P<field>(?:[^"]|\\")*)" with "(?P<value>(?:[^"]|\\")*)"$/
   * @When /^(?:|I )fill in the color field "(?P<field>(?:[^"]|\\")*)" with the value "(?P<value>(?:[^"]|\\")*)"$/
   */
  public function fillColorField(string $field, ?string $value = NULL): mixed {
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
   * Asserts that a color field has a value.
   *
   * @Then /^the color field "(?P<field>(?:[^"]|\\")*)" should have the value "(?P<value>(?:[^"]|\\")*)"$/
   */
  public function assertColorFieldHasValue(string $field, string $value): void {
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

}
