Feature: Check that JavascriptTrait works
  As a Behat Steps library developer
  I want to provide tools to track and verify JavaScript errors
  So that users can ensure their pages are error-free

  @javascript
  Scenario: Assert no JavaScript errors when none exist
    When I go to "/"
    Then there should be no JavaScript errors on the page

  @javascript
  Scenario: Assert JavaScript error is detected when one exists
    When I go to "/"
    And I execute the JavaScript code "throw new Error('Test JS error');"
    Then I should get a JavaScript error

  @javascript
  Scenario: Assert no JavaScript errors fails when errors exist
    When I go to "/"
    And I execute the JavaScript code "console.error('Test JS error');"
    Then I should get a JavaScript error
