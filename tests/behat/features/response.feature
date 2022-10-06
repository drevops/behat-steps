@d7 @d9 @d10
Feature: Check that ResponseTrait works

  Scenario: Assert header response
    Given I go to "/"
    And response contains header "Content-Type"
    And response does not contain header "NotExistingHeader"
    And response header "Content-Type" contains "text/html; charset=utf-8"
    And response header "Content-Type" does not contain "NotExistingHeaderValue"
