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
   * Then I see field "Body"
   * Then I see field "field_body"
   * @endcode
   *
   * @Then I see field :name
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
   * Then I don't see field "Body"
   * Then I don't see field "field_body"
   * @endcode
   *
   * @Then I don't see field :name
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
   * Assert whether the field exists on the page using id,name,label or value.
   *
   * @code
   * Then field "Body" "exists" on the page
   * Then field "field_body" "exists" on the page
   * Then field "Tags" "does not exist" on the page
   * Then field "field_tags" "does not exist" on the page
   * @endcode
   *
   * @Then field :name :exists on the page
   */
  public function fieldAssertExistence(string $field_name, string $exists): void {
    if ($exists === 'exists') {
      $this->fieldAssertExists($field_name);
    }
    else {
      $this->fieldAssertNotExists($field_name);
    }
  }

  /**
   * Assert whether the field has a state.
   *
   * @code
   * Then field "Body" is "disabled" on the page
   * Then field "field_body" is "disabled" on the page
   * Then field "Tags" is "enabled" on the page
   * Then field "field_tags" is "not enabled" on the page
   * @endcode
   *
   * @Then field :name is :disabled on the page
   */
  public function fieldAssertState(string $field_name, string $disabled): void {
    $field = $this->fieldAssertExists($field_name);

    if ($disabled === 'disabled' && !$field->hasAttribute('disabled')) {
      throw new \Exception(sprintf('A field "%s" should be disabled, but it is not.', $field_name));
    }
    elseif ($disabled !== 'disabled' && $field->hasAttribute('disabled')) {
      throw new \Exception(sprintf('A field "%s" should not be disabled, but it is.', $field_name));
    }
  }

  /**
   * Assert whether the field exists on the page and has a state.
   *
   * @code
   * Then field "Body" should be "present" on the page and have state "enabled"
   * Then field "Tags" should be "absent" on the page and have state "n/a"
   * @endcode
   *
   * @Then field :name should be :presence on the page and have state :state
   */
  public function fieldAssertExistsState(string $field_name, string $presence, string $state = 'enabled'): void {
    if ($presence === 'present') {
      $this->fieldAssertExists($field_name);
      $this->fieldAssertState($field_name, $state);
    }
    else {
      $this->fieldAssertNotExists($field_name);
    }
  }

  /**
   * Fills value for color field.
   *
   * @When /^(?:|I )fill color in "(?P<field>(?:[^"]|\\")*)" with "(?P<value>(?:[^"]|\\")*)"$/
   * @When /^(?:|I )fill color in "(?P<value>(?:[^"]|\\")*)" for "(?P<field>(?:[^"]|\\")*)"$/
   */
  public function fillColorField(string $field, string $value = NULL): mixed {
    $js = <<<JS
        (function() {
            var element = document.querySelector('$field');
            if (!element) {
                throw new Error('Element not found: $field');
            }
            element.value = '$value';
            var event = new Event('change', { bubbles: true });
            element.dispatchEvent(event);
        })();
JS;
    return $this->getSession()->evaluateScript($js);
  }

  /**
   * Asserts that a color field has a value.
   *
   * @Then /^color field "(?P<field>(?:[^"]|\\")*)" value is "(?P<value>(?:[^"]|\\")*)"$/
   */
  public function assertColorFieldHasValue(string $field, string $value): void {
    $js = <<<JS
        (function() {
            var element = document.querySelector('$field');
            if (!element) {
                throw new Error('Element not found: $field');
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
