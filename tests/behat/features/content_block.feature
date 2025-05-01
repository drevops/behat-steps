@api
Feature: Check that ContentBlockTrait works

  Scenario: Create, manage, and verify content block entities
    Given I am logged in as a user with the "administrator" role
    Then the content block type "basic" should exist
    And the following "basic" content blocks do not exist:
      | [TEST] Content Block 1 |
      | [TEST] Content Block 2 |
    And the following "basic" content blocks exist:
      | info                   | status | body                  |
      | [TEST] Content Block 1 | 1      | [TEST] Body content 1 |
      | [TEST] Content Block 2 | 1      | [TEST] Body content 2 |
    And I go to "admin/content/block"
    Then I should see "[TEST] Content Block 1"
    And I should see "[TEST] Content Block 2"
    When I edit the "basic" content block with the description "[TEST] Content Block 1"
    Then the "Block description" field should contain "[TEST] Content Block 1"
    And the "Body" field should contain "[TEST] Body content 1"

  Scenario: Edit a content block
    Given I am logged in as a user with the "administrator" role
    And the content block type "basic" should exist
    And the following "basic" content blocks do not exist:
      | [TEST] Editable Block |
    And the following "basic" content blocks exist:
      | info                  | status | body                |
      | [TEST] Editable Block | 1      | Original block body |
    When I edit the "basic" content block with the description "[TEST] Editable Block"
    And I fill in "Body" with "Updated block body content"
    And I press "Save"
    Then I should see the success message "Basic block [TEST] Editable Block has been updated."

  Scenario: Create a new basic content block and place it in a region
    Given I am logged in as a user with the "administrator" role
    And the following "basic" content blocks exist:
      | info               | body                | status |
      | [TEST] Basic Block | [TEST] Body content | 1      |
    When I create a block of type "[TEST] Basic Block" with:
      | label         | [TEST] Content Block |
      | label_display | 1                    |
      | region        | content              |
      | status        | 1                    |
    Then block with label "[TEST] Content Block" should exist
    And block with label "[TEST] Content Block" should exist in the region "content"
    When I visit "/"
    Then I should see "[TEST] Content Block"
    And I should see "[TEST] Body content"

  Scenario: Verify content block type exists
    Given I am logged in as a user with the "administrator" role
    Then the content block type "basic" should exist
    And the following "basic" content blocks exist:
      | info                        | body                                      | status |
      | [TEST] Verify Block Content | Testing ContentBlockTrait's functionality | 1      |
    When I visit "/admin/content/block"
    Then I should see "[TEST] Verify Block Content"

  Scenario: Edit content block
    Given the following "basic" content blocks exist:
      | info                  | body                  | status |
      | [TEST] Editable Block | Initial block content | 1      |
    And I am logged in as a user with the "administrator" role
    When I edit the "basic" content block with the description "[TEST] Editable Block"
    And I fill in "Block description" with "[TEST] Updated Block"
    And I fill in "Body" with "This content has been updated through Behat test"
    And I press "Save"
    Then I should see "Basic block [TEST] Updated Block has been updated."
    When I create a block of type "[TEST] Updated Block" with:
      | label         | [TEST] Updated Content Block |
      | label_display | 1                            |
      | region        | content                      |
      | status        | 1                            |
    And I visit "/"
    Then I should see "[TEST] Updated Content Block"
    And I should see "This content has been updated through Behat test"

  Scenario: Remove content block
    Given I am logged in as a user with the "administrator" role
    And the following "basic" content blocks exist:
      | info                   | body                       | status |
      | [TEST] Removable Block | Block that will be removed | 1      |
    And I visit "/admin/content/block"
    Then I should see "[TEST] Removable Block"
    And the following "basic" content blocks do not exist:
      | info                   |
      | [TEST] Removable Block |
    When I visit "/admin/content/block"
    Then I should not see "[TEST] Removable Block"
