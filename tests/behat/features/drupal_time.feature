Feature: Check that TimeTrait works

  Scenario: Assert system time can be overridden
    When I go to "/mysite_core/test-time"
    Then I should not see "1737849900"
    When the system time is set to "1737849900"
    And I go to "/mysite_core/test-time"
    Then I should see "1737849900"

  Scenario: Assert system time can be reset
    When the system time is set to "1737849900"
    And I go to "/mysite_core/test-time"
    Then I should see "1737849900"
    When the system time is reset
    And I go to "/mysite_core/test-time"
    Then I should not see "1737849900"

  Scenario: Assert system time is cleaned up after scenario
    When I go to "/mysite_core/test-time"
    Then I should not see "1737849900"

  @test-trait:Drupal\TimeTrait
  Scenario: Assert that "When the system time is set to :value" fails when the value is not an integer
    Given some behat configuration
    And scenario steps:
      """
      When the system time is set to "tomorrow"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The value must be an integer, but "tomorrow" was given.
      """
