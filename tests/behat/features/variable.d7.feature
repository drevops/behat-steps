@d7
Feature: Check that VariableTrait for D7 works

  @api
  Scenario: assert that we add not existing "foo" variable and set it
    Given I delete variable "foo"
    And variable "foo" does not have value "bar"
    Then I set variable "foo" to value "bar"

  @api
  Scenario: assert that the "foo" variable not persist after previous test scenario with set variable
    Given variable "foo" does not have value "bar"

  @api
  Scenario: assert that we store the "bar" in the "foo"
    Given I set variable "foo" to value "bar"
    Then I store original variable "foo"

  @api
  Scenario: assert that we get original "foo" value after restoring
    Given variable "foo" has value "bar"
    Then I set variable "foo" to value "baz"
    Then I restore original variables
    And variable "foo" does not have value "baz"
    And variable "foo" has value "bar"

  @api
  Scenario: assert that we deleted all test values at the end
    Given variable "foo" has value "bar"
    Then I delete variable "foo"
    Given variable "foo" does not have value "bar"
