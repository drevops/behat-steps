@d9 @errorcleanup
Feature: Check that WatchdogTrait works for or D9

  @trait:WatchdogTrait
  Scenario: Assert that watchdog fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given set watchdog error level "warning"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      PHP errors were logged to watchdog during scenario "Stub scenario title" (line 3):
      """

  @trait:WatchdogTrait
  Scenario: Assert that watchdog does not fail when a custom message type is triggered
    Given some behat configuration
    And scenario steps:
      """
      Given set watchdog error level "warning" of type "custom_type"
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:WatchdogTrait
  Scenario: Assert that watchdog fails when a custom message type is triggered
    Given some behat configuration
    And scenario steps tagged with "@api @watchdog:custom_type":
      """
      Given set watchdog error level "warning" of type "custom_type"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      PHP errors were logged to watchdog during scenario "Stub scenario title" (line 3):
      """

  @api
  Scenario: Assert that watchdog does not track errors with level below threshold
    Given set watchdog error level "notice"

  @api @error
  Scenario: Assert that watchdog track errors with level above threshold
    Given set watchdog error level "warning"
