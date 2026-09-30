@test-errorcleanup
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
  Scenario: Assert that a failing scenario is reported as failed and recorded for a rerun
    Given some behat configuration
    And scenario steps:
      """
      When set watchdog error level "warning"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      PHP errors were logged to watchdog
      """
    And the output should contain:
      """
      1 scenario (1 failed)
      """
    When I run "behat --no-colors --rerun-only"
    Then it should fail with:
      """
      PHP errors were logged to watchdog
      """

  @trait:Drupal\WatchdogTrait
  Scenario: Assert that an error logged after the last step is reported
    Given some behat configuration
    And scenario steps tagged with "@test-watchdog-teardown":
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      PHP errors were logged to watchdog during the teardown of scenario "Stub scenario title"
      """

  @trait:Drupal\WatchdogTrait
  Scenario: Assert that an error is reported when an earlier step failed
    Given some behat configuration
    And scenario steps:
      """
      When set watchdog error level "warning"
      Then I should see "text that is not on the page"
      And I visit "/"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      PHP errors were logged to watchdog during scenario "Stub scenario title"
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
    And scenario steps tagged with "@watchdog:custom_type":
      """
      When set watchdog error level "warning" of type "custom_type"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      PHP errors were logged to watchdog during scenario "Stub scenario title" (line 3):
      """

  Scenario: Assert that watchdog does not track errors with level below threshold
    When set watchdog error level "notice"

  @error
  Scenario: Assert that watchdog track errors with level above threshold
    When set watchdog error level "warning"

  @watchdog:custom_type
  Scenario: Assert that @watchdog tag parsing works with custom type
    # This scenario tests that the @watchdog:custom_type tag is parsed correctly
    # The watchdog functionality will check for both 'php' and 'custom_type' messages
    Given the watchdog is cleared
    When I go to the homepage

  @watchdog:type1 @watchdog:type2
  Scenario: Assert that multiple @watchdog tags are parsed correctly
    # This scenario tests that multiple @watchdog tags are parsed into message types
    Given the watchdog is cleared
    When I go to the homepage

  @trait:Drupal\WatchdogTrait
  Scenario: Assert that the skip tag switches the WatchdogTrait hooks off
    Given some behat configuration
    And scenario steps tagged with "@behat-steps-skip:WatchdogTrait":
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:Drupal\WatchdogTrait,Drupal\ModuleTrait
  Scenario: Assert that a missing watchdog table fails with the switches that turn the check off
    Given some behat configuration
    And scenario steps tagged with "@module:!dblog":
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The "watchdog" table does not exist, so logged errors cannot be checked. Enable the "dblog" module, or switch the check off with the "watchdog.enabled" option or the "@behat-steps-skip:WatchdogTrait" tag.
      """

  @trait:Drupal\WatchdogTrait,Drupal\ModuleTrait
  Scenario: Assert that the enabled option switches the check off for a missing watchdog table
    Given a configuration with the step options:
      """
      'watchdog' => ['enabled' => FALSE],
      """
    And some behat configuration
    And scenario steps tagged with "@module:!dblog":
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:Drupal\WatchdogTrait,Drupal\ModuleTrait
  Scenario: Assert that the skip tag switches the check off for a missing watchdog table
    Given some behat configuration
    And scenario steps tagged with "@module:!dblog @behat-steps-skip:WatchdogTrait":
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:Drupal\WatchdogTrait,Drupal\ModuleTrait
  Scenario: Assert that turning fail_on_errors off does not cover a missing watchdog table
    Given a configuration with the step options:
      """
      'watchdog' => ['fail_on_errors' => FALSE],
      """
    And some behat configuration
    And scenario steps tagged with "@module:!dblog":
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The "watchdog" table does not exist
      """

  @trait:Drupal\WatchdogTrait,Drupal\ModuleTrait
  Scenario: Assert that the error tag does not cover a missing watchdog table
    Given some behat configuration
    And scenario steps tagged with "@module:!dblog @error":
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The "watchdog" table does not exist
      """

  @trait:Drupal\WatchdogTrait
  Scenario: Assert that a configuration reaching Drupal only through Drush runs no check
    Given a configuration listing the drivers "drush, blackbox"
    And some behat configuration
    And scenario steps:
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should pass
