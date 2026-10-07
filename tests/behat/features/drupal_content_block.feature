Feature: Check that ContentBlockTrait works
  As Behat Steps library developer
  I want to provide tools to manage content blocks
  So that users can test block content and placement functionality

  Scenario: Verify content block type exists
    Given the following "basic" content blocks exist:
      | info                        | body                                      | status |
      | [TEST] Verify Block Content | Testing ContentBlockTrait's functionality | 1      |
    Then the content block type "basic" should exist
    When I log in as a user with the role "administrator"
    And I visit "/admin/content/block"
    Then I should see "[TEST] Verify Block Content"

  @test-trait:Drupal\ContentBlockTrait
  Scenario: Verify content block type validation fails for non-existent type
    Given some behat configuration
    And scenario steps:
      """
      Then the content block type "non_existent_type" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The content block type "non_existent_type" does not exist.
      """

  Scenario: Create, manage, and verify content block entities
    When I log in as a user with the role "administrator"
    And the content block type "basic" should exist
    And the following "basic" content blocks do not exist:
      | [TEST] Content Block 1 |
      | [TEST] Content Block 2 |
    When the following "basic" content blocks exist:
      | info                   | status | body                  |
      | [TEST] Content Block 1 | 1      | [TEST] Body content 1 |
      | [TEST] Content Block 2 | 1      | [TEST] Body content 2 |
    And I go to "admin/content/block"
    Then I should see "[TEST] Content Block 1"
    And I should see "[TEST] Content Block 2"
    When I visit the "basic" content block edit page with the description "[TEST] Content Block 1"
    Then the "Block description" field should contain "[TEST] Content Block 1"
    And the "Body" field should contain "[TEST] Body content 1"

  Scenario: Verify "Given the following content blocks do not exist" does not fail for non-existent content blocks
    When I log in as a user with the role "administrator"
    And the content block type "basic" should exist
    When the following "basic" content blocks do not exist:
      | [TEST] Non-existent Block |
    Then I should not see "[TEST] Non-existent Block"

  @test-skipped
  Scenario: Edit a content block
    When I log in as a user with the role "administrator"
    And the content block type "basic" should exist
    And the following "basic" content blocks do not exist:
      | [TEST] Editable Block |
    When the following "basic" content blocks exist:
      | info                  | status | body                |
      | [TEST] Editable Block | 1      | Original block body |
    And I visit the "basic" content block edit page with the description "[TEST] Editable Block"
    And I fill in "Body" with "Updated block body content"
    And I press "Save"
    Then the success message "Basic block [TEST] Editable Block has been updated." should exist

  @test-trait:Drupal\ContentBlockTrait
  Scenario: Assert visiting the edit page of a non-existent content block fails
    Given some behat configuration
    And scenario steps:
      """
      When I log in as a user with the role "administrator"
      When I visit the "basic" content block edit page with the description "Non-existent Content Block"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find "basic" content block with the description "Non-existent Content Block"
      """

  Scenario: Create a new basic content block and place it in a region
    Given the following "basic" content blocks exist:
      | info               | body                | status |
      | [TEST] Basic Block | [TEST] Body content | 1      |
    And the instance of the block "[TEST] Basic Block" exists with the following configuration:
      | label         | [TEST] Content Block |
      | label_display | 1                    |
      | region        | content              |
      | status        | 1                    |
    Then the block "[TEST] Content Block" should exist
    And the block "[TEST] Content Block" in the region "content" should exist
    When I visit "/"
    Then I should see "[TEST] Content Block"
    And I should see "[TEST] Body content"

  @test-trait:Drupal\BlockTrait
  Scenario: Assert "the instance of block exists with the following configuration" fails for non-existent block
    Given some behat configuration
    And scenario steps:
      """
      Given the instance of the block "Non-existent Block" exists with the following configuration:
        | label         | [TEST] Content Block |
        | label_display | 1                    |
        | region        | content              |
        | status        | 1                    |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Could not create block with admin label "Non-existent Block"
      """

  @test-skipped
  Scenario: Edit content block with configuration
    Given the following "basic" content blocks exist:
      | info                  | body                  | status |
      | [TEST] Editable Block | Initial block content | 1      |
    And I log in as a user with the role "administrator"
    When I visit the "basic" content block edit page with the description "[TEST] Editable Block"
    And I fill in "Block description" with "[TEST] Updated Block"
    And I fill in "Body" with "This content has been updated through Behat test"
    And I press "Save"
    Then I should see "Basic block [TEST] Updated Block has been updated."
    Given the instance of the block "[TEST] Updated Block" exists with the following configuration:
      | label         | [TEST] Updated Content Block |
      | label_display | 1                            |
      | region        | content                      |
      | status        | 1                            |
    And I visit "/"
    Then I should see "[TEST] Updated Content Block"
    And I should see "This content has been updated through Behat test"

  Scenario: Remove content block
    When I log in as a user with the role "administrator"
    When the following "basic" content blocks exist:
      | info                   | body                       | status |
      | [TEST] Removable Block | Block that will be removed | 1      |
    And I visit "/admin/content/block"
    Then I should see "[TEST] Removable Block"
    When the following "basic" content blocks do not exist:
      | info                   |
      | [TEST] Removable Block |
    And I visit "/admin/content/block"
    Then I should not see "[TEST] Removable Block"

  Scenario: Create basic content block, then delete it, and verify it no longer exists
    When I log in as a user with the role "administrator"
    And the content block type "basic" should exist
    And the following "basic" content blocks exist:
      | info                   | status | body                       |
      | [TEST] Temporary Block | 1      | This block will be deleted |
    When I go to "admin/content/block"
    Then I should see "[TEST] Temporary Block"
    When the following "basic" content blocks do not exist:
      | [TEST] Temporary Block |
    And I go to "admin/content/block"
    Then I should not see "[TEST] Temporary Block"

  Scenario: Assert that deleting a non-existent content block doesn't fail
    When I log in as a user with the role "administrator"
    And the content block type "basic" should exist
    When the following "basic" content blocks do not exist:
      | [TEST] Content Block That Doesn't Exist |
    Then I should not see "[TEST] Content Block That Doesn't Exist"

  @behat-steps-entity-cleanup-skip:block_content
  Scenario: Content blocks are not automatically cleaned up when skip tag is used
    Given the following "basic" content blocks exist:
      | info                      | body              | status |
      | [TEST] Skip Cleanup Block | Skip cleanup test | 1      |
    And I log in as a user with the role "administrator"
    When I visit "/admin/content/block"
    Then I should see "[TEST] Skip Cleanup Block"
    When the following "basic" content blocks do not exist:
      | [TEST] Skip Cleanup Block |

  Scenario: Create single content block with vertical field format
    When I log in as a user with the role "administrator"
    And the following basic content blocks with fields exist:
      | info   | [TEST] Vertical Block        |
      | body   | Created with vertical format |
      | status | 1                            |
    When I go to "admin/content/block"
    Then I should see "[TEST] Vertical Block"
    When I visit the "basic" content block edit page with the description "[TEST] Vertical Block"
    Then the "Body" field should contain "Created with vertical format"

  Scenario: Create multiple content blocks with vertical field format
    When I log in as a user with the role "administrator"
    And the following basic content blocks with fields exist:
      | info   | [TEST] Vertical Block 1 | [TEST] Vertical Block 2 | [TEST] Vertical Block 3 |
      | body   | First vertical block    | Second vertical block   | Third vertical block    |
      | status | 1                       | 1                       | 1                       |
    When I go to "admin/content/block"
    Then I should see "[TEST] Vertical Block 1"
    And I should see "[TEST] Vertical Block 2"
    And I should see "[TEST] Vertical Block 3"
