@api @wip
Feature: Check that BlockTrait works

  Scenario: Configure and place a system block in a region
    Given I am logged in as a user with the "administrator" role
    When I create a block of type "User account menu" with:
      | label         | [TEST] User Account Menu  |
      | label_display | 1                         |
      | region        | content             |
      | status        | 1                         |
    Then block with label "[TEST] User Account Menu" should exist
    And block with label "[TEST] User Account Menu" should exist in the region "content"
    When I visit "/"
    Then I should see "[TEST] User Account Menu"

  Scenario: Verify block can be enabled and disabled
    Given I am logged in as a user with the "administrator" role
    When I create a block of type "User account menu" with:
      | label         | [TEST] User Account Menu  |
      | label_display | 1                         |
      | region        | content             |
      | status        | 1                         |
    Then the block with label "[TEST] User Account Menu" is enabled
    When I disable the block with label "[TEST] User Account Menu"
    Then the block with label "[TEST] User Account Menu" is disabled
    When I visit "/"
    Then I should not see "[TEST] User Account Menu"
    When I enable the block with label "[TEST] User Account Menu"
    Then the block with label "[TEST] User Account Menu" is enabled
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
    And I configure a visibility condition "request_path" for the block with label "[TEST] User Account Menu"
      | pages | /user/* |
    Then the block with label "[TEST] User Account Menu" should have the visibility condition "request_path"
    When I visit "/"
    Then I should not see "[TEST] User Account Menu"
    When I visit "/user"
    Then I should see "[TEST] User Account Menu"
    When I remove the visibility condition "request_path" from the block with label "[TEST] User Account Menu"
    Then the block with label "[TEST] User Account Menu" should not have the visibility condition "request_path"
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
    Then block with label "[TEST] User Account Menu" should exist in the region "content"
    When I configure the block with the label "[TEST] User Account Menu" with:
      | region        | header            |
    Then block with label "[TEST] User Account Menu" should exist in the region "header"
    And block with label "[TEST] User Account Menu" should not exist in the region "content"
