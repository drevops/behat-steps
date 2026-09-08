Feature: Check that BasicAuthTrait works
  As Behat Steps library developer
  I want to keep HTTP basic authentication applied across session resets
  So that a site behind webserver-level auth stays reachable mid-scenario

  @api
  Scenario: Assert that a scenario without configured credentials is unaffected
    Given the user is anonymous
    When I visit "/"
    Then the response status code should be 200

  @api
  Scenario: Assert that the credentials survive a log out
    Given the user is anonymous
    When I log in as a user with the "administrator" role
    And I log out
    And I visit "/"
    Then the response status code should be 200
