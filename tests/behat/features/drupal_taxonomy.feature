@taxonomy
Feature: Check that TaxonomyTrait works
  As Behat Steps library developer
  I want to provide tools to manage taxonomy terms programmatically
  So that users can test taxonomy-related functionality

  Background:
    Given "tags" terms:
      | name |
      | Tag1 |
      | Tag2 |
      | Tag3 |

  @api
  Scenario: Assert "Then the vocabulary :machine_name with the name :name should exist" works
    Given I am logged in as a user with the "administrator" role
    Then the vocabulary "tags" with the name "Tags" should exist

  @api @trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "Then the vocabulary :machine_name with the name :name should exist" works with non-existing vocabulary
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then the vocabulary "noneixisting" with the name "Noneixisting" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The vocabulary "noneixisting" does not exist.
      """

  @api @trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "Then the vocabulary :machine_name with the name :name should exist" works with existing vocabulary but incorrect name
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then the vocabulary "tags" with the name "Invalidname" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The vocabulary "tags" exists with a name "Tags", but expected "Invalidname".
      """

  @api
  Scenario: Assert "Then the vocabulary :machine_name should not exist" works
    Given I am logged in as a user with the "administrator" role
    Then the vocabulary "noneixisting" should not exist

  @api @trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "Then the vocabulary :machine_name should not exist" works with existing vocabulary
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then the vocabulary "tags" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The vocabulary "tags" exist, but it should not.
      """

  @api
  Scenario: Assert "Then the taxonomy term :term_name from the vocabulary :vocabulary_machine_name should exist" works
    Given I am logged in as a user with the "administrator" role
    Then the taxonomy term "Tag1" from the vocabulary "tags" should exist

  @api @trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "Then the taxonomy term :term_name from the vocabulary :vocabulary_machine_name should exist" works with non-existing vocabulary
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then the taxonomy term "Tag" from the vocabulary "nonexisting" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The vocabulary "nonexisting" does not exist.
      """

  @api @trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "Then the taxonomy term :term_name from the vocabulary :vocabulary_machine_name should exist" works with non-existing term
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then the taxonomy term "Nonexisting" from the vocabulary "tags" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The taxonomy term "Nonexisting" from the vocabulary "tags" does not exist.
      """

  @api
  Scenario: Assert "Then the taxonomy term :term_name from the vocabulary :vocabulary_machine_name should not exist" works
    Given I am logged in as a user with the "administrator" role
    Then the taxonomy term "Nonexisting" from the vocabulary "tags" should not exist

  @api @trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "Then the taxonomy term :term_name from the vocabulary :vocabulary_machine_name should not exist" works with non-existing vocabulary
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then the taxonomy term "Nonexisting" from the vocabulary "nonexisting" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The vocabulary "nonexisting" does not exist.
      """

  @api @trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "Then the taxonomy term :term_name from the vocabulary :vocabulary_machine_name should not exist" works with an existing term
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      Then the taxonomy term "Tag1" from the vocabulary "tags" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The taxonomy term "Tag1" from the vocabulary "tags" exists, but it should not.
      """

  @api @trait:Drupal\TaxonomyTrait
  Scenario: Assert "Given the following :vocabulary_machine_name vocabulary terms do not exist" works
    Given the following "tags" vocabulary terms do not exist:
      | Tag1        |
      | Tag2        |
      | Nonexisting |
    Then the taxonomy term "Tag1" from the vocabulary "tags" should not exist
    And the taxonomy term "Tag2" from the vocabulary "tags" should not exist
    And the taxonomy term "Nonexisting" from the vocabulary "tags" should not exist
    And the taxonomy term "Tag3" from the vocabulary "tags" should exist

  @api @trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "Given the following :vocabulary_machine_name vocabulary terms do not exist" fails with non-existing vocabulary
    Given some behat configuration
    And scenario steps:
      """
      Given the following "nonexisting" vocabulary terms do not exist:
        | Tag1        |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The vocabulary "nonexisting" does not exist.
      """

  @api
  Scenario: Assert "When I visit the :vocabulary_machine_name term page with the name :term_name" works
    Given I am logged in as a user with the "administrator" role
    When I visit the "tags" term page with the name "Tag1"
    Then the response should contain "200"
    And I should see "Tag1"

  @api @trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "When I visit the :vocabulary_machine_name term page with the name :term_name" fails with non-existing vocabulary
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the "nonexisting" term page with the name "Tag1"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The vocabulary "nonexisting" does not exist.
      """

  @api @trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "When I visit the :vocabulary_machine_name term page with the name :term_name" fails with non-existing term
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the "tags" term page with the name "Nonexisting"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find the term "Nonexisting" in the vocabulary "tags".
      """

  @api
  Scenario: Assert "When I visit the :vocabulary_machine_name term edit page with the name :term_name" works
    Given I am logged in as a user with the "administrator" role
    When I visit the "tags" term edit page with the name "Tag1"
    Then the response should contain "200"
    And I should see "Tag1"

  @api @trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "When I visit the :vocabulary_machine_name term edit page with the name :term_name" fails with non-existing vocabulary
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the "nonexisting" term edit page with the name "Tag1"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The vocabulary "nonexisting" does not exist.
      """

  @api @trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "When I visit the :vocabulary_machine_name term edit page with the name :term_name" fails with non-existing term
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the "tags" term edit page with the name "Nonexisting"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find the term "Nonexisting" in the vocabulary "tags".
      """

  @api
  Scenario: Assert "When I visit the :vocabulary_machine_name term delete page with the name :term_name" works
    Given I am logged in as a user with the "administrator" role
    When I visit the "tags" term delete page with the name "Tag1"
    Then the response should contain "200"
    And I should see "Tag1"

  @api @trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "When I visit the :vocabulary_machine_name term delete page with the name :term_name" fails with non-existing vocabulary
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the "nonexisting" term delete page with the name "Tag1"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The vocabulary "nonexisting" does not exist.
      """

  @api @trait:Drupal\TaxonomyTrait
  Scenario: Assert negative assertion for "When I visit the :vocabulary_machine_name term delete page with the name :term_name" fails with non-existing term
    Given some behat configuration
    And scenario steps:
      """
      Given I am logged in as a user with the "administrator" role
      When I visit the "tags" term delete page with the name "Nonexisting"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to find the term "Nonexisting" in the vocabulary "tags".
      """
