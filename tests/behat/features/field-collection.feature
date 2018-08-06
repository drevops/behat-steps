@d7 @api
Feature: Check that FieldCollectionTrait works

  Background:
    Given I am logged in as a user with the "administrator" role
    And article content:
      | title           | field_collection:field_text_first;field_text_second |
      | Test collection | test_text1;test_text2                               |

  Scenario: Assert that collection field present on page
    When I go to "content/test-collection"
    Then I should see "test_text1"
    And I should see "test_text2"
