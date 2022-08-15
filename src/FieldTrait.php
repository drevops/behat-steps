<?php

namespace DrevOps\BehatSteps;

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
  public function fieldAssertExists($field_name) {
    $page = $this->getSession()->getPage();
    $field = $page->findField($field_name);
    // Try to resolve by ID.
    $field = $field ? $field : $page->findById($field_name);

    if ($field === NULL) {
      throw new ElementNotFoundException($this->getSession()
        ->getDriver(), 'form field', 'id|name|label|value', $field_name);
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
  public function fieldAssertNotExists($field_name) {
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
  public function fieldAssertExistence($field_name, $exists) {
    if ($exists == 'exists') {
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
  public function fieldAssertState($field_name, $disabled) {
    $field = $this->fieldAssertExists($field_name);

    if ($disabled == 'disabled' && !$field->hasAttribute('disabled')) {
      throw new \Exception(sprintf('A field "%s" should be disabled, but it is not.', $field_name));
    }
    elseif ($disabled != 'disabled' && $field->hasAttribute('disabled')) {
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
  public function fieldAssertExistsState($field_name, $presence, $state = 'enabled') {
    if ($presence == 'present') {
      $this->fieldAssertExists($field_name);
      $this->fieldAssertState($field_name, $state);
    }
    else {
      $this->fieldAssertNotExists($field_name);
    }
  }

}
