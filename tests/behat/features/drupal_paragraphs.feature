Feature: Check that ParagraphsTrait works
  As Behat Steps library developer
  I want to provide tools to manage paragraph entities programmatically
  So that users can test paragraph field functionality

  Background:
    Given I am logged in as a user with the role "administrator"
    And the following "landing_page" content does not exist:
      | title                 |
      | [TEST] Landing page 1 |
    And the following landing_page content exists:
      | title                 |
      | [TEST] Landing page 1 |

  Scenario: Assert "Given the following fields for the :paragraph_type paragraph exist in the field :parent_field within the :parent_bundle :parent_entity_type entity with the value :parent_lookup_value in the field :parent_lookup_field:"
    When the following fields for the "text" paragraph exist in the field "field_paragraph" within the "landing_page" "node" entity with the value "[TEST] Landing page 1" in the field "title":
      | field_paragraph_title       | My paragraph title   |
      | field_paragraph_body:value  | My paragraph message |
      | field_paragraph_body:format | full_html            |
    And I visit the "landing_page" content page with the title "[TEST] Landing page 1"
    Then I should see "My paragraph title"
    And I should see "My paragraph message"

  @test-trait:Drupal\ParagraphsTrait
  Scenario: Assert that negative assertion for "Given the following fields for the :paragraph_type paragraph exist in the field :parent_field within the :parent_bundle :parent_entity_type entity with the value :parent_lookup_value in the field :parent_lookup_field:" fails with an error on non-existing parent field
    Given some behat configuration
    And scenario steps:
      """
      When the following fields for the "text" paragraph exist in the field "field_non_existing_paragraph" within the "landing_page" "node" entity with the value "[TEST] Landing page 1" in the field "title":
        | field_paragraph_title | My paragraph title |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The entity type "node" with bundle "landing_page" does not have a field "field_non_existing_paragraph".
      """

  @test-trait:Drupal\ParagraphsTrait
  Scenario: Assert that negative assertion for "Given the following fields for the :paragraph_type paragraph exist in the field :parent_field within the :parent_bundle :parent_entity_type entity with the value :parent_lookup_value in the field :parent_lookup_field:" fails with an error on non-existing parent entity
    Given some behat configuration
    And scenario steps:
      """
      When the following fields for the "text" paragraph exist in the field "field_paragraph" within the "landing_page" "node" entity with the value "[TEST] Non-existing landing page" in the field "title":
        | field_paragraph_title | My paragraph title |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The parent entity of type "node" and bundle "landing_page" with the field "title" and the value "[TEST] Non-existing landing page" was not found
      """

  Scenario: Assert "Given the following fields for the :paragraph_type paragraph exist in the field :wrapper_field of a new :wrapper_type paragraph in the field :parent_field within the :parent_bundle :parent_entity_type entity with the value :parent_lookup_value in the field :parent_lookup_field:"
    When the following fields for the "text" paragraph exist in the field "field_paragraph_columns" of a new "row" paragraph in the field "field_paragraph" within the "landing_page" "node" entity with the value "[TEST] Landing page 1" in the field "title":
      | field_paragraph_title       | My column title   |
      | field_paragraph_body:value  | My column message |
      | field_paragraph_body:format | full_html         |
    And the following fields for the "text" paragraph exist in the field "field_paragraph_columns" of a new "row" paragraph in the field "field_paragraph" within the "landing_page" "node" entity with the value "[TEST] Landing page 1" in the field "title":
      | field_paragraph_title | My second column title |
    And I visit the "landing_page" content page with the title "[TEST] Landing page 1"
    Then I should see 2 ".paragraph--type--row" elements
    And the ".field--name-field-paragraph .field__item:first-child > .paragraph--type--row .paragraph--type--text" element should contain "My column title"
    And the ".field--name-field-paragraph .field__item:first-child > .paragraph--type--row .paragraph--type--text" element should contain "My column message"
    And the ".field--name-field-paragraph .field__item:last-child > .paragraph--type--row .paragraph--type--text" element should contain "My second column title"

  @test-trait:Drupal\ParagraphsTrait
  Scenario: Assert that negative assertion for "Given the following fields for the :paragraph_type paragraph exist in the field :wrapper_field of a new :wrapper_type paragraph in the field :parent_field within the :parent_bundle :parent_entity_type entity with the value :parent_lookup_value in the field :parent_lookup_field:" fails with an error on non-existing wrapper field
    Given some behat configuration
    And scenario steps:
      """
      When the following fields for the "text" paragraph exist in the field "field_non_existing_columns" of a new "row" paragraph in the field "field_paragraph" within the "landing_page" "node" entity with the value "[TEST] Landing page 1" in the field "title":
        | field_paragraph_title | My column title |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The entity type "paragraph" with bundle "row" does not have a field "field_non_existing_columns".
      """

  @test-trait:Drupal\ParagraphsTrait
  Scenario: Assert that negative assertion for "Given the following fields for the :paragraph_type paragraph exist in the field :wrapper_field of a new :wrapper_type paragraph in the field :parent_field within the :parent_bundle :parent_entity_type entity with the value :parent_lookup_value in the field :parent_lookup_field:" fails with an error on non-existing parent field
    Given some behat configuration
    And scenario steps:
      """
      When the following fields for the "text" paragraph exist in the field "field_paragraph_columns" of a new "row" paragraph in the field "field_non_existing_paragraph" within the "landing_page" "node" entity with the value "[TEST] Landing page 1" in the field "title":
        | field_paragraph_title | My column title |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The entity type "node" with bundle "landing_page" does not have a field "field_non_existing_paragraph".
      """

  @test-trait:Drupal\ParagraphsTrait
  Scenario: Assert that negative assertion for "Given the following fields for the :paragraph_type paragraph exist in the field :wrapper_field of a new :wrapper_type paragraph in the field :parent_field within the :parent_bundle :parent_entity_type entity with the value :parent_lookup_value in the field :parent_lookup_field:" fails with an error on non-existing parent entity
    Given some behat configuration
    And scenario steps:
      """
      When the following fields for the "text" paragraph exist in the field "field_paragraph_columns" of a new "row" paragraph in the field "field_paragraph" within the "landing_page" "node" entity with the value "[TEST] Non-existing landing page" in the field "title":
        | field_paragraph_title | My column title |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The parent entity of type "node" and bundle "landing_page" with the field "title" and the value "[TEST] Non-existing landing page" was not found
      """
