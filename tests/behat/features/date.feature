Feature: Check that DateTrait works

  @api
  Scenario: Assert that table transform works
    Given I am logged in as a user with the "administrator" role
    When I go to "node/add/article"
    And I fill in "Title" with "[TEST] Article 1 first [relative:-10 years#Y-m-d] and second [relative:-9 years#Y-m-d]"
    And I select "Published" from "edit-moderation-state-0-state"
    And I press "Save"
    Then the response status code should be 200
    And I should see the text "[TEST] Article 1 first 2014-07-15 and second 2015-07-15"

  @api
  Scenario: Assert that table transform works
    Given "article" content:
      | title            | created              | status | moderation_state |
      | [TEST] Article 1 | [relative:-10 years] | 1      | published        |
    When I visit article "[TEST] Article 1"
    Then the response status code should be 200
    And I should see the text "201"

    @api
  Scenario: Assert that date transform works
    Given I am logged in as a user with the "administrator" role
    When I go to "node/add/article"
    And I fill in "Title" with "[TEST] Article relative 5 seconds [relative:5 seconds#Y-m-d H:i:s]"
    And I select "Published" from "edit-moderation-state-0-state"
    And I press "Save"
    Then the response status code should be 200
    And I should see the text "[TEST] Article relative 5 seconds 2024-07-15 12:00:05"

  @api
  Scenario: Assert that date transform works
    Given I am logged in as a user with the "administrator" role
    When I go to "node/add/article"
    And I fill in "Title" with "[TEST] Article relative 30 seconds [relative:30 seconds#Y-m-d H:i:s]"
    And I select "Published" from "edit-moderation-state-0-state"
    And I press "Save"
    Then the response status code should be 200
    And I should see the text "[TEST] Article relative 30 seconds 2024-07-15 12:00:30"

  @api
  Scenario: Assert that date transform works
    Given I am logged in as a user with the "administrator" role
    When I go to "node/add/article"
    And I fill in "Title" with "[TEST] Article relative 65 seconds [relative:65 seconds#Y-m-d H:i:s]"
    And I select "Published" from "edit-moderation-state-0-state"
    And I press "Save"
    Then the response status code should be 200
    And I should see the text "[TEST] Article relative 65 seconds 2024-07-15 12:00:60"