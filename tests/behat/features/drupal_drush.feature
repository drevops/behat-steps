Feature: Check that DrushTrait works
  As Behat Steps library developer
  I want to provide tools to run Drush commands and assert their output
  So that users can cover behavior that only the CLI exposes

  Scenario: Assert "When I run the drush command :command" works as expected
    Given I am an anonymous user
    When I run the drush command "status"
    Then the drush output should contain "Drupal version"
    And the drush output should not contain "no such command"
    And the drush output should match "/Drupal version/"

  Scenario: Assert "When I run the drush command :command with the arguments :arguments" works as expected
    Given I am an anonymous user
    When I run the drush command "config:get" with the arguments "system.site"
    Then the drush output should contain "Drush Site-Install"

  Scenario: Assert "When I run the drush command :command" passes the arguments the command carries
    Given I am an anonymous user
    When I run the drush command "config:get system.site name"
    Then the drush output should contain "Drush Site-Install"
    And the drush output should not contain "does not exist"

  Scenario: Assert "When I run the drush command :command with the arguments :arguments" keeps a quoted part in 1 argument
    Given I am an anonymous user
    When I run the drush command "php:eval" with the arguments "'echo 6 * 7;'"
    Then the drush output should contain "42"

  Scenario: Assert "When I run the drush command :command with the arguments :arguments" keeps stderr after stdout
    Given I am an anonymous user
    When I run the drush command "php:eval" with the arguments "'echo 6 * 7; fwrite(STDERR, (string) (6 * 9));'"
    Then the drush output should contain "54"
    And the drush output should match "/42\s+54/"

  Scenario: Assert "When I run the failing drush command :command" works as expected
    Given I am an anonymous user
    When I run the failing drush command "pm:uninstall no_such_module"
    Then the drush output should contain "no_such_module"

  Scenario: Assert "When I run the failing drush command :command with the arguments :arguments" works as expected
    Given I am an anonymous user
    When I run the failing drush command "pm:uninstall" with the arguments "no_such_module"
    Then the drush output should contain "no_such_module"
    And the drush output should not contain "is not defined"

  Scenario: Assert "When I run the failing drush command :command with the arguments :arguments" keeps stderr after stdout
    Given I am an anonymous user
    When I run the failing drush command "php:eval" with the arguments "'echo 6 * 7; fwrite(STDERR, (string) (6 * 9)); throw new Exception();'"
    Then the drush output should match "/42\s+54/"

  Scenario: Assert "When I print the last drush output" works as expected
    Given I am an anonymous user
    When I run the drush command "status" with the arguments "--field=drupal-version"
    And I print the last drush output
    Then the drush output should match "/^\d+\.\d+/"

  Scenario: Assert "When I run the drush command :command" leaves no stale entity behind
    Given the following users exist:
      | name           | roles         |
      | drush_assigned | administrator |
    And the user "drush_assigned" should not have the role "content_editor" assigned
    When I run the drush command "user:role:add content_editor drush_assigned"
    Then the user "drush_assigned" should have the role "content_editor" assigned

  @test-trait:Drupal\DrushTrait
  Scenario: Assert that reading output before running a command fails
    Given some behat configuration
    And scenario steps:
      """
      Then the drush output should contain "anything"
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

  @test-trait:Drupal\DrushTrait
  Scenario: Assert negative "Then the drush output should contain :value" fails when the output lacks the text
    Given a configuration listing the backends "drupal, drush, blackbox"
    And some behat configuration
    And scenario steps:
      """
      When I run the drush command "config:get" with the arguments "system.site name"
      Then the drush output should contain "No such site name"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The last drush command output does not contain "No such site name". It was:
      """

  @test-trait:Drupal\DrushTrait
  Scenario: Assert negative "Then the drush output should not contain :value" fails when the output has the text
    Given a configuration listing the backends "drupal, drush, blackbox"
    And some behat configuration
    And scenario steps:
      """
      When I run the drush command "config:get" with the arguments "system.site name"
      Then the drush output should not contain "Drush Site-Install"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The last drush command output contains "Drush Site-Install". It was:
      """

  @test-trait:Drupal\DrushTrait
  Scenario: Assert negative "Then the drush output should match :pattern" fails when the output does not match
    Given a configuration listing the backends "drupal, drush, blackbox"
    And some behat configuration
    And scenario steps:
      """
      When I run the drush command "config:get" with the arguments "system.site name"
      Then the drush output should match "/^No such site name$/"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The last drush command output does not match "/^No such site name$/". It was:
      """

  @test-trait:Drupal\DrushTrait
  Scenario: Assert negative "Then the drush output should match :pattern" fails for a pattern that is not a regular expression
    Given a configuration listing the backends "drupal, drush, blackbox"
    And some behat configuration
    And scenario steps:
      """
      When I run the drush command "config:get" with the arguments "system.site name"
      Then the drush output should match "Drush Site-Install"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      "Drush Site-Install" is not a valid regular expression: Internal error.
      """

  @test-trait:Drupal\DrushTrait
  Scenario: Assert negative "When I run the drush command :command" fails when the command fails
    Given a configuration listing the backends "drupal, drush, blackbox"
    And some behat configuration
    And scenario steps:
      """
      When I run the drush command "pm:uninstall no_such_module"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The drush command "pm:uninstall no_such_module" exited with code 1. Output:
      """

  @test-trait:Drupal\DrushTrait
  Scenario: Assert negative "When I run the failing drush command :command" fails when the command succeeds
    Given a configuration listing the backends "drupal, drush, blackbox"
    And some behat configuration
    And scenario steps:
      """
      When I run the failing drush command "config:get" with the arguments "system.site name"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The drush command "config:get" was expected to fail, but it exited 0. Output:
      """
