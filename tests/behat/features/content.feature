Feature: Check that ContentTrait works

  @api
  Scenario: Assert visiting a page with title of specified content type
    Given page content:
      | title             |
      | [TEST] Page title |
    When I am logged in as a user with the "administrator" role
    And I visit "page" "[TEST] Page title"
    Then I should see "[TEST] Page title"

  @trait:ContentTrait
  Scenario: Assert visiting page with non-existing node throws an exception
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "page" "[TEST] Non-Existing Page title"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find page page "[TEST] Non-Existing Page title"
      """

  @api
  Scenario: Assert visiting edit page with title of specified content type
    Given page content:
      | title             |
      | [TEST] Page title |
    When I am logged in as a user with the "administrator" role
    And I edit "page" "[TEST] Page title"
    Then I should see "[TEST] Page title"

  @trait:ContentTrait
  Scenario: Assert visiting edit page with non-existing node throws an exception
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I edit "page" "[TEST] Non-Existing Page title"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find page page "[TEST] Non-Existing Page title"
      """

  @api
  Scenario: Assert visiting delete page with title of specified content type
    Given page content:
      | title             |
      | [TEST] Page title |
    When I am logged in as a user with the "administrator" role
    And I delete "page" "[TEST] Page title"
    Then I should see "[TEST] Page title"

  @trait:ContentTrait
  Scenario: Assert delete page with non-existing node throws an exception
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then I delete "page" "[TEST] Non-Existing Page title"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find page page "[TEST] Non-Existing Page title"
      """

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
    When I visit "page" "[TEST] Page title" scheduled transitions
    Then I should see "[TEST] Page title"
    And save screenshot

  @trait:ContentTrait
  Scenario: Assert visiting scheduled transition page with non-existing node throws an exception
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit "page" "[TEST] Non-Existing Page title" scheduled transitions
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find page page "[TEST] Non-Existing Page title"
      """

  @api
  Scenario: Assert change moderation state of a content with specified title
    Given page content:
      | title             | moderation_state |
      | [TEST] Page title | draft            |
    And I am logged in as a user with the "administrator" role
    Then I edit "page" "[TEST] Page title"
    Then I should see "Draft" in the "#edit-moderation-state-0-current" element
    When the moderation state of "page" "[TEST] Page title" changes from "draft" to "published"
    And I edit "page" "[TEST] Page title"
    Then I should see "Published" in the "#edit-moderation-state-0-current" element

  @trait:ContentTrait
  Scenario: Assert change moderation state of a content with non-existing node throws an exception
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When the moderation state of "page" "[TEST] Non-Existing Page title" changes from "draft" to "published"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Unable to find page page "[TEST] Non-Existing Page title"
      """

  @trait:ContentTrait
  Scenario: Assert change moderation state of a content with a different current state throws an exception
    Given some behat configuration
    And scenario steps:
      """
      Given page content:
      | title             | moderation_state |
      | [TEST] Page title | draft            |
      When the moderation state of "page" "[TEST] Page title" changes from "review" to "published"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The current state "draft" is different from "review"
      """

  @api
  Scenario: Assert Delete content type
    When I am logged in as a user with the "administrator" role
    Then I visit "/admin/structure/types/add"
    Then I fill in "Name" with "test_content_type"
    And I fill in "Machine-readable name" with "test_content_type"
    Then I press "Save"
    Then I visit "/admin/structure/types"
    Then I should see "test_content_type"
    Given no "test_content_type" content type
    Then I visit "/admin/structure/types"
    Then I should not see "test_content_type"
