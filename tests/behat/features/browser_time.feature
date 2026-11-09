@browser_time
Feature: Check that BrowserTimeTrait works
  As Behat Steps library developer
  I want to provide steps that set the time and the timezone of the browser
  So that users can test client-side date and time logic

  @javascript @phpserver
  Scenario: Assert "Given the browser time is set to :value" sets the time from a timestamp
    Given the browser time is set to "1798156740"
    When I visit "http://cli:8888/browser_time.html"
    Then the "#utc" element should contain "2026-12-24T23:59"
    And the "#timestamp" element should contain "17981567"
    And the "#banner" element should contain "Sale ends in 1 minutes"

  @javascript @phpserver
  Scenario: Assert "Given the browser time is set to :value" sets the time from a date and time
    Given the browser time is set to "2026-12-25T01:00:00+00:00"
    When I visit "http://cli:8888/browser_time.html"
    Then the "#utc" element should contain "2026-12-25T01:00"
    And the "#banner" element should contain "Sale has ended"

  @javascript @phpserver
  Scenario: Assert the overridden Date keeps the native behavior
    Given the browser time is set to "1798156740"
    When I visit "http://cli:8888/browser_time.html"
    Then the "#string" element should contain "string"
    And the "#instance" element should contain "yes"
    And the "#parsed" element should contain "2000-01-02T03:04:05.000Z"

  @javascript @phpserver
  Scenario: Assert the browser time stays set across page loads
    Given the browser time is set to "1798156740"
    When I visit "http://cli:8888/browser_time.html"
    And I visit "http://cli:8888/browser_time.html"
    Then the "#utc" element should contain "2026-12-24T23:59"

  @javascript @phpserver
  Scenario: Assert the last browser time set applies
    Given the browser time is set to "1798156740"
    And the browser time is set to "2026-12-25T01:00:00+00:00"
    When I visit "http://cli:8888/browser_time.html"
    Then the "#utc" element should contain "2026-12-25T01:00"

  @javascript @phpserver
  Scenario: Assert "Given the browser time is set to :value in the timezone :name" sets the time and the timezone
    Given the browser time is set to "2026-12-24 23:59:00" in the timezone "Australia/Melbourne"
    When I visit "http://cli:8888/browser_time.html"
    Then the "#local" element should contain "2026-12-24 23:59"
    And the "#utc" element should contain "2026-12-24T12:59"
    And the "#timezone" element should contain "Australia/Melbourne"

  @javascript @phpserver
  Scenario: Assert "Given the browser timezone is set to :name" sets the timezone
    Given the browser timezone is set to "America/New_York"
    When I visit "http://cli:8888/browser_time.html"
    Then the "#timezone" element should contain "America/New_York"
    And the "#utc" element should not contain "2026-12-24"

  @javascript @phpserver
  Scenario: Assert "When the browser time is reset" restores the real time and timezone
    Given the browser time is set to "2026-12-24 23:59:00" in the timezone "Australia/Melbourne"
    When I visit "http://cli:8888/browser_time.html"
    Then the "#utc" element should contain "2026-12-24T12:59"
    When the browser time is reset
    And I visit "http://cli:8888/browser_time.html"
    Then the "#utc" element should not contain "2026-12-24"
    And the "#timezone" element should not contain "Australia/Melbourne"

  # Setup scenario: the AfterScenario hook is skipped here, so the overrides
  # persist into the following scenario.
  @javascript @phpserver @behat-steps-skip:BrowserTimeTrait
  Scenario: Set the browser time without restoring it
    Given the browser time is set to "1798156740" in the timezone "Australia/Melbourne"
    When I visit "http://cli:8888/browser_time.html"
    Then the "#utc" element should contain "2026-12-24T23:59"

  @javascript @phpserver
  Scenario: Verify the skipped hook left the browser time set and the reset step restores it
    When I visit "http://cli:8888/browser_time.html"
    Then the "#utc" element should contain "2026-12-24T23:59"
    And the "#timezone" element should contain "Australia/Melbourne"
    When the browser time is reset
    And I visit "http://cli:8888/browser_time.html"
    Then the "#utc" element should not contain "2026-12-24"
    And the "#timezone" element should not contain "Australia/Melbourne"

  # Setup scenario: the AfterScenario hook restores the overrides set here, as
  # the following scenario verifies.
  @javascript @phpserver
  Scenario: Set the browser time and let the hook restore it
    Given the browser time is set to "1798156740" in the timezone "Australia/Melbourne"
    When I visit "http://cli:8888/browser_time.html"
    Then the "#utc" element should contain "2026-12-24T23:59"

  @javascript @phpserver
  Scenario: Verify the hook restored the real time and timezone after the previous scenario
    When I visit "http://cli:8888/browser_time.html"
    Then the "#utc" element should not contain "2026-12-24"
    And the "#timezone" element should not contain "Australia/Melbourne"

  @test-trait:BrowserTimeTrait
  Scenario: Assert "Given the browser time is set to :value" fails on a browser driver without the clock capability
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the browser time is set to "1798156740"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Behat\Mink\Exception\UnsupportedDriverActionException" exception:
      """
      No browser capability "DrevOps\BehatSteps\Extension\Browser\Capability\ClockCapabilityInterface" is available for
      """

  @test-trait:BrowserTimeTrait
  Scenario: Assert "Given the browser time is set to :value" fails on a value that is not a time
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given the browser time is set to "not a time"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The value must be a Unix timestamp or a date and time, but "not a time" was given.
      """

  @test-trait:BrowserTimeTrait
  Scenario: Assert "Given the browser time is set to :value" fails on an empty value
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given the browser time is set to ""
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The value must be a Unix timestamp or a date and time, but "" was given.
      """

  @test-trait:BrowserTimeTrait
  Scenario: Assert "Given the browser time is set to :value in the timezone :name" fails on a name that is not a timezone
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given the browser time is set to "2026-12-24 23:59:00" in the timezone "+10:00"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The timezone must be a timezone name, such as "Europe/London", but "+10:00" was given.
      """

  @test-trait:BrowserTimeTrait
  Scenario: Assert "Given the browser timezone is set to :name" fails on a name that is not a timezone
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given the browser timezone is set to "Mars/Olympus_Mons"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The timezone must be a timezone name, such as "Europe/London", but "Mars/Olympus_Mons" was given.
      """
