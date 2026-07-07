Feature: Check that DiagnosticsTrait works
  As Behat Steps library developer
  I want on-failure diagnostics appended to the failed step message
  So that a red CI run is self-explanatory without a re-run

  @trait:DiagnosticsTrait
  Scenario: Diagnostics are appended to the message of a failed step
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "/"
      Then I should see "TextThatIsDefinitelyNotOnThisPage"
      """
    When I run "behat --no-colors"
    Then it should fail with:
      """
      --- Failure diagnostics ---
      """
    And the output should contain:
      """
      URL: http
      """
    And the output should contain:
      """
      HTTP status:
      """
    And the output should contain:
      """
      Mink driver:
      """
    And the output should contain:
      """
      Re-run: vendor/bin/behat
      """
    And the output should contain:
      """
      stub.feature:
      """

  @trait:DiagnosticsTrait
  Scenario: Diagnostics are suppressed for an opted-out scenario
    Given some behat configuration
    And scenario steps tagged with "@behat-steps-skip:DiagnosticsTrait":
      """
      Given I go to "/"
      Then I should see "TextThatIsDefinitelyNotOnThisPage"
      """
    When I run "behat --no-colors"
    Then it should fail
    And the output should not contain:
      """
      --- Failure diagnostics ---
      """
