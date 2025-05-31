Feature: Page JS Integrity

  @javascript @no-js-errors
  Scenario: Visiting home page should not produce JS errors
    Given I am on "/"
    Then I should see no JavaScript errors

    When I go to "/about"
    Then I should see no JavaScript errors
