@behatcli
Feature: Behat CLI context Javascript steps

  Tests that JS sessions can be correctly started and ended when running
  multiple Behat runs through CLI.

  @javascript
  Scenario: Test @javascript session can be started for the scenario
    Given I visit "/sites/default/files/clean1.html"

  @trait:PathTrait
  Scenario: Test @javascript session can be started for an assertion
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      Given I visit "/sites/default/files/clean1.html"
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:PathTrait
  Scenario: Test @javascript session can be started for assertion in the second run
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      Given I visit "/sites/default/files/clean1.html"
      """
    When I run "behat --no-colors"
    Then it should pass

  @javascript
  Scenario: Test @javascript session can be started for the scenario in the third run
    Given I visit "/sites/default/files/clean1.html"
