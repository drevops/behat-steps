@api
Feature: Check that BlockTrait works
  As Behat Steps library developer
  I want to provide tools to manage blocks programmatically
  So that users can test block placement and visibility functionality

  Scenario: Configure and place a system block in a region
    Given I am logged in as a user with the "administrator" role
    When I create a block of type "User account menu" with:
      | label         | [TEST] User Account Menu |
      | label_display | 1                        |
      | region        | content                  |
      | status        | 1                        |
    Then I should see the block with label "[TEST] User Account Menu"
    And I should see the block with label "[TEST] User Account Menu" in the region "content"
    When I visit "/"
    Then I should see "[TEST] User Account Menu"

  Scenario: Verify block can be enabled and disabled
    Given I am logged in as a user with the "administrator" role
    When I create a block of type "User account menu" with:
      | label         | [TEST] User Account Menu |
      | label_display | 1                        |
      | region        | content                  |
      | status        | 1                        |
    Then the block with label "[TEST] User Account Menu" should be enabled
    When I disable the block with label "[TEST] User Account Menu"
    Then the block with label "[TEST] User Account Menu" should be disabled
    When I visit "/"
    Then I should not see "[TEST] User Account Menu"
    When I enable the block with label "[TEST] User Account Menu"
    Then the block with label "[TEST] User Account Menu" should be enabled
    When the cache has been cleared
    And I visit "/"
    Then I should see "[TEST] User Account Menu"

  Scenario: Configure visibility conditions for a block
    Given I am logged in as a user with the "administrator" role
    When I create a block of type "User account menu" with:
      | label         | [TEST] User Account Menu |
      | label_display | 1                        |
      | region        | content                  |
      | status        | 1                        |
    And I configure a visibility condition "request_path" for the block with label "[TEST] User Account Menu"
      | pages | /user/* |
    Then the block with label "[TEST] User Account Menu" should have the visibility condition "request_path"
    When I visit "/"
    Then I should not see "[TEST] User Account Menu"
    When I visit "/user"
    Then I should see "[TEST] User Account Menu"
    When I remove the visibility condition "request_path" from the block with label "[TEST] User Account Menu"
    Then the block with label "[TEST] User Account Menu" should not have the visibility condition "request_path"
    When the cache has been cleared
    And I visit "/"
    Then I should see "[TEST] User Account Menu"

  Scenario: Move block from one region to another
    Given I am logged in as a user with the "administrator" role
    When I create a block of type "User account menu" with:
      | label         | [TEST] User Account Menu |
      | label_display | 1                        |
      | region        | content                  |
      | status        | 1                        |
    Then I should see the block with label "[TEST] User Account Menu" in the region "content"
    When I configure the block with the label "[TEST] User Account Menu" with:
      | region | header |
    Then I should see the block with label "[TEST] User Account Menu" in the region "header"
    And I should not see the block with label "[TEST] User Account Menu" in the region "content"









#
#  @api
#  Feature: Check that BlockTrait works
#
#  Scenario: Configure and place a system block in a region
#    Given I am logged in as a user with the "administrator" role
#    When I create a block of type "User account menu" with:
#      | label         | [TEST] User Account Menu  |
#      | label_display | 1                         |
#      | region        | content             |
#      | status        | 1                         |
#    Then the block "[TEST] User Account Menu" should exist
#    And the block "[TEST] User Account Menu" should exist in the region "content"
#    When I visit "/"
#    Then I should see "[TEST] User Account Menu"
#
#  Scenario: Verify block can be enabled and disabled
#    Given I am logged in as a user with the "administrator" role
#    When I create a block of type "User account menu" with:
#      | label         | [TEST] User Account Menu  |
#      | label_display | 1                         |
#      | region        | content             |
#      | status        | 1                         |
#    Then the block "[TEST] User Account Menu" should be enabled
#    When I disable the block "[TEST] User Account Menu"
#    Then the block "[TEST] User Account Menu" should be disabled
#    When I visit "/"
#    Then I should not see "[TEST] User Account Menu"
#    When I enable the block "[TEST] User Account Menu"
#    Then the block "[TEST] User Account Menu" should be enabled
#    And the cache has been cleared
#    When I visit "/"
#    Then I should see "[TEST] User Account Menu"
#
#  Scenario: Configure visibility conditions for a block
#    Given I am logged in as a user with the "administrator" role
#    When I create a block of type "User account menu" with:
#      | label         | [TEST] User Account Menu  |
#      | label_display | 1                         |
#      | region        | content             |
#      | status        | 1                         |
#    And I configure the visibility condition "request_path" for the block "[TEST] User Account Menu" with:
#      | pages | /user/* |
#    Then the block "[TEST] User Account Menu" should have the visibility condition "request_path"
#    When I visit "/"
#    Then I should not see "[TEST] User Account Menu"
#    When I visit "/user"
#    Then I should see "[TEST] User Account Menu"
#    When I remove the visibility condition "request_path" from the block "[TEST] User Account Menu"
#    Then the block "[TEST] User Account Menu" should not have the visibility condition "request_path"
#    And the cache has been cleared
#    When I visit "/"
#    Then I should see "[TEST] User Account Menu"
#
#  Scenario: Move block from one region to another
#    Given I am logged in as a user with the "administrator" role
#    When I create a block of type "User account menu" with:
#      | label         | [TEST] User Account Menu  |
#      | label_display | 1                         |
#      | region        | content             |
#      | status        | 1                         |
#    Then the block "[TEST] User Account Menu" should exist in the region "content"
#    When I configure the block "[TEST] User Account Menu" with:
#      | region        | header            |
#    Then the block "[TEST] User Account Menu" should exist in the region "header"
#    And the block "[TEST] User Account Menu" should not exist in the "content" region
