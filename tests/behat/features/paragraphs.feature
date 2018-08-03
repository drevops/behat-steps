@d7
Feature: Check that ParagraphsTrait works

  Background:
    Given I am logged in as a user with the "administrator" role
    And lading_page content:
      | title        | field_paragraph |
      | Test Landing | 1               |

  @api
  Scenario Outline: Users have access to create paragraph bundles
    Given I am logged in as a user with the "<role>" role
    When I go to "admin/structure/paragraphs/add"
    Then I should get a "<response>" HTTP response
    Examples:
      | role               | response |
      | anonymous user     | 403      |
      | authenticated user | 403      |
      | administrator      | 200      |

  @api
  Scenario: Assert that paragraph field can be created with correct fields.
    When "field_paragraph" in "Test Landing" node of type "lading_page" has "text" paragraph:
      | field_paragraph_title       | My paragraph title   |
      | field_paragraph_body:value  | My paragraph message |
      | field_paragraph_body:format | full_html            |
    And "Test Landing" node of type "lading_page" has paragraph field "field_paragraph" with "text" type with paragraph:
      | field_paragraph_title       | My paragraph title   |
      | field_paragraph_body:value  | My paragraph message |
      | field_paragraph_body:format | full_html            |
    And "Test Landing" node of type "lading_page" has paragraph field "field_paragraph" with "text" bundle
