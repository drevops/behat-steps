@d8
Feature: Check that EckEntityTrait works for D8

  Background:
    Given I am logged in as a user with the "administrator" role

    Given no test_bundle test_entity_type entities:
      | title             |
      | [TEST] ECK Entity |
    And "tags" terms:
      | name |
      | T2   |
    And test_bundle test_entity_type entities:
    | title | field_test_text | field_test_reference |
    | [TEST] ECK Entity    | Test text field | T2                   |
  @api
  Scenario: Assert "When I edit :bundle :entity_type with title :label"
    When I edit test_bundle test_entity_type with title "[TEST] ECK Entity"
    Then I should see "Edit Test entity type"
    And I visit test_bundle test_entity_type with title "[TEST] ECK Entity"
    And I should see "[TEST] ECK Entity"
    And I should see "T2"
