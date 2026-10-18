@taxonomy
Feature: Check that TaxonomyTrait works
  As Behat Steps library developer
  I want to provide tools to manage taxonomy terms programmatically
  So that users can test taxonomy-related functionality

  Background:
    Given the following "tags" terms exist:
      | name |
      | Tag1 |
      | Tag2 |
      | Tag3 |

  Scenario: Assert "Then the vocabulary :vocabulary with the name :name should exist" works
    Given I am logged in as a user with the role "administrator"
    Then the vocabulary "tags" with the name "Tags" should exist

  @test-trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "Then the vocabulary :vocabulary with the name :name should exist" works with non-existing vocabulary
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the role "administrator"
      Then the vocabulary "nonexisting" with the name "Nonexisting" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The vocabulary "nonexisting" does not exist.
      """

  @test-trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "Then the vocabulary :vocabulary with the name :name should exist" works with existing vocabulary but incorrect name
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the role "administrator"
      Then the vocabulary "tags" with the name "Invalidname" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The vocabulary "tags" exists with a name "Tags", but expected "Invalidname".
      """

  Scenario: Assert "Then the vocabulary :vocabulary should not exist" works
    Given I am logged in as a user with the role "administrator"
    Then the vocabulary "nonexisting" should not exist

  @test-trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "Then the vocabulary :vocabulary should not exist" works with existing vocabulary
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the role "administrator"
      Then the vocabulary "tags" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The vocabulary "tags" exists, but it should not.
      """

  Scenario: Assert "Then the :vocabulary term with the name :name should exist" works
    Given I am logged in as a user with the role "administrator"
    Then the "tags" term with the name "Tag1" should exist

  @test-trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "Then the :vocabulary term with the name :name should exist" works with non-existing vocabulary
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the role "administrator"
      Then the "nonexisting" term with the name "Tag" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The vocabulary "nonexisting" does not exist.
      """

  @test-trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "Then the :vocabulary term with the name :name should exist" works with non-existing term
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the role "administrator"
      Then the "tags" term with the name "Nonexisting" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The taxonomy term "Nonexisting" from the vocabulary "tags" does not exist.
      """

  Scenario: Assert "Then the :vocabulary term with the name :name should not exist" works
    Given I am logged in as a user with the role "administrator"
    Then the "tags" term with the name "Nonexisting" should not exist

  @test-trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "Then the :vocabulary term with the name :name should not exist" works with non-existing vocabulary
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the role "administrator"
      Then the "nonexisting" term with the name "Nonexisting" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The vocabulary "nonexisting" does not exist.
      """

  @test-trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "Then the :vocabulary term with the name :name should not exist" works with an existing term
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the role "administrator"
      Then the "tags" term with the name "Tag1" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The taxonomy term "Tag1" from the vocabulary "tags" exists, but it should not.
      """

  Scenario: Assert "Given the following :vocabulary terms do not exist" works
    Given the following "tags" terms do not exist:
      | Tag1        |
      | Tag2        |
      | Nonexisting |
    Then the "tags" term with the name "Tag1" should not exist
    And the "tags" term with the name "Tag2" should not exist
    And the "tags" term with the name "Nonexisting" should not exist
    And the "tags" term with the name "Tag3" should exist

  @test-trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "Given the following :vocabulary terms do not exist" fails with non-existing vocabulary
    Given some behat configuration
    And scenario steps:
      """
      Given the following "nonexisting" terms do not exist:
        | Tag1        |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The vocabulary "nonexisting" does not exist.
      """

  Scenario: Assert "When I visit the :vocabulary term page with the name :name" works
    Given I am logged in as a user with the role "administrator"
    When I visit the "tags" term page with the name "Tag1"
    Then the response should contain "200"
    And I should see "Tag1"

  Scenario: Assert "When I visit the :vocabulary term page with the name :name" visits the most recently created term when names are duplicated
    Given the following "tags" terms exist:
      | name                  | description                   |
      | [TEST] Duplicate term | [TEST] Older term description |
      | [TEST] Duplicate term | [TEST] Newer term description |
    And I am logged in as a user with the role "administrator"
    When I visit the "tags" term page with the name "[TEST] Duplicate term"
    Then I should see "[TEST] Newer term description"
    And I should not see "[TEST] Older term description"

  @test-trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "When I visit the :vocabulary term page with the name :name" fails with non-existing vocabulary
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the role "administrator"
      When I visit the "nonexisting" term page with the name "Tag1"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The vocabulary "nonexisting" does not exist.
      """

  @test-trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "When I visit the :vocabulary term page with the name :name" fails with non-existing term
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the role "administrator"
      When I visit the "tags" term page with the name "Nonexisting"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find the term "Nonexisting" in the vocabulary "tags".
      """

  Scenario: Assert "When I visit the :vocabulary term edit page with the name :name" works
    Given I am logged in as a user with the role "administrator"
    When I visit the "tags" term edit page with the name "Tag1"
    Then the response should contain "200"
    And the "name[0][value]" field should contain "Tag1"

  @test-trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "When I visit the :vocabulary term edit page with the name :name" fails with non-existing vocabulary
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the role "administrator"
      When I visit the "nonexisting" term edit page with the name "Tag1"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The vocabulary "nonexisting" does not exist.
      """

  @test-trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "When I visit the :vocabulary term edit page with the name :name" fails with non-existing term
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the role "administrator"
      When I visit the "tags" term edit page with the name "Nonexisting"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find the term "Nonexisting" in the vocabulary "tags".
      """

  Scenario: Assert "When I visit the :vocabulary term delete page with the name :name" works
    Given I am logged in as a user with the role "administrator"
    When I visit the "tags" term delete page with the name "Tag1"
    Then the response should contain "200"
    And I should see "Tag1"

  @test-trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "When I visit the :vocabulary term delete page with the name :name" fails with non-existing vocabulary
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the role "administrator"
      When I visit the "nonexisting" term delete page with the name "Tag1"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The vocabulary "nonexisting" does not exist.
      """

  @test-trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "When I visit the :vocabulary term delete page with the name :name" fails with non-existing term
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the role "administrator"
      When I visit the "tags" term delete page with the name "Nonexisting"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find the term "Nonexisting" in the vocabulary "tags".
      """

  Scenario: Create single taxonomy term with vertical field format
    Given I am logged in as a user with the role "administrator"
    And the following tags terms with fields exist:
      | name        | [TEST] Vertical Tag  |
      | description | Vertical format term |
    When I go to "admin/structure/taxonomy/manage/tags/overview"
    Then I should see "[TEST] Vertical Tag"

  Scenario: Create multiple taxonomy terms with vertical field format
    Given I am logged in as a user with the role "administrator"
    And the following tags terms with fields exist:
      | name        | [TEST] V-Tag 1      | [TEST] V-Tag 2       | [TEST] V-Tag 3      |
      | description | First vertical term | Second vertical term | Third vertical term |
    When I go to "admin/structure/taxonomy/manage/tags/overview"
    Then I should see "[TEST] V-Tag 1"
    And I should see "[TEST] V-Tag 2"
    And I should see "[TEST] V-Tag 3"

  Scenario: Create a term with the "parent" column naming its parent term
    Given the following "tags" terms exist:
      | name               |
      | [TEST] Parent term |
    And the following "tags" terms exist:
      | name              | parent             |
      | [TEST] Child term | [TEST] Parent term |
    And I am logged in as a user with the role "administrator"
    When I visit the "tags" term edit page with the name "[TEST] Child term"
    Then the option "[TEST] Parent term" within the select "edit-parent" should be selected

  @test-trait:Drupal\TaxonomyTrait
  Scenario: Assert that a "parent" column naming a term that does not exist fails
    Given some behat configuration
    And scenario steps:
      """
      Given the following "tags" terms exist:
        | name              | parent                |
        | [TEST] Child term | [TEST] Missing parent |
      """
    When I run "behat --no-colors"
    Then it should fail with a "DrevOps\BehatSteps\Backend\Exception\CreationAliasResolutionException" exception:
      """
      Cannot create term because parent term "[TEST] Missing parent" does not exist in vocabulary "tags".
      """

  @test-trait:Drupal\TaxonomyTrait
  Scenario: Assert that a "parent" column naming a term that several terms share fails
    Given some behat configuration
    And scenario steps:
      """
      Given the following "tags" terms exist:
        | name                 |
        | [TEST] Shared parent |
        | [TEST] Shared parent |
      And the following "tags" terms exist:
        | name              | parent               |
        | [TEST] Child term | [TEST] Shared parent |
      """
    When I run "behat --no-colors"
    Then it should fail with a "DrevOps\BehatSteps\Backend\Exception\CreationAliasResolutionException" exception:
      """
      Cannot resolve parent term "[TEST] Shared parent" in vocabulary "tags" because multiple terms share that name.
      """
