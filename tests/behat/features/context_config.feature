@test-errorcleanup
Feature: Check that trait configuration works
  As Behat Steps library developer
  I want to provide a declared option per trait, resolved from the extension and the context
  So that users can configure a trait once instead of tagging every feature file

  @trait:Drupal\WatchdogTrait
  Scenario: Assert that a disabled trait reads nothing
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
    # A disabled trait reads nothing, so the row the inner run logged is still
    # there for this scenario's own check to find.
    And the watchdog is cleared

  @trait:Drupal\WatchdogTrait
  Scenario: Assert that a trait that does not fail on errors still passes
    Given a configuration with the step options:
      """
      'watchdog' => ['fail_on_errors' => FALSE],
      """
    And some behat configuration
    And scenario steps:
      """
      When set watchdog error level "warning"
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:Drupal\WatchdogTrait
  Scenario: Assert that a context argument overrides the extension defaults
    Given a configuration with the step options:
      """
      'watchdog' => ['fail_on_errors' => FALSE],
      """
    And a context with the arguments:
      """
      'config' => ['watchdog' => ['fail_on_errors' => TRUE]],
      """
    And some behat configuration
    And scenario steps:
      """
      When set watchdog error level "warning"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      PHP errors were logged to watchdog
      """

  @trait:Drupal\WatchdogTrait
  Scenario: Assert that a scenario tag overrides a context argument
    Given a context with the arguments:
      """
      'config' => ['watchdog' => ['fail_on_errors' => TRUE]],
      """
    And some behat configuration
    And scenario steps tagged with "@error":
      """
      When set watchdog error level "warning"
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:Drupal\WatchdogTrait
  Scenario: Assert that an unknown option group names what the context accepts
    Given a context with the arguments:
      """
      'config' => ['nonexistent' => ['enabled' => FALSE]],
      """
    And some behat configuration
    And scenario steps:
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should fail with:
      """
      Unknown option group "nonexistent" for context "FeatureContext".
      """

  @trait:Drupal\WatchdogTrait
  Scenario: Assert that an unknown option names what the group accepts
    Given a context with the arguments:
      """
      'config' => ['watchdog' => ['nonexistent' => FALSE]],
      """
    And some behat configuration
    And scenario steps:
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should fail with:
      """
      Unknown option "watchdog.nonexistent" for context "FeatureContext". The "watchdog" group accepts: enabled, fail_on_errors.
      """

  @trait:Drupal\WatchdogTrait
  Scenario: Assert that a value of the wrong type names the type it expects
    Given a context with the arguments:
      """
      'config' => ['watchdog' => ['enabled' => 'yes']],
      """
    And some behat configuration
    And scenario steps:
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should fail with:
      """
      The "watchdog.enabled" option expects a boolean, but a string was given.
      """

  @trait:Drupal\WatchdogTrait
  Scenario: Assert that a group naming a trait the context does not compose is ignored
    Given a configuration with the step options:
      """
      'nonexistent' => ['enabled' => FALSE],
      """
    And some behat configuration
    And scenario steps:
      """
      When I visit "/"
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:Web\RandomTrait,Web\CommandTrait
  Scenario: Assert that a disabled transform passes its token through
    Given a configuration with the step options:
      """
      'random' => ['enabled' => FALSE],
      """
    And some behat configuration
    And scenario steps:
      """
      When I run the command "echo -n '[?slug]'"
      Then the command output should contain "?slug"
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:Web\RandomTrait,Web\CommandTrait
  Scenario: Assert that an enabled transform replaces its token
    Given some behat configuration
    And scenario steps:
      """
      When I run the command "echo -n '[?slug]'"
      Then the command output should not contain "?slug"
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:Web\CommandTrait
  Scenario: Assert that a configured option reaches the step that reads it
    Given a configuration with the step options:
      """
      'command' => ['timeout' => 1],
      """
    And some behat configuration
    And scenario steps:
      """
      When I run the command "sleep 5"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The command "sleep 5" timed out after 1 seconds.
      """
