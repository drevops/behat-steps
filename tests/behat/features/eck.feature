Feature: Check that EckTrait works for or D9

  Background:
    Given I am logged in as a user with the "administrator" role

    Given no test_bundle test_entity_type entities:
      | title             |
      | [TEST] ECK Entity |
    And "tags" terms:
      | name |
      | T2   |
    And test_bundle test_entity_type entities:
      | title            | field_test_text | field_test_reference |
      | [TEST] ECK test1 | Test text field | T2                   |

  @api
  Scenario: Assert "When I edit :bundle :entity_type with title :label"
    When I edit test_bundle test_entity_type with title "[TEST] ECK test1"
    Then I should see "Edit test bundle [TEST] ECK test1"
    And I visit test_bundle test_entity_type with title "[TEST] ECK test1"
    And I should see "[TEST] ECK test1"
    And I should see "T2"
