@behatcli
Feature: Behat CLI context additional steps
  As Behat Steps library developer
  I want to provide tools to test CLI step functionality
  So that users can verify CLI testing capabilities work correctly

  @test-trait:PathTrait
  Scenario: Test fails with exception
    Given some behat configuration
    And scenario steps:
      """
      When I go to the homepage
      Then I throw test exception with message "Intentional error"
      And the path should be "/nonexisting"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Intentional error
      """

  @test-trait:PathTrait
  Scenario: Test with additionally tagged scenario fails with exception
    Given some behat configuration
    And scenario steps tagged with "@tag1 @tag2":
      """
      When I go to the homepage
      Then I throw test exception with message "Intentional error"
      And the path should be "/nonexisting"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Intentional error
      """
    And the output should contain:
      """
      @tag1 @tag2
      """

  @test-trait:PathTrait
  Scenario: Test fails
    Given some behat configuration
    And scenario steps:
      """
      When I go to the homepage
      Then the path should be "/nonexisting"
      """
    When I run "behat --no-colors"
    Then it should fail

  @test-trait:PathTrait
  Scenario: Test fails with message
    Given some behat configuration
    And scenario steps:
      """
      When I go to the homepage
      Then the path should be "/nonexisting"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The current path is "/", but it should be "/nonexisting"
      """
