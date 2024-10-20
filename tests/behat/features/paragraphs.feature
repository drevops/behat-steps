Feature: Check that ParagraphsTrait works

  Background:
    Given I am logged in as a user with the "administrator" role
    Given no landing_page content:
      | title                 |
      | [TEST] Landing page 1 |
    And landing_page content:
      | title                 |
      | [TEST] Landing page 1 |

  @api
  Scenario: Assert "When :field_name in :bundle :entity_type with :entity_field_name of :entity_field_identifer has :paragraph_type paragraph:"
    When "field_paragraph" in landing_page node with title of "[TEST] Landing page 1" has "text" paragraph:
      | field_paragraph_title       | My paragraph title   |
      | field_paragraph_body:value  | My paragraph message |
      | field_paragraph_body:format | full_html            |
    And I visit landing_page "[TEST] Landing page 1"
    Then I should see the text "My paragraph title"
    And I should see the text "My paragraph message"
