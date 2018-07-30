@d7 @test23
Feature: Check that VariableTrait for D7 works

  @api
  Scenario: Assert that a caching variable work
    Given I set variable "test" to value "boo"
    Then variable "test" has value "boo"
    Then I delete variable "test"
    Then variable "test" does not have value "boo"
    Then I store original variable "test"
    Then I restore original variables
