@d9 @d10
Feature: Check that WysiwygTrait works for or D9

  @api
  Scenario: Assert "When I fill in WYSIWYG :field with :value"
    Given I am logged in as a user with the "administrator" role
    When I go to "node/add/page"
    And I fill in "Title" with "test title"
    And I fill in WYSIWYG "Body" with "TEST BODY"
    And I press "Save"
    Then I should see "TEST BODY"
