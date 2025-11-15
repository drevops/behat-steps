@behat-steps-skip:JavascriptTrait
Feature: Check that JavascriptTrait skip at feature level works
  As Behat Steps library developer
  I want to be able to skip JavaScript error checking at feature level
  So that I can disable it for entire feature files

  @javascript
  Scenario: Feature-level skip tag bypasses error checking even with @javascript
    Given I visit "/sites/default/files/errors1.html"
    Then I should see "Page 1 with JavaScript Errors"
    When I press "Click to trigger error"
    And sleep for 4 seconds
    # This should pass even though there are JavaScript errors
    # because the feature has @behat-steps-skip:JavascriptTrait tag
