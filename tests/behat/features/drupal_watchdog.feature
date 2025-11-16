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

  @api @watchdog:custom_type
  Scenario: Assert that @watchdog tag parsing works with custom type
    # This scenario tests that the @watchdog:custom_type tag is parsed correctly
    # The watchdog functionality will check for both 'php' and 'custom_type' messages
    Given the watchdog is cleared
    When I go to the homepage

  @api @watchdog:type1 @watchdog:type2
  Scenario: Assert that multiple @watchdog tags are parsed correctly
    # This scenario tests that multiple @watchdog tags are parsed into message types
    Given the watchdog is cleared
    When I go to the homepage

  @trait:Drupal\WatchdogTrait
  Scenario: Assert that skip tag for watchdogSetScenario hook works
    Given some behat configuration
    And scenario steps tagged with "@behat-steps-skip:watchdogSetScenario":
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:Drupal\WatchdogTrait
  Scenario: Assert that skip tag for watchdogAfterScenario hook works
    Given some behat configuration
    And scenario steps tagged with "@behat-steps-skip:watchdogAfterScenario":
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:Drupal\WatchdogTrait,Drupal\ModuleTrait
  Scenario: Assert that missing watchdog table throws RuntimeException
    Given some behat configuration
    And scenario steps tagged with "@module:!dblog":
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Watchdog table does not exist. Ensure the dblog module is enabled.
      """
