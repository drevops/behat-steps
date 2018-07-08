<?php

namespace IntegratedExperts\BehatSteps\D7;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

/**
 * Class VariableTrait.
 */
trait VariableTrait {

  /**
   * @Given I set variable :name to value :value
   * @Given I set variable :name to value:
   */
  public function variableSet($name, $value) {
    $this->variableStoreOriginal($name);
    variable_set($name, (string) $value);
  }

  /**
   * @Given I delete variable :name
   */
  public function variableDelete($name) {
    $this->variableStoreOriginal($name);
    variable_del($name);
  }

  /**
   * @Then variable :name has value :value
   * @Then variable :name has value:
   */
  public function variableAssertValue($name, $value) {
    $this->variableRefresh();
    $variable_value = variable_get($name);
    if ($value != $variable_value) {
      throw new \Exception(sprintf('Variable %s has value "%s", but should have value "%s".', $name, $variable_value, $value));
    }
  }

  /**
   * @Then variable :name does not have value :value
   */
  public function variableAssertNoValue($name, $value) {
    $this->variableRefresh();
    $variable_value = variable_get($name);
    if ($value == $variable_value) {
      throw new \Exception(sprintf('Variable %s has value "%s", but should not have it.', $name, $variable_value));
    }
  }

  /**
   * @Then variable :name does not have a value
   */
  public function variableAssertNullValue($name) {
    $this->variableRefresh();
    $variable_value = variable_get($name);
    if (!is_null($variable_value)) {
      throw new \Exception(sprintf('Variable %s has value "%s", but should not have any value set.', $name, $variable_value));
    }
  }

  /**
   * Allows to store variable values until test is complete.
   *
   * It also supports automatic variable restore.
   *
   * @Given I store original variable :name
   */
  public function variableStoreOriginal($variable_name) {
    $value = variable_get($variable_name);
    if ($value) {
      variable_set('test_behat_' . $variable_name, $value);
    }
    else {
      variable_set('test_behat_' . $variable_name, '__NON_EXISTING__');
    }
  }

  /**
   * @Given I restore original variables
   */
  public function variableRestoreOriginal() {
    $stored = $this->variableGetTestNames();
    foreach ($stored as $stored_name) {
      $original_name = substr($stored_name, strlen('test_behat_'));
      $stored_value = variable_get($stored_name);
      if ($stored_value != '__NON_EXISTING__') {
        variable_set($original_name, $stored_value);
      }
      else {
        variable_del($original_name);
      }
    }

    $this->variableCleanupTestVariables();
  }

  /**
   * Cleanup temporary behat variables.
   *
   * @beforeScenario
   */
  public function variableBeforeScenarioRemoveTestVariables(BeforeScenarioScope $scope) {
    $this->variableCleanupTestVariables();
  }

  /**
   * Restore original variables, if any.
   *
   * @afterScenario
   */
  public function variableBeforeScenarioRestoreOriginalVariables(AfterScenarioScope $scope) {
    $this->variableRestoreOriginal();
  }

  /**
   * Get all test variable names.
   */
  protected function variableGetTestNames() {
    $variables = db_select('variable')
      ->fields('variable', ['name'])
      ->condition('name', db_like('test_behat_') . '%', 'LIKE')
      ->execute()
      ->fetchCol();

    return $variables;
  }

  /**
   * Cleanup all test variables.
   */
  protected function variableCleanupTestVariables() {
    $variables = $this->variableGetTestNames();

    foreach ($variables as $variable) {
      variable_del($variable);
    }
  }

  /**
   * Refresh the in-memory set of variables.
   */
  protected function variableRefresh() {
    global $conf;
    cache_clear_all('variables', 'cache_bootstrap');
    $conf = variable_initialize();
  }

}
