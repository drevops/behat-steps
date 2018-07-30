Feature: Homepage

  Ensure that homepage is displayed as expected.

  @api @d7 @d8
  Scenario: Anonymous user visits homepage
    Given I go to the homepage
    And I should be in the "<front>" path
    Then I save screenshot

  @api @javascript @d7 @d8
  Scenario: Anonymous user visits homepage
    Given I go to the homepage
    And I should be in the "<front>" path
    Then I save screenshot
