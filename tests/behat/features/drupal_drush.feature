Feature: Check that DrushTrait works
  As Behat Steps library developer
  I want to provide tools to run Drush commands and assert their output
  So that users can cover behavior that only the CLI exposes

  Scenario: Assert "When I run the drush command :command" works as expected
    Given the user is anonymous
    When I run the drush command "status"
    Then the drush output should contain the value "Drupal version"
    And the drush output should not contain the value "no such command"
    And the drush output should match the pattern "/Drupal version/"

  Scenario: Assert "When I run the drush command :command with the arguments :arguments" works as expected
    Given the user is anonymous
    When I run the drush command "config:get" with the arguments "system.site"
    Then the drush output should contain the value "Drush Site-Install"

  Scenario: Assert "When I run the failing drush command :command" works as expected
    Given the user is anonymous
    When I run the failing drush command "pm:uninstall no_such_module"
    Then the drush output should contain the value "no_such_module"

  Scenario: Assert "When I run the failing drush command :command with the arguments :arguments" works as expected
    Given the user is anonymous
    When I run the failing drush command "pm:uninstall" with the arguments "no_such_module"
    Then the drush output should contain the value "no_such_module"
    And the drush output should not contain the value "is not defined"

  Scenario: Assert "When I print the last drush output" works as expected
    Given the user is anonymous
    When I run the drush command "status" with the arguments "--field=drupal-version"
    And I print the last drush output
    Then the drush output should match the pattern "/^\d+\.\d+/"

  @test-trait:Drupal\DrushTrait
  Scenario: Assert that reading output before running a command fails
    Given some behat configuration
    And scenario steps:
      """
      Then the drush output should contain the value "anything"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      No drush command has run in this scenario, so there is no output to read.
      """

  @test-trait:Drupal\DrushTrait
  Scenario: Assert that printing output before running a command fails
    Given some behat configuration
    And scenario steps:
      """
      When I print the last drush output
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      No drush command has run in this scenario, so there is no output to read.
      """
