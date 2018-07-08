<?php

namespace IntegratedExperts\BehatSteps;

use Behat\Mink\Exception\ElementNotFoundException;

/**
 * Trait Field.
 *
 * @package IntegratedExperts\BehatSteps
 */
trait FieldTrait {

  /**
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
   * @Then I don't see field :name
   */
  public function fieldAssertNotExists($field_name) {
    $page = $this->getSession()->getPage();
    $field = $page->findField($field_name);
    // Try to resolve by ID.
    $field = $field ? $field : $page->findById($field_name);

    if ($field !== NULL) {
      throw new \Exception(sprintf('A field "%s" appears on this page, but it should not.', $field_name), $this->getSession()
        ->getDriver());
    }
  }

  /**
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
   * @Then field :name is :disabled on the page
   */
  public function fieldAssertState($field_name, $disabled) {
    $field = $this->fieldAssertExists($field_name);

    if ($disabled == 'disabled' && !$field->hasAttribute('disabled')) {
      throw new \Exception(sprintf('A field "%s" should be disabled, but it is not.', $field_name), $this->getSession()
        ->getDriver());
    }
    elseif ($disabled != 'disabled' && $field->hasAttribute('disabled')) {
      throw new \Exception(sprintf('A field "%s" should not be disabled, but it is.', $field_name), $this->getSession()
        ->getDriver());
    }
  }

  /**
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
