Feature: Check that BasicAuthTrait works
  As Behat Steps library developer
  I want to keep HTTP basic authentication applied across session resets
  So that a site behind webserver-level auth stays reachable mid-scenario

  Scenario: Assert that a scenario without configured credentials is unaffected
    Given I am an anonymous user
    When I visit "/"
    Then the response status code should be 200

  Scenario: Assert that the credentials survive a log out
    Given I am an anonymous user
    And I am logged in as a user with the role "administrator"
    When I log out
    And I visit "/"
    Then the response status code should be 200

  @behat-steps-skip:BasicAuthTrait
  Scenario: Assert that a scenario with the skip tag runs without re-applying the credentials
    Given I am an anonymous user
    When I visit "/"
    Then the response status code should be 200
