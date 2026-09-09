Feature: Check that DrushTrait works
  As Behat Steps library developer
  I want to provide tools to run Drush commands and assert their output
  So that users can cover behaviour that only the CLI exposes

  @api
  Scenario: Assert "When I run the drush command :command" works as expected
    Given the user is anonymous
    When I run the drush command "status"
    Then the drush output should contain the value "Drupal version"
    And the drush output should not contain the value "no such command"
    And the drush output should match the pattern "/Drupal version/"

  @api
  Scenario: Assert "When I run the drush command :command with the arguments :arguments" works as expected
    Given the user is anonymous
    When I run the drush command "config:get" with the arguments "system.site"
    Then the drush output should contain the value "Drush Site-Install"

  @api
  Scenario: Assert "When I run the failing drush command :command" works as expected
    Given the user is anonymous
    When I run the failing drush command "pm:uninstall no_such_module"
    Then the drush output should contain the value "no_such_module"

  @trait:Drupal\DrushTrait
  Scenario: Assert that reading output before running a command fails
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      Then the drush output should contain the value "anything"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      No drush command has run in this scenario, so there is no output to read.
      """
