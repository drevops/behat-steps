@d7
Feature: Check that ResponseTrait for D7 works

  @api
  Scenario: Assert header response
    Given I go to "/"
    And response contains header "Server"
    And response does not contain header "NotExist"
    And response header "Server" contains "nginx"
    And response header "Server" does not contain "NotExist"
