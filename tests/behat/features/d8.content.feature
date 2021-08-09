@d8 @d9
Feature: Check that ContentTrait works for D8

  @api
  Scenario: Assert visiting page with title of specified content type
    Given page content:
      | title             |
      | [TEST] Page title |
    When I am logged in as a user with the "administrator" role
    And I visit "page" "[TEST] Page title"
    Then I should see "[TEST] Page title"

  @api
  Scenario: Assert editing page with title of specified content type
    Given page content:
      | title             |
      | [TEST] Page title |
    When I am logged in as a user with the "administrator" role
    And I edit "page" "[TEST] Page title"
    Then I should see "[TEST] Page title"

  @api
  Scenario: Assert removing page with title and specified type
    Given page content:
      | title             |
      | [TEST] Page title |
    When I am logged in as a user with the "administrator" role
    And I go to "content/test-page-title"
    Then I should get a 200 HTTP response
    When no page content:
      | title             |
      | [TEST] Page title |
    And I go to "content/test-page-title"
    Then I should get a 404 HTTP response

  @api
  Scenario: Assert visiting scheduled transition page with title of specified content type
    Given page content:
      | title             |
      | [TEST] Page title |
    And I am logged in as a user with the "administrator" role
    When I visit page "[TEST] Page title" scheduled transitions
    Then I should see "[TEST] Page title"
    And save screenshot
