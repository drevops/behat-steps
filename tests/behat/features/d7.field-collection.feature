@d7
Feature: Check that FieldCollectionTrait works for D7

  Background:
    Given I am logged in as a user with the "administrator" role
    And article content:
      | title                       | field_collection:field_fc_text_first;field_fc_text_second | field_collection_2:field_fc_text_third;field_fc_text_fourth |
      | Test collection             | test_text1;test_text2                                     | test_text3;test_text4                                       |
      | Test multi-value collection | test_text1;test_text2, test_text12;test_text22            | test_text3;test_text4             |

  @api
  Scenario: Assert that collection field present on page
    When I go to "content/test-collection"
    Then I should see "test_text1"
    And I should see "test_text2"
    And I should see "test_text3"
    And I should see "test_text4"
    And I go to "content/test-multi-value-collection"
    And I should see "test_text1"
    And I should see "test_text2"
    And I should see "test_text3"
    And I should see "test_text4"
    And I should see "test_text12"
    And I should see "test_text22"
    And I should see "test_text3"
    And I should see "test_text4"
