@d7 @errorcleanup
Feature: Check that WatchdogTrait works for D7

  @trait:D7\WatchdogTrait
  Scenario: Assert that watchdog fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given set watchdog error level 4
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      PHP errors were logged to watchdog during this scenario:
      """

  @api
  Scenario: Assert that watchdog does not track errors with level below threshold
    Given set watchdog error level 5

  @api @error
  Scenario: Assert that watchdog track errors with level above threshold
   Given set watchdog error level 4
