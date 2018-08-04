@d7
Feature: Check that ParagraphsTrait works

  Background:
    Given I am logged in as a user with the "administrator" role
    Given no landing_page content:
      | title                 |
      | [TEST] Landing page 1 |
    And lading_page content:
      | title                 | field_paragraph |
      | [TEST] Landing page 1 | 1               |

  @api
  Scenario: Assert "When :field_name in :node_title node of type :node_type has :paragraph_type paragraph:"
    When "field_paragraph" in "[TEST] Landing page 1" node of type "lading_page" has "text" paragraph:
      | field_paragraph_title       | My paragraph title   |
      | field_paragraph_body:value  | My paragraph message |
      | field_paragraph_body:format | full_html            |
