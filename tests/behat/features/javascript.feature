Feature: Check that JavascriptTrait works
  As a Behat Steps library developer
  I want to provide tools to track and verify JavaScript errors
  So that users can ensure their pages are error-free

  @javascript @no-js-errors
  Scenario: Assert no JavaScript errors when none exist
    When I go to "/"


  @javascript @no-js-errors
  Scenario: Assert JavaScript error is detected when one exists
    When I visit "/sites/default/files/relative.html"
    And the element "#js-error-trigger" should be displayed
    And I click on the element "#js-error-trigger"

