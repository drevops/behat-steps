Feature: Check that ParagraphsTrait works
  As Behat Steps library developer
  I want to provide tools to manage paragraph entities programmatically
  So that users can test paragraph field functionality

  Background:
    Given I am logged in as a user with the "administrator" role
    And the following "landing_page" content does not exist:
      | title                 |
      | [TEST] Landing page 1 |
    And landing_page content:
      | title                 |
      | [TEST] Landing page 1 |

  @api
  Scenario: Assert "Given the following fields for the paragraph :paragraph_type exist in the field :parent_field within the :parent_bundle :parent_entity_type identified by the field :parent_lookup_field and the value :parent_lookup_value:"
    When the following fields for the paragraph "text" exist in the field "field_paragraph" within the "landing_page" "node" identified by the field "title" and the value "[TEST] Landing page 1":
      | field_paragraph_title       | My paragraph title   |
      | field_paragraph_body:value  | My paragraph message |
      | field_paragraph_body:format | full_html            |
    And I visit the "landing_page" content page with the title "[TEST] Landing page 1"
    Then I should see the text "My paragraph title"
    And I should see the text "My paragraph message"

  @api @trait:Drupal\ParagraphsTrait
  Scenario: Assert that negative assertion for "Given the following fields for the paragraph :paragraph_type exist in the field :parent_field within the :parent_bundle :parent_entity_type identified by the field :parent_lookup_field and the value :parent_lookup_value:" fails with an error on non-existing parent field
    Given some behat configuration
    And scenario steps:
      """
      When the following fields for the paragraph "text" exist in the field "field_non_existing_paragraph" within the "landing_page" "node" identified by the field "title" and the value "[TEST] Landing page 1":
        | field_paragraph_title | My paragraph title |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The entity type "node" with bundle "landing_page" does not have a field "field_non_existing_paragraph".
      """

  @api @trait:Drupal\ParagraphsTrait
  Scenario: Assert that negative assertion for "Given the following fields for the paragraph :paragraph_type exist in the field :parent_field within the :parent_bundle :parent_entity_type identified by the field :parent_lookup_field and the value :parent_lookup_value:" fails with an error on non-existing parent entity
    Given some behat configuration
    And scenario steps:
      """
      When the following fields for the paragraph "text" exist in the field "field_paragraph" within the "landing_page" "node" identified by the field "title" and the value "[TEST] Non-existing landing page":
        | field_paragraph_title | My paragraph title |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The parent entity of type "node" and bundle "landing_page" with the field "title" and the value "[TEST] Non-existing landing page" was not found
      """
