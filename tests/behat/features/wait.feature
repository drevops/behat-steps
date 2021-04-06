@d7 @d8 @api @javascript
Feature: Check that WaitTrait works

  Scenario: Anonymous user visits homepage
    Given I go to the homepage
    And wait 1 second
    Then I save screenshot
    And wait 2 seconds
    Then I save screenshot
    And I wait 1 second
    Then I save screenshot
    And I wait 2 seconds
