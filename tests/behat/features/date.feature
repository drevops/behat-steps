Feature: Check that DateTrait works

  @api
  Scenario: Assert that table transform works
    Given I am logged in as a user with the "administrator" role
    When I go to "node/add/article"
    And I fill in "Title" with "[TEST] Article 1 first [relative:-10 years#Y-m-d] and second [relative:-9 years#Y-m-d]"
    And I select "Published" from "edit-moderation-state-0-state"
    And I press "Save"
    Then the response status code should be 200
    And I should see the text "[TEST] Article 1 first 201"
    And I should see the text "and second 201"

  @api
  Scenario: Assert that table transform works
    Given "article" content:
      | title            | created              | status | moderation_state |
      | [TEST] Article 1 | [relative:-10 years] | 1      | published        |
    When I visit article "[TEST] Article 1"
    Then the response status code should be 200
    And I should see the text "201"
