Feature: Check that ContentBlockTrait works
  As Behat Steps library developer
  I want to provide tools to manage content blocks
  So that users can test block content and placement functionality

  @api
  Scenario: Verify content block type exists
    Given the following "basic" content blocks exist:
      | info                        | body                                      | status |
      | [TEST] Verify Block Content | Testing ContentBlockTrait's functionality | 1      |
    Then the content block type "basic" should exist
    When I am logged in as a user with the "administrator" role
    And I visit "/admin/content/block"
    Then I should see "[TEST] Verify Block Content"

  @api @trait:Drupal\ContentBlockTrait
  Scenario: Verify content block type validation fails for non-existent type
    Given some behat configuration
    And scenario steps:
      """
      Then the content block type "non_existent_type" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      "non_existent_type" content block type does not exist
      """

  @api
  Scenario: Create, manage, and verify content block entities
    Given I am logged in as a user with the "administrator" role
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
    When I edit the "basic" content block with the description "[TEST] Content Block 1"
    Then the "Block description" field should contain "[TEST] Content Block 1"
    And the "Body" field should contain "[TEST] Body content 1"

  @api
  Scenario: Verify "Given the following content blocks do not exist" does not fail for non-existent content blocks
    Given I am logged in as a user with the "administrator" role
    And the content block type "basic" should exist
    When the following "basic" content blocks do not exist:
      | [TEST] Non-existent Block |
    Then I should not see the text "[TEST] Non-existent Block"

  @api
  Scenario: Edit a content block
    Given I am logged in as a user with the "administrator" role
    And the content block type "basic" should exist
    And the following "basic" content blocks do not exist:
      | [TEST] Editable Block |
    When the following "basic" content blocks exist:
      | info                  | status | body                |
      | [TEST] Editable Block | 1      | Original block body |
    And I edit the "basic" content block with the description "[TEST] Editable Block"
    And I fill in "Body" with "Updated block body content"
    And I press "Save"
    Then I should see the success message "Basic block [TEST] Editable Block has been updated."

  @api @trait:Drupal\ContentBlockTrait
  Scenario: Assert editing a non-existent content block fails
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I edit the "basic" content block with the description "Non-existent Content Block"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find "basic" content block with the description "Non-existent Content Block"
      """

  @api
  Scenario: Create a new basic content block and place it in a region
    Given the following "basic" content blocks exist:
      | info               | body                | status |
      | [TEST] Basic Block | [TEST] Body content | 1      |
    And the instance of "[TEST] Basic Block" block exists with the following configuration:
      | label         | [TEST] Content Block |
      | label_display | 1                    |
      | region        | content              |
      | status        | 1                    |
    Then the block "[TEST] Content Block" should exist
    And the block "[TEST] Content Block" should exist in the "content" region
    When I visit "/"
    Then I should see "[TEST] Content Block"
    And I should see "[TEST] Body content"

  @api @trait:Drupal\BlockTrait
  Scenario: Assert "the instance of block exists with the following configuration" fails for non-existent block
    Given some behat configuration
    And scenario steps:
      """
      Given the instance of "Non-existent Block" block exists with the following configuration:
        | label         | [TEST] Content Block |
        | label_display | 1                    |
        | region        | content              |
        | status        | 1                    |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Could not create block with admin label "Non-existent Block"
      """

  @api
  Scenario: Edit content block with configuration
    Given the following "basic" content blocks exist:
      | info                  | body                  | status |
      | [TEST] Editable Block | Initial block content | 1      |
    And I am logged in as a user with the "administrator" role
    When I edit the "basic" content block with the description "[TEST] Editable Block"
    And I fill in "Block description" with "[TEST] Updated Block"
    And I fill in "Body" with "This content has been updated through Behat test"
    And I press "Save"
    Then I should see "Basic block [TEST] Updated Block has been updated."
    Given the instance of "[TEST] Updated Block" block exists with the following configuration:
      | label         | [TEST] Updated Content Block |
      | label_display | 1                            |
      | region        | content                      |
      | status        | 1                            |
    And I visit "/"
    Then I should see "[TEST] Updated Content Block"
    And I should see "This content has been updated through Behat test"

  @api
  Scenario: Remove content block
    Given I am logged in as a user with the "administrator" role
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

  @api
  Scenario: Create basic content block, then delete it, and verify it no longer exists
    Given I am logged in as a user with the "administrator" role
    And the content block type "basic" should exist
    And the following "basic" content blocks exist:
      | info                   | status | body                       |
      | [TEST] Temporary Block | 1      | This block will be deleted |
    When I go to "admin/content/block"
    Then I should see the text "[TEST] Temporary Block"
    When the following "basic" content blocks do not exist:
      | [TEST] Temporary Block |
    And I go to "admin/content/block"
    Then I should not see the text "[TEST] Temporary Block"

  @api @trait:Drupal\ContentBlockTrait
  Scenario: Assert that deleting a non-existent content block doesn't fail
    Given I am logged in as a user with the "administrator" role
    And the content block type "basic" should exist
    When the following "basic" content blocks do not exist:
      | [TEST] Content Block That Doesn't Exist |
    Then I should not see the text "[TEST] Content Block That Doesn't Exist"
