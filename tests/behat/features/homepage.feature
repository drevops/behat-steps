@smoke @homepage
Feature: Homepage
  As Behat Steps library developer
  I want to provide tools to test homepage access
  So that users can verify basic site navigation

  @api
  Scenario: Anonymous user visits homepage
    When I go to the homepage
    Then the path should be "/"
    And I save screenshot

  @api @javascript
  Scenario: Anonymous user visits homepage using a real browser
    When I go to the homepage
    Then the path should be "/"
    And I save screenshot
