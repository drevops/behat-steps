@test-errorcleanup
Feature: Check that WatchdogTrait works
  As Behat Steps library developer
  I want to provide tools to monitor Drupal watchdog messages
  So that users can detect unexpected errors in their tests

  @test-trait:Drupal\WatchdogTrait
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

  @test-trait:Drupal\WatchdogTrait
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

  @test-trait:Drupal\WatchdogTrait
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

  @test-trait:Drupal\WatchdogTrait
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

  @test-trait:Drupal\WatchdogTrait
  Scenario: Assert that watchdog does not fail when a custom message type is triggered
    Given some behat configuration
    And scenario steps:
      """
      When set watchdog error level "warning" of type "custom_type"
      """
    When I run "behat --no-colors"
    Then it should pass

  @test-trait:Drupal\WatchdogTrait
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
    Given the watchdog is cleared
    When I go to the homepage

  @watchdog:type1 @watchdog:type2
  Scenario: Assert that multiple @watchdog tags are parsed correctly
    Given the watchdog is cleared
    When I go to the homepage

  @test-trait:Drupal\WatchdogTrait @error
  Scenario: Assert that the enabled option switches the check off on a site with dblog
    Given a configuration with the step options:
      """
      'watchdog' => ['enabled' => FALSE],
      """
    And some behat configuration
    And scenario steps:
      """
      When set watchdog error level "warning"
      """
    When I run "behat --no-colors"
    Then it should pass

  @test-trait:Drupal\WatchdogTrait @error
  Scenario: Assert that the skip tag switches the check off on a site with dblog
    Given some behat configuration
    And scenario steps tagged with "@behat-steps-skip:WatchdogTrait":
      """
      When set watchdog error level "warning"
      """
    When I run "behat --no-colors"
    Then it should pass

  @test-trait:Drupal\WatchdogTrait @module:!dblog @behat-steps-skip:WatchdogTrait
  Scenario: Assert that an opted-in scenario on a site without dblog fails at its start
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      WatchdogTrait requires that the core "dblog" module is enabled, which does not hold. Meet the prerequisite, or switch WatchdogTrait off with the "watchdog.enabled" option or the "@behat-steps-skip:WatchdogTrait" tag.
      """
    And the output should contain:
      """
      1 step (1 skipped)
      """

  @test-trait:Drupal\WatchdogTrait @module:!dblog @behat-steps-skip:WatchdogTrait
  Scenario: Assert that turning fail_on_errors off does not cover a site without dblog
    Given a configuration with the step options:
      """
      'watchdog' => ['fail_on_errors' => FALSE],
      """
    And some behat configuration
    And scenario steps:
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      WatchdogTrait requires that the core "dblog" module is enabled, which does not hold.
      """

  @test-trait:Drupal\WatchdogTrait @module:!dblog @behat-steps-skip:WatchdogTrait
  Scenario: Assert that the error tag does not cover a site without dblog
    Given some behat configuration
    And scenario steps tagged with "@error":
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      WatchdogTrait requires that the core "dblog" module is enabled, which does not hold.
      """

  @test-trait:Drupal\WatchdogTrait @module:!dblog @behat-steps-skip:WatchdogTrait
  Scenario: Assert that the enabled option switches the check off on a site without dblog
    Given a configuration with the step options:
      """
      'watchdog' => ['enabled' => FALSE],
      """
    And some behat configuration
    And scenario steps:
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should pass

  @test-trait:Drupal\WatchdogTrait @module:!dblog @behat-steps-skip:WatchdogTrait
  Scenario: Assert that the skip tag switches the check off on a site without dblog
    Given some behat configuration
    And scenario steps tagged with "@behat-steps-skip:WatchdogTrait":
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should pass

  @test-trait:Drupal\WatchdogTrait,Drupal\ModuleTrait
  Scenario: Assert that a scenario that uninstalls dblog fails at its last step
    Given some behat configuration
    And scenario steps:
      """
      Given the module "dblog" is disabled
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      WatchdogTrait requires that the core "dblog" module is enabled, which does not hold.
      """
    And the output should contain:
      """
      2 steps (2 passed)
      """

  @test-trait:Drupal\WatchdogTrait
  Scenario: Assert that an opted-in configuration reaching Drupal only through Drush fails at its start
    Given a configuration listing the backends "drush, blackbox"
    And some behat configuration
    And scenario steps:
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should fail with a "DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException" exception:
      """
      WatchdogTrait requires that a backend in the scenario's list provides "CoreCapabilityInterface", which does not hold. Backends available to this scenario, in order: drush, blackbox. Meet the prerequisite, or switch WatchdogTrait off with the "watchdog.enabled" option or the "@behat-steps-skip:WatchdogTrait" tag.
      """
    And the output should contain:
      """
      1 step (1 skipped)
      """

  @test-trait:Drupal\WatchdogTrait
  Scenario: Assert that an opted-out configuration reaching Drupal only through Drush passes
    Given a configuration listing the backends "drush, blackbox"
    And a configuration with the step options:
      """
      'watchdog' => ['enabled' => FALSE],
      """
    And some behat configuration
    And scenario steps:
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should pass

  @test-trait:Drupal\WatchdogTrait
  Scenario: Assert that a @watchdog tag on the feature tracks the type in every scenario
    Given some behat configuration
    And a file named "features/stub.feature" with:
      """
      @watchdog:custom_type
      Feature: Stub feature

        Scenario: Custom type is logged
          When set watchdog error level "warning" of type "custom_type"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      PHP errors were logged to watchdog during scenario "Custom type is logged" (line 4):
      """
