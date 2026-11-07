Feature: Check that EntityTrait works
  As Behat Steps library developer
  I want to provide steps that create and reference entities of any type
  So that users can cover entity types without a dedicated trait

  Scenario: Assert "Given the following :entity_type entities exist:" works as expected
    Given the following "block_content" entities exist:
      | info                | type  |
      | [TEST] Basic block  | basic |
    And I am logged in as a user with the role "administrator"
    When I visit "/admin/content/block"
    Then I should see "[TEST] Basic block"

  # The paired scenarios below rely on Behat running scenarios in file order:
  # the first keeps its entity past teardown, the second removes it so later
  # scenarios do not inherit it.

  @behat-steps-entity-cleanup-skip:block_content
  Scenario: The per-type skip tag keeps entities of the named type
    Given the following "block_content" entities exist:
      | info               | type  |
      | [TEST] Kept block  | basic |
    And I am logged in as a user with the role "administrator"
    When I visit "/admin/content/block"
    Then I should see "[TEST] Kept block"

  Scenario: An entity kept by the per-type skip tag survives teardown and is removed manually
    Given the following "basic" content blocks do not exist:
      | [TEST] Kept block |
    And I am logged in as a user with the role "administrator"
    When I visit "/admin/content/block"
    Then I should not see "[TEST] Kept block"

  Scenario: Assert "When I fill in the entity reference field :field with the :entity_type entity with the label :label" works
    Given the following users exist:
      | name                  | mail                              | status |
      | test_reference_author | test_reference_author@example.com | 1      |
    And I am logged in as a user with the role "administrator"
    When I go to "node/add/article"
    And I fill in "Title" with "[TEST] Article with a referenced author"
    And I fill in the entity reference field "Authored by" with the "user" entity with the label "test_reference_author"
    And I select "Published" from "edit-moderation-state-0-state"
    And I press "Save"
    And I visit the "article" content edit page with the title "[TEST] Article with a referenced author"
    Then the response should contain "test_reference_author ("

  Scenario: Assert "When I fill in the entity reference field :field with the :bundle :entity_type entity with the label :label" uses the newest of several entities sharing the label
    Given the following "tags" terms exist:
      | name             | description                   |
      | [TEST] Jazz term | [TEST] Older jazz description |
      | [TEST] Jazz term | [TEST] Newer jazz description |
    And I am logged in as a user with the role "administrator"
    When I go to "node/add/article"
    And I fill in "Title" with "[TEST] Article with a referenced term"
    And I fill in the entity reference field "Tags" with the "tags" "taxonomy_term" entity with the label "[TEST] Jazz term"
    And I select "Published" from "edit-moderation-state-0-state"
    And I press "Save"
    And I follow "[TEST] Jazz term"
    Then I should see "[TEST] Newer jazz description"
    And I should not see "[TEST] Older jazz description"

  Scenario: Assert "When I fill in the entity reference field :field with the :entity_type entity with the label :label" keeps a label with a comma as 1 entity
    Given the following "tags" terms exist:
      | name              |
      | [TEST] Rock, pop  |
    And I am logged in as a user with the role "administrator"
    When I go to "node/add/article"
    And I fill in "Title" with "[TEST] Article with a comma term"
    And I fill in the entity reference field "Tags" with the "taxonomy_term" entity with the label "[TEST] Rock, pop"
    And I select "Published" from "edit-moderation-state-0-state"
    And I press "Save"
    And I follow "[TEST] Rock, pop"
    Then the response status code should be 200
    And the "tags" term with the name "[TEST] Rock" should not exist

  @test-trait:Drupal\EntityTrait
  Scenario: Assert negative assertion for "When I fill in the entity reference field :field with the :entity_type entity with the label :label" fails with a non-existing entity
    Given some behat configuration
    And scenario steps:
      """
      When I fill in the entity reference field "Tags" with the "taxonomy_term" entity with the label "[TEST] Nonexisting term"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The "taxonomy_term" entity with the label "[TEST] Nonexisting term" does not exist.
      """

  @test-trait:Drupal\EntityTrait
  Scenario: Assert negative assertion for "When I fill in the entity reference field :field with the :bundle :entity_type entity with the label :label" fails with a non-existing bundle
    Given some behat configuration
    And scenario steps:
      """
      When I fill in the entity reference field "Tags" with the "nonexisting" "taxonomy_term" entity with the label "[TEST] Jazz term"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The entity type "taxonomy_term" has no bundle "nonexisting".
      """

  @test-trait:Drupal\EntityTrait
  Scenario: Assert negative assertion for "When I fill in the entity reference field :field with the :bundle :entity_type entity with the label :label" fails with a non-existing entity in the bundle
    Given some behat configuration
    And scenario steps:
      """
      When I fill in the entity reference field "Tags" with the "tags" "taxonomy_term" entity with the label "[TEST] Nonexisting term"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The "tags" "taxonomy_term" entity with the label "[TEST] Nonexisting term" does not exist.
      """

  @test-trait:Drupal\EntityTrait
  Scenario: Assert negative assertion for "When I fill in the entity reference field :field with the :bundle :entity_type entity with the label :label" fails with a non-existing field
    Given some behat configuration
    And scenario steps:
      """
      Given the following "taxonomy_term" entities exist:
        | name             | vid  |
        | [TEST] Jazz term | tags |
      And I am logged in as a user with the role "administrator"
      When I go to "node/add/article"
      And I fill in the entity reference field "Nonexisting field" with the "tags" "taxonomy_term" entity with the label "[TEST] Jazz term"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Form field with id|name|label|value|placeholder "Nonexisting field" not found.
      """
