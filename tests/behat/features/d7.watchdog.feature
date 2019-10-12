@d7 @errorcleanup
Feature: Check that WatchdogTrait works for D7

  @api
  Scenario: Assert that watchdog does not track errors with level below threshold
    Given set watchdog error level 5

  @api @error
  Scenario: Assert that watchdog track errors with level above threshold
   Given set watchdog error level 4
