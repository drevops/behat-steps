Feature: Check that TimeTrait works

  Scenario: Assert system time can be overridden
    When I go to "/mysite_core/test-time"
    Then I should not see "1737849900"
    When I set the system time to the value "1737849900"
    And I go to "/mysite_core/test-time"
    Then I should see "1737849900"

  Scenario: Assert system time can be reset
    When I set the system time to the value "1737849900"
    And I go to "/mysite_core/test-time"
    Then I should see "1737849900"
    When I reset the system time
    And I go to "/mysite_core/test-time"
    Then I should not see "1737849900"

  Scenario: Assert system time is cleaned up after scenario
    When I go to "/mysite_core/test-time"
    Then I should not see "1737849900"
