Feature: Check that BlockTrait works
  As Behat Steps library developer
  I want to provide tools to manage blocks programmatically
  So that users can test block placement and visibility functionality

  Background:
    Given the block "[TEST] User Account Menu" does not exist

  @api
  Scenario: Create a block instance, disable and enable it
    Given the instance of "User account menu" block exists with the following configuration:
      | label         | [TEST] User Account Menu |
      | label_display | 1                        |
      | region        | content                  |
      | status        | 1                        |
    Then the block "[TEST] User Account Menu" should exist
    Then the block "Other random block" should not exist
    And the block "[TEST] User Account Menu" should exist in the "content" region
    When I visit "/"
    Then I should see "[TEST] User Account Menu"

    Given the block "[TEST] User Account Menu" is disabled
    And the cache has been cleared
    When I visit "/"
    Then I should not see "[TEST] User Account Menu"

    Given the block "[TEST] User Account Menu" is enabled
    And the cache has been cleared
    When I visit "/"
    Then I should see "[TEST] User Account Menu"

    # Run twice to make sure that no exceptions are thrown on missing block.
    Given the block "[TEST] User Account Menu" does not exist
    And the block "[TEST] User Account Menu" does not exist

  @trait:Drupal\BlockTrait @api
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

  @trait:Drupal\BlockTrait @api
  Scenario: Assert "block should not exist" fails for existing block
    Given some behat configuration
    And scenario steps:
      """
      Given the instance of "User account menu" block exists with the following configuration:
        | label         | [TEST] User Account Menu |
        | label_display | 1                        |
        | region        | content                  |
        | status        | 1                        |
      Then the block "[TEST] User Account Menu" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The block "[TEST] User Account Menu" exists but should not.
      """

  @trait:Drupal\BlockTrait @api
  Scenario: Assert "block should exist in region" fails for non-existing block
    Given some behat configuration
    And scenario steps:
      """
      Then the block "Non-existent Block Label" should exist in the "content" region
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The block "Non-existent Block Label" does not exist.
      """

  @trait:Drupal\BlockTrait @api
  Scenario: Assert "block should exist in region" fails for block in wrong region
    Given some behat configuration
    And scenario steps:
      """
      Given the instance of "User account menu" block exists with the following configuration:
        | label         | [TEST] User Account Menu |
        | label_display | 1                        |
        | region        | content                  |
        | status        | 1                        |
      Then the block "[TEST] User Account Menu" should exist in the "sidebar" region
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Block "[TEST] User Account Menu" is in region "content" but should be in "sidebar"
      """

  @trait:Drupal\BlockTrait @api
  Scenario: Assert "block should not exist in region" fails for non-existing block
    Given some behat configuration
    And scenario steps:
      """
      Then the block "Non-existent Block Label" should not exist in the "content" region
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The block "Non-existent Block Label" does not exist.
      """

  @trait:Drupal\BlockTrait @api
  Scenario: Assert "block should not exist in region" fails for block in the specified region
    Given some behat configuration
    And scenario steps:
      """
      Given the instance of "User account menu" block exists with the following configuration:
        | label         | [TEST] User Account Menu |
        | label_display | 1                        |
        | region        | content                  |
        | status        | 1                        |
      Then the block "[TEST] User Account Menu" should not exist in the "content" region
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Block "[TEST] User Account Menu" is in region "content" but should not be
      """

  @trait:Drupal\BlockTrait @api
  Scenario: Assert "Given the block is enabled" fails for non-existing block
    Given some behat configuration
    And scenario steps:
      """
      Given the block "Non-existent Block Label" is enabled
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The block "Non-existent Block Label" does not exist.
      """

  @trait:Drupal\BlockTrait @api
  Scenario: Assert "Given the block is disabled" fails for non-existing block
    Given some behat configuration
    And scenario steps:
      """
      Given the block "Non-existent Block Label" is disabled
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The block "Non-existent Block Label" does not exist.
      """

  @trait:Drupal\BlockTrait @api
  Scenario: Assert "Given the block has configuration" fails for non-existing block
    Given some behat configuration
    And scenario steps:
      """
      Given the block "Non-existent Block Label" has the following configuration:
        | region | content |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The block "Non-existent Block Label" does not exist.
      """

  @api
  Scenario: Configure visibility conditions for a block
    Given the instance of "User account menu" block exists with the following configuration:
      | label         | [TEST] User Account Menu |
      | label_display | 1                        |
      | region        | content                  |
      | status        | 1                        |

    Given the block "[TEST] User Account Menu" has the following "request_path" condition configuration:
      | pages | /user/* |

    When I visit "/"
    Then I should not see "[TEST] User Account Menu"

    When I visit "/user"
    Then I should see "[TEST] User Account Menu"

    Given the block "[TEST] User Account Menu" has the "request_path" condition removed
    And the cache has been cleared
    When I visit "/"
    Then I should see "[TEST] User Account Menu"

  @trait:Drupal\BlockTrait @api
  Scenario: Assert "block has condition configuration" fails for non-existing block
    Given some behat configuration
    And scenario steps:
      """
      Given the block "Non-existent Block Label" has the following "request_path" condition configuration:
        | pages | /user/* |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The block "Non-existent Block Label" does not exist.
      """

  @trait:Drupal\BlockTrait @api
  Scenario: Assert "block has condition removed" fails for non-existing block
    Given some behat configuration
    And scenario steps:
      """
      Given the block "Non-existent Block Label" has the "request_path" condition removed
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The block "Non-existent Block Label" does not exist.
      """

  @api
  Scenario: Move block from one region to another
    Given the instance of "User account menu" block exists with the following configuration:
      | label         | [TEST] User Account Menu |
      | label_display | 1                        |
      | region        | content                  |
      | status        | 1                        |
    Then the block "[TEST] User Account Menu" should exist in the "content" region

    Given the block "[TEST] User Account Menu" has the following configuration:
      | region | header |
    Then the block "[TEST] User Account Menu" should exist in the "header" region
    And the block "[TEST] User Account Menu" should not exist in the "content" region

  @trait:Drupal\BlockTrait @api
  Scenario: Assert "block instance exists" fails for non-existing block type
    Given some behat configuration
    And scenario steps:
      """
      Given the instance of "Non-existent Block Type" block exists with the following configuration:
        | label         | [TEST] User Account Menu |
        | label_display | 1                        |
        | region        | content                  |
        | status        | 1                        |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Could not create block with admin label "Non-existent Block Type"
      """
