@api
Feature: Check that BlockContentTrait works

  Scenario: Create, manage, and verify custom block content entities
    Given I am logged in as a user with the "administrator" role
    And "basic" block_content type exists
    And no "basic" block_content:
      | [TEST] Custom Block 1 |
      | [TEST] Custom Block 2 |
    When "basic" block_content:
      | info                  | status | body                  |
      | [TEST] Custom Block 1 | 1      | [TEST] Body content 1  |
      | [TEST] Custom Block 2 | 1      | [TEST] Body content 2 |
    And I go to "admin/content/block"
    Then I should see "[TEST] Custom Block 1"
    And I should see "[TEST] Custom Block 2"
    When I edit "basic" block_content_type with description "[TEST] Custom Block 1"
    Then the "Block description" field should contain "[TEST] Custom Block 1"
    And the "Body" field should contain "[TEST] Body content 1"

  Scenario: Edit a custom block content
    Given I am logged in as a user with the "administrator" role
    And "basic" block_content type exists
    And no "basic" block_content:
      | [TEST] Editable Block |
    And "basic" block_content:
      | info                  | status | body                  |
      | [TEST] Editable Block | 1      | Original block body   |
    When I edit "basic" block_content_type with description "[TEST] Editable Block"
    And I fill in "Body" with "Updated block body content"
    And I press "Save"
    Then I should see the success message "Basic block [TEST] Editable Block has been updated."

  Scenario: Create a new basic block content and place it in a region
    Given I am logged in as a user with the "administrator" role
    And "basic" block_content:
      | info              | body                                                   | status |
      | [TEST] Basic Block | [TEST] Body content | 1      |
    When I create a block of type "[TEST] Basic Block" with:
      | label         | [TEST] Content Block    |
      | label_display | 1                       |
      | region        | content                 |
      | status        | 1                       |
    Then block with label "[TEST] Content Block" should exist
    And block with label "[TEST] Content Block" should exist in the region "content"
    When I visit "/"
    Then I should see "[TEST] Content Block"
    And I should see "[TEST] Body content"

  Scenario: Verify block content type exists
    Given I am logged in as a user with the "administrator" role
    And "basic" block_content type exists
    And "basic" block_content:
      | info                   | body                                      | status |
      | [TEST] Verify Block Content | Testing BlockContentTrait's functionality | 1      |
    When I visit "/admin/content/block"
    Then I should see "[TEST] Verify Block Content"

  Scenario: Edit block content
    Given "basic" block_content:
      | info                | body                       | status |
      | [TEST] Editable Block | Initial block content      | 1      |
    And I am logged in as a user with the "administrator" role
    When I edit "basic" block_content_type with description "[TEST] Editable Block"
    And I fill in "Block description" with "[TEST] Updated Block"
    And I fill in "Body" with "This content has been updated through Behat test"
    And I press "Save"
    Then I should see "Basic block [TEST] Updated Block has been updated."
    When I create a block of type "[TEST] Updated Block" with:
      | label         | [TEST] Updated Content Block |
      | label_display | 1                           |
      | region        | content                     |
      | status        | 1                           |
    And I visit "/"
    Then I should see "[TEST] Updated Content Block"
    And I should see "This content has been updated through Behat test"

  Scenario: Remove block content
    Given I am logged in as a user with the "administrator" role
    And "basic" block_content:
      | info                  | body                       | status |
      | [TEST] Removable Block | Block that will be removed | 1      |
    And I visit "/admin/content/block"
    Then I should see "[TEST] Removable Block"
    And no "basic" block_content:
      | info |
      | [TEST] Removable Block |
    When I visit "/admin/content/block"
    Then I should not see "[TEST] Removable Block"
