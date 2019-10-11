@d7
Feature: Check that variable assertions work for D7

  @api
  Scenario: Setting and asserting variable value equals.
    Given I set variable "random_test_var1" to value "my value"
    Then variable "random_test_var1" has value "my value"
    And I delete test variable "random_test_var1"

  @api
  Scenario: Setting and asserting variable value not equals.
    Given I set variable "random_test_var2" to value "my value"
    Then variable "random_test_var2" does not have value "my value2"
    And I delete test variable "random_test_var2"

  @api
  Scenario: Asserting that variable value is not set for non-existing variable.
    Given variable "random_test_var3" does not have a value

  # Following 2 scenarios should be sequential.
  @api
  Scenario: Preserving original variables when value updated. Part 1.
    Given I set test variable "random_test_var4" to value "init"
    And variable "random_test_var4" has value "init"
    When I set variable "random_test_var4" to value "my value"
    Then variable "random_test_var4" has value "my value"

  @api
  Scenario: Preserving original variables when value updated. Part 2.
    # Assert that any overridden values in the previous test were restored.
    Given variable "random_test_var4" has value "init"
    And I delete test variable "random_test_var4"

  # Following 2 scenarios should be sequential.
  @api
  Scenario: Preserving original variables when value removed. Part 1.
    Given I set test variable "random_test_var5" to value "init"
    And variable "random_test_var5" has value "init"
    When I delete variable "random_test_var5"
    Then variable "random_test_var5" does not have a value

  @api
  Scenario: Preserving original variables when value removed. Part 2.
    # Assert that any overridden values in the previous test were restored.
    Given variable "random_test_var5" has value "init"
    And I delete test variable "random_test_var5"

  @api
  Scenario: Setting and asserting multiline variables.
    Given I set variable "random_test_var6" to value:
    """
    Line1
    Line2
    Line3
    """
    Then variable "random_test_var6" has value:
    """
    Line1
    Line2
    Line3
    """
    And I delete test variable "random_test_var6"
