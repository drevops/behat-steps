@errorcleanup
Feature: Check that WatchdogTrait works
  As Behat Steps library developer
  I want to provide tools to monitor Drupal watchdog messages
  So that users can detect unexpected errors in their tests

  @trait:Drupal\WatchdogTrait
  Scenario: Assert that watchdog fails with an error
    Given some behat configuration
    And scenario steps:
      """
      When set watchdog error level "warning"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      PHP errors were logged to watchdog during scenario "Stub scenario title" (line 3):
      """

  @trait:Drupal\WatchdogTrait
  Scenario: Assert that watchdog does not fail when a custom message type is triggered
    Given some behat configuration
    And scenario steps:
      """
      When set watchdog error level "warning" of type "custom_type"
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:Drupal\WatchdogTrait
  Scenario: Assert that watchdog fails when a custom message type is triggered
    Given some behat configuration
    And scenario steps tagged with "@api @watchdog:custom_type":
      """
      When set watchdog error level "warning" of type "custom_type"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      PHP errors were logged to watchdog during scenario "Stub scenario title" (line 3):
      """

  @api
  Scenario: Assert that watchdog does not track errors with level below threshold
    When set watchdog error level "notice"

  @api @error
  Scenario: Assert that watchdog track errors with level above threshold
    When set watchdog error level "warning"
