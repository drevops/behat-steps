@behatcli
Feature: Behat CLI context additional steps

  @trait:PathTrait
  Scenario: Test fails with exception
    Given some behat configuration
    And scenario steps:
      """
      Given I go to the homepage
      Then I throw test exception with message "Intentional error"
      And I should be in the "nonexisting" path
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Intentional error
      """

  @trait:PathTrait
  Scenario: Test with additionally tagged scenario fails with exception
    Given some behat configuration
    And scenario steps tagged with "@tag1 @tag2":
      """
      Given I go to the homepage
      Then I throw test exception with message "Intentional error"
      And I should be in the "nonexisting" path
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

  @trait:PathTrait
  Scenario: Test fails
    Given some behat configuration
    And scenario steps:
      """
      Given I go to the homepage
      And I should be in the "nonexisting" path
      """
    When I run "behat --no-colors"
    Then it should fail

  @trait:PathTrait
  Scenario: Test fails with message
    Given some behat configuration
    And scenario steps:
      """
      Given I go to the homepage
      And I should be in the "nonexisting" path
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Current path is "<front>", but expected is "nonexisting"
      """
