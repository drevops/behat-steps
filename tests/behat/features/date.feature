Feature: Check that DateTrait works
  As Behat Steps library developer
  I want to provide tools to use relative dates in tests
  So that users can create time-based test scenarios independent of current date

  Scenario: Assert that relative date works in value transform
    Given I am logged in as a user with the role "administrator"
    When I go to "node/add/article"
    And I fill in "Title" with "[TEST] Article 1 first [relative:-10 years#Y-m-d] and second [relative:-9 years#Y-m-d]"
    And I select "Published" from "edit-moderation-state-0-state"
    And I press "Save"
    Then the response status code should be 200
    And I should see "[TEST] Article 1 first 2014-07-15 and second 2015-07-15"

  Scenario: Assert that relative date works in table transform
    Given the following "article" content exists:
      | title            | created              | status | moderation_state |
      | [TEST] Article 1 | [relative:-10 years] | 1      | published        |
    When I visit the "article" content page with the title "[TEST] Article 1"
    Then the response status code should be 200
    And I should see "201"

  Scenario: Assert that relative date works in table transform for seconds
    Given I am logged in as a user with the role "administrator"
    When I go to "node/add/article"
    And I fill in "Title" with "[TEST] Article relative 5 seconds [relative:5 seconds#Y-m-d H:i:s]"
    And I select "Published" from "edit-moderation-state-0-state"
    And I press "Save"
    Then the response status code should be 200
    And I should see "[TEST] Article relative 5 seconds 2024-07-15 12:00:05"

  Scenario: Assert that relative date works in table transform for seconds under a minute
    Given I am logged in as a user with the role "administrator"
    When I go to "node/add/article"
    And I fill in "Title" with "[TEST] Article relative 30 seconds [relative:30 seconds#Y-m-d H:i:s]"
    And I select "Published" from "edit-moderation-state-0-state"
    And I press "Save"
    Then the response status code should be 200
    And I should see "[TEST] Article relative 30 seconds 2024-07-15 12:00:30"

  Scenario: Assert that relative date works in table transform for seconds over a minute
    Given I am logged in as a user with the role "administrator"
    When I go to "node/add/article"
    And I fill in "Title" with "[TEST] Article relative 65 seconds [relative:65 seconds#Y-m-d H:i:s]"
    And I select "Published" from "edit-moderation-state-0-state"
    And I press "Save"
    Then the response status code should be 200
    And I should see "[TEST] Article relative 65 seconds 2024-07-15 12:01:05"

  Scenario: Assert that relative date works in text transform
    When I run the command "echo 2014-07-15"
    Then the command output should contain "[relative:-10 years#Y-m-d]"
    And the command output should be "[relative:-10 years#Y-m-d]"

  Scenario: Assert that relative date works in content transform
    Given the unmanaged file at the URI "public://relative-date.txt" exists with the content "[relative:-10 years#Y-m-d]"
    Then the unmanaged file at the URI "public://relative-date.txt" should contain "2014-07-15"

  @phpserver
  Scenario: Assert that relative date works in partial value transform
    When I visit "http://cli:8888/cookies.html"
    And I set a test cookie with name "testdate" and value "[relative:-10 years#Y-m-d]"
    Then the cookie "testdate" with the value "2014-07-15" should exist
    And the cookie "testdate" with a value containing "[relative:-10 years#Y-m]" should exist
