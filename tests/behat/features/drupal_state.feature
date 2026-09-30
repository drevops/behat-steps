@state
Feature: Check that StateTrait works
  As Behat Steps library developer
  I want to provide tools to manage the Drupal State API
  So that users can set, delete, and assert state values during tests with automatic revert

  Scenario: Assert "Given the state :name has the value :value" sets a scalar state value
    Given the state "behat_steps_test.flag" has the value "1"
    Then the state "behat_steps_test.flag" should have the value "1"

  Scenario: Assert "Given the state :name has the value :value" sets a string state value
    Given the state "behat_steps_test.label" has the value "hello world"
    Then the state "behat_steps_test.label" should have the value "hello world"

  Scenario: Assert "Given the state :name has the value :value" sets a boolean state value
    Given the state "behat_steps_test.enabled" has the value "true"
    Then the state "behat_steps_test.enabled" should have the value "true"

  Scenario: Assert "Given the state :name has the value :value" sets a JSON array state value
    Given the state "behat_steps_test.list" has the value "[1,2,3]"
    Then the state "behat_steps_test.list" should have the value "[1,2,3]"

  Scenario: Assert "Given the state :name has the value :value" sets a null state value
    Given the state "behat_steps_test.nullable" has the value "null"
    Then the state "behat_steps_test.nullable" should have the value "null"

  Scenario: Assert "Given the state :name does not exist" deletes a state value
    Given the state "behat_steps_test.flag" has the value "1"
    When the state "behat_steps_test.flag" does not exist
    Then the state "behat_steps_test.flag" should not exist

  Scenario: Assert "Given the following state values exist:" sets multiple state values
    Given the following state values exist:
      | name                         | value |
      | behat_steps_test.launched    | 1     |
      | behat_steps_test.feature     | 0     |
      | behat_steps_test.name        | alpha |
    Then the state "behat_steps_test.launched" should have the value "1"
    And the state "behat_steps_test.feature" should have the value "0"
    And the state "behat_steps_test.name" should have the value "alpha"

  Scenario: Assert "Then the state :name should not exist" passes for unset key
    Then the state "behat_steps_test.missing" should not exist

  # Setup scenario: store a known value so the next scenario can prove it got
  # reverted automatically. The AfterScenario hook should snapshot NULL here
  # and revert the key back to NULL after this scenario finishes.
  @behat-steps-skip:StateTrait
  Scenario: Seed a state value without auto-revert
    Given the state "behat_steps_test.persistent" has the value "seeded"
    Then the state "behat_steps_test.persistent" should have the value "seeded"

  Scenario: Assert state values are reverted after scenario
    Given the state "behat_steps_test.persistent" has the value "overridden"
    Then the state "behat_steps_test.persistent" should have the value "overridden"

  Scenario: Verify previous scenario state change was reverted to seeded value
    Then the state "behat_steps_test.persistent" should have the value "seeded"

  # The steps below resolve the State capability instead of bootstrapping
  # Drupal, so the '@driver:drush' tag runs the same step text against a site
  # this process never boots.
  @driver:drush
  Scenario: Set and assert state values of every type over Drush
    Given the following state values exist:
      | name                          | value       |
      | behat_steps_test.drush_string | hello world |
      | behat_steps_test.drush_int    | 42          |
      | behat_steps_test.drush_bool   | true        |
      | behat_steps_test.drush_array  | [1,2,3]     |
    Then the state "behat_steps_test.drush_string" should have the value "hello world"
    And the state "behat_steps_test.drush_int" should have the value "42"
    And the state "behat_steps_test.drush_bool" should have the value "true"
    And the state "behat_steps_test.drush_array" should have the value "[1,2,3]"

  @driver:drush
  Scenario: Delete a state value over Drush
    Given the state "behat_steps_test.drush_doomed" has the value "present"
    And the state "behat_steps_test.drush_doomed" does not exist
    Then the state "behat_steps_test.drush_doomed" should not exist

  @behat-steps-skip:StateTrait @driver:drush
  Scenario: Seed a state value over Drush without auto-revert
    Given the state "behat_steps_test.drush_persistent" has the value "seeded"
    Then the state "behat_steps_test.drush_persistent" should have the value "seeded"

  @driver:drush
  Scenario: State values set over Drush are reverted after scenario
    Given the state "behat_steps_test.drush_persistent" has the value "overridden"
    Then the state "behat_steps_test.drush_persistent" should have the value "overridden"

  @driver:drush
  Scenario: Verify the Drush revert restored the seeded state value
    Then the state "behat_steps_test.drush_persistent" should have the value "seeded"

  @trait:Drupal\StateTrait
  Scenario: Assert negative assertion for "Then the state :name should have the value :value" fails when key is missing
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      Then the state "behat_steps_test.missing" should have the value "1"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The state "behat_steps_test.missing" does not exist, but it should have the value "1".
      """

  @trait:Drupal\StateTrait
  Scenario: Assert negative assertion for "Then the state :name should have the value :value" fails on value mismatch
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the state "behat_steps_test.flag" has the value "1"
      Then the state "behat_steps_test.flag" should have the value "2"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The state "behat_steps_test.flag" has the value "1", but it should have the value "2".
      """

  @trait:Drupal\StateTrait
  Scenario: Assert negative assertion for "Then the state :name should not exist" fails for existing key
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      And the state "behat_steps_test.flag" has the value "1"
      Then the state "behat_steps_test.flag" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The state "behat_steps_test.flag" exists with the value "1", but it should not exist.
      """
