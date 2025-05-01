@api
Feature: Check that BlockTrait works
  As Behat Steps library developer
  I want to provide tools to manage blocks programmatically
  So that users can test block placement and visibility functionality

  Scenario: Configure and place a system block in a region
    Given I am logged in as a user with the "administrator" role
    When I create a block of type "User account menu" with:
      | label         | [TEST] User Account Menu  |
      | label_display | 1                         |
      | region        | content             |
      | status        | 1                         |
    Then the block "[TEST] User Account Menu" should exist
    And the block "[TEST] User Account Menu" should exist in the "content" region
    When I visit "/"
    Then I should see "[TEST] User Account Menu"

  Scenario: Verify block can be enabled and disabled
    Given I am logged in as a user with the "administrator" role
    When I create a block of type "User account menu" with:
      | label         | [TEST] User Account Menu  |
      | label_display | 1                         |
      | region        | content             |
      | status        | 1                         |
    Then the block "[TEST] User Account Menu" should be enabled
    When I disable the block "[TEST] User Account Menu"
    Then the block "[TEST] User Account Menu" should be disabled
    When I visit "/"
    Then I should not see "[TEST] User Account Menu"
    When I enable the block "[TEST] User Account Menu"
    Then the block "[TEST] User Account Menu" should be enabled
    And the cache has been cleared
    When I visit "/"
    Then I should see "[TEST] User Account Menu"

  Scenario: Configure visibility conditions for a block
    Given I am logged in as a user with the "administrator" role
    When I create a block of type "User account menu" with:
      | label         | [TEST] User Account Menu  |
      | label_display | 1                         |
      | region        | content             |
      | status        | 1                         |
    And I configure the visibility condition "request_path" for the block "[TEST] User Account Menu" with:
      | pages | /user/* |
    Then the block "[TEST] User Account Menu" should have the visibility condition "request_path"
    When I visit "/"
    Then I should not see "[TEST] User Account Menu"
    When I visit "/user"
    Then I should see "[TEST] User Account Menu"
    When I remove the visibility condition "request_path" from the block "[TEST] User Account Menu"
    Then the block "[TEST] User Account Menu" should not have the visibility condition "request_path"
    And the cache has been cleared
    When I visit "/"
    Then I should see "[TEST] User Account Menu"

  Scenario: Move block from one region to another
    Given I am logged in as a user with the "administrator" role
    When I create a block of type "User account menu" with:
      | label         | [TEST] User Account Menu  |
      | label_display | 1                         |
      | region        | content             |
      | status        | 1                         |
    Then the block "[TEST] User Account Menu" should exist in the "content" region
    When I configure the block "[TEST] User Account Menu" with:
      | region        | header            |
    Then the block "[TEST] User Account Menu" should exist in the "header" region
    And the block "[TEST] User Account Menu" should not exist in the "content" region
