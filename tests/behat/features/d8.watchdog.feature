@d8 @errorcleanup
Feature: Check that WatchdogTrait works for D8

  @trait:D8\WatchdogTrait
  Scenario: Assert that watchdog fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given set Drupal8 watchdog error level "warning"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      PHP errors were logged to watchdog during this scenario:
      """

  @api
  Scenario: Assert that watchdog does not track errors with level below threshold
    Given set Drupal8 watchdog error level "notice"

  @api @error
  Scenario: Assert that watchdog track errors with level above threshold
    Given set Drupal8 watchdog error level "warning"
