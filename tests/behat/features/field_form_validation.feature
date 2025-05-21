Feature: Check that browser form validation can be disabled
  As a Behat Steps library user
  I want to disable browser validation for testing form errors
  So that I can test server-side validation without browser interference

  @api @javascript @validation
  Scenario: Disable browser validation for article creation form
    Given I am logged in as a user with the "administrator" role
    When I go to "node/add/article"
    And browser validation for the form "#node-article-form" is disabled
     # Try to submit without filling in required fields.
    And I press "Save"
     # Server-side validation errors should appear.
    Then I should see "Title field is required"
