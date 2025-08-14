Feature: Check that datetime fields are handled correctly in FieldTrait
  As a Behat Steps library developer
  I want to provide tools to interact with datetime fields
  So that users can set date, time, or both values in their tests

  @api @datetime
  Scenario: Set both date and time values on a datetime field
    Given I am logged in as a user with the "administrator" role
    When I go to "node/add/article"
    And I fill in "Title" with "[TEST] Article with datetime field"
    And I fill in the datetime field "Date and time" with date "2024-03-15" and time "14:30:00"
    And I select "Published" from "edit-moderation-state-0-state"
    And I press "Save"
    Then the response status code should be 200
    And I should see the text "[TEST] Article with datetime field"
    And I should see the text "Date and time"
    And I should see the text "03/15/2024 - 14:30"

  @api @datetime
  Scenario: Set date value on a date field
    Given I am logged in as a user with the "administrator" role
    When I go to "node/add/article"
    And I fill in "Title" with "[TEST] Article with datetime field"
    And I fill in the date field "Date only" with date "2024-03-15"
    And I select "Published" from "edit-moderation-state-0-state"
    And I press "Save"
    Then the response status code should be 200
    And I should see the text "[TEST] Article with datetime field"
    And I should see the text "Date"
    And I should see the text "03/15/2024"
