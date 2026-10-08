Feature: Check that BlockTrait works
  As Behat Steps library developer
  I want to provide tools to manage blocks programmatically
  So that users can test block placement and visibility functionality

  Background:
    Given the block "[TEST] User Account Menu" does not exist

  Scenario: Create a block instance, disable and enable it
    Given the instance of the block "User account menu" exists with the following configuration:
      | label         | [TEST] User Account Menu |
      | label_display | 1                        |
      | region        | content                  |
      | status        | 1                        |
    Then the block "[TEST] User Account Menu" should exist
    Then the block "Other random block" should not exist
    And the block "[TEST] User Account Menu" in the region "content" should exist
    When I visit "/"
    Then I should see "[TEST] User Account Menu"

    Given the block "[TEST] User Account Menu" is disabled
    And the cache is empty
    When I visit "/"
    Then I should not see "[TEST] User Account Menu"

    Given the block "[TEST] User Account Menu" is enabled
    And the cache is empty
    When I visit "/"
    Then I should see "[TEST] User Account Menu"

    # Run twice to make sure that no exceptions are thrown on missing block.
    Given the block "[TEST] User Account Menu" does not exist
    And the block "[TEST] User Account Menu" does not exist

  Scenario: Assert that the most recently created block wins when two share a label
    Given the block "[TEST] Duplicate Label" does not exist
    And the instance of the block "User account menu" exists with the following configuration:
      | label         | [TEST] Duplicate Label |
      | label_display | 1                      |
      | region        | content                |
      | status        | 1                      |
    And the instance of the block "User account menu" exists with the following configuration:
      | label         | [TEST] Duplicate Label |
      | label_display | 1                      |
      | region        | footer_top             |
      | status        | 1                      |
    Then the block "[TEST] Duplicate Label" in the region "footer_top" should exist
    And the block "[TEST] Duplicate Label" in the region "content" should not exist

  Scenario: Assert that the most recently placed block wins when blocks from 2 plugins share a label
    Given the block "[TEST] Duplicate Label" does not exist
    And the instance of the block "User account menu" exists with the following configuration:
      | label         | [TEST] Duplicate Label |
      | label_display | 1                      |
      | region        | content                |
      | status        | 1                      |
    And the instance of the block "Powered by Drupal" exists with the following configuration:
      | label         | [TEST] Duplicate Label |
      | label_display | 1                      |
      | region        | footer_top             |
      | status        | 1                      |
    Then the block "[TEST] Duplicate Label" in the region "footer_top" should exist
    And the block "[TEST] Duplicate Label" in the region "content" should not exist
    Given the block "[TEST] Duplicate Label" does not exist
    Then the block "[TEST] Duplicate Label" should not exist

  @test-trait:Drupal\BlockTrait
  Scenario: Assert "block should exist" fails for non-existing block
    Given some behat configuration
    And scenario steps:
      """
      Then the block "Non-existent Block Label" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The block "Non-existent Block Label" does not exist.
      """

  @test-trait:Drupal\BlockTrait
  Scenario: Assert "block should not exist" fails for existing block
    Given some behat configuration
    And scenario steps:
      """
      Given the instance of the block "User account menu" exists with the following configuration:
        | label         | [TEST] User Account Menu |
        | label_display | 1                        |
        | region        | content                  |
        | status        | 1                        |
      Then the block "[TEST] User Account Menu" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The block "[TEST] User Account Menu" exists, but it should not.
      """

  @test-trait:Drupal\BlockTrait
  Scenario: Assert "block should exist in region" fails for non-existing block
    Given some behat configuration
    And scenario steps:
      """
      Then the block "Non-existent Block Label" in the region "content" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The block "Non-existent Block Label" does not exist.
      """

  @test-trait:Drupal\BlockTrait
  Scenario: Assert "block should exist in region" fails for block in wrong region
    Given some behat configuration
    And scenario steps:
      """
      Given the instance of the block "User account menu" exists with the following configuration:
        | label         | [TEST] User Account Menu |
        | label_display | 1                        |
        | region        | content                  |
        | status        | 1                        |
      Then the block "[TEST] User Account Menu" in the region "sidebar" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The block "[TEST] User Account Menu" is in the region "content", but it should be in the region "sidebar"
      """

  @test-trait:Drupal\BlockTrait
  Scenario: Assert "block should not exist in region" fails for non-existing block
    Given some behat configuration
    And scenario steps:
      """
      Then the block "Non-existent Block Label" in the region "content" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The block "Non-existent Block Label" does not exist.
      """

  @test-trait:Drupal\BlockTrait
  Scenario: Assert "block should not exist in region" fails for block in the specified region
    Given some behat configuration
    And scenario steps:
      """
      Given the instance of the block "User account menu" exists with the following configuration:
        | label         | [TEST] User Account Menu |
        | label_display | 1                        |
        | region        | content                  |
        | status        | 1                        |
      Then the block "[TEST] User Account Menu" in the region "content" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The block "[TEST] User Account Menu" is in the region "content", but it should not be
      """

  @test-trait:Drupal\BlockTrait
  Scenario: Assert "Given the block is enabled" fails for non-existing block
    Given some behat configuration
    And scenario steps:
      """
      Given the block "Non-existent Block Label" is enabled
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The block "Non-existent Block Label" does not exist.
      """

  @test-trait:Drupal\BlockTrait
  Scenario: Assert "Given the block is disabled" fails for non-existing block
    Given some behat configuration
    And scenario steps:
      """
      Given the block "Non-existent Block Label" is disabled
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The block "Non-existent Block Label" does not exist.
      """

  @test-trait:Drupal\BlockTrait
  Scenario: Assert "Given the block has configuration" fails for non-existing block
    Given some behat configuration
    And scenario steps:
      """
      Given the block "Non-existent Block Label" has the following configuration:
        | region | content |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The block "Non-existent Block Label" does not exist.
      """

  Scenario: Configure visibility conditions for a block
    Given the instance of the block "User account menu" exists with the following configuration:
      | label         | [TEST] User Account Menu |
      | label_display | 1                        |
      | region        | content                  |
      | status        | 1                        |

    Given the block "[TEST] User Account Menu" has the condition "request_path" with the following configuration:
      | pages | /user/* |

    When I visit "/"
    Then I should not see "[TEST] User Account Menu"

    When I visit "/user"
    Then I should see "[TEST] User Account Menu"

    Given the block "[TEST] User Account Menu" has the condition "request_path" removed
    And the cache is empty
    When I visit "/"
    Then I should see "[TEST] User Account Menu"

  @test-trait:Drupal\BlockTrait
  Scenario: Assert "block has condition configuration" fails for non-existing block
    Given some behat configuration
    And scenario steps:
      """
      Given the block "Non-existent Block Label" has the condition "request_path" with the following configuration:
        | pages | /user/* |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The block "Non-existent Block Label" does not exist.
      """

  @test-trait:Drupal\BlockTrait
  Scenario: Assert "block has condition removed" fails for non-existing block
    Given some behat configuration
    And scenario steps:
      """
      Given the block "Non-existent Block Label" has the condition "request_path" removed
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The block "Non-existent Block Label" does not exist.
      """

  Scenario: Move block from one region to another
    Given the instance of the block "User account menu" exists with the following configuration:
      | label         | [TEST] User Account Menu |
      | label_display | 1                        |
      | region        | content                  |
      | status        | 1                        |
    Then the block "[TEST] User Account Menu" in the region "content" should exist

    Given the block "[TEST] User Account Menu" has the following configuration:
      | region | header |
    Then the block "[TEST] User Account Menu" in the region "header" should exist
    And the block "[TEST] User Account Menu" in the region "content" should not exist

  @test-trait:Drupal\BlockTrait
  Scenario: Assert "block instance exists" fails for non-existing block type
    Given some behat configuration
    And scenario steps:
      """
      Given the instance of the block "Non-existent Block Type" exists with the following configuration:
        | label         | [TEST] User Account Menu |
        | label_display | 1                        |
        | region        | content                  |
        | status        | 1                        |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Could not create block with admin label "Non-existent Block Type"
      """

  @behat-steps-entity-cleanup-skip:block
  Scenario: Blocks are not automatically cleaned up when skip tag is used
    Given the instance of the block "User account menu" exists with the following configuration:
      | label         | [TEST] Skip Cleanup Block |
      | label_display | 1                         |
      | region        | content                   |
      | status        | 1                         |
    Then the block "[TEST] Skip Cleanup Block" should exist
    Given the block "[TEST] Skip Cleanup Block" does not exist
