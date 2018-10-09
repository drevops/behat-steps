@d7 @d8
Feature: Check that ResponseTrait for D7 works

  @api
  Scenario: Assert header response
    Given I go to "/"
    And response contains header "Content-Type"
    And response does not contain header "NotExistingHeader"
    And response header "Content-Type" contains "text/html; charset=utf-8"
    And response header "Content-Type" does not contain "NotExistingHeaderValue"
