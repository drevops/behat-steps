@smoke @homepage
Feature: Homepage

  Ensure that homepage is displayed as expected.

  @api
  Scenario: Anonymous user visits homepage
    Given I go to the homepage
    Then the path should be "/"
    And I save screenshot

  @api @javascript
  Scenario: Anonymous user visits homepage using a real browser
    Given I go to the homepage
    Then the path should be "/"
    And I save screenshot
