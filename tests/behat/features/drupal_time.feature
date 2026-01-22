Feature: Ensure TimeTrait works.

  @api
  Scenario: Assert system time can be overridden
    When I go to "/mysite_core/test-time"
    Then I should not see the text "1737849900"
    When I set system time to "1737849900"
    And I go to "/mysite_core/test-time"
    Then I should see the text "1737849900"

  @api
  Scenario: Assert system time can be reset
    When I set system time to "1737849900"
    And I go to "/mysite_core/test-time"
    Then I should see the text "1737849900"
    When I reset system time
    And I go to "/mysite_core/test-time"
    Then I should not see the text "1737849900"

  @api
  Scenario: Assert system time is cleaned up after scenario
    When I go to "/mysite_core/test-time"
    Then I should not see the text "1737849900"
