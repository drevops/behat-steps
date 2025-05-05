Feature: Check that MenuTrait works
  As Behat Steps library developer
  I want to provide tools to manage menus programmatically
  So that users can test menu functionality

  @api
  Scenario: Assert "When the following menus:"
    When the following menus:
      | label               | description             |
      | [TEST] menu 1 title | Test menu 1 description |
      | [TEST] menu 2 title | Test menu 2 description |
    And I am logged in as a user with the "administrator" role
    And I visit "/admin/structure/menu"
    Then I should see the text "[TEST] menu 1 title"
    And I should see the text "[TEST] menu 2 title"
    And I should see the text "Test menu 1 description"
    And I should see the text "Test menu 2 description"

  @api
  Scenario: Assert "When the menu :menu_name does not exist"
    Given the following menus:
      | label               | description             |
      | [TEST] menu 1 title | Test menu 1 description |
      | [TEST] menu 2 title | Test menu 2 description |
    When the menu "[TEST] menu 1 title" does not exist
    And the menu "[TEST] menu 2 title" does not exist
    And the menu "[TEST] non-existent menu" does not exist
    And I am logged in as a user with the "administrator" role
    And I visit "/admin/structure/menu"
    Then I should not see the text "[TEST] menu 1 title"
    And I should not see the text "[TEST] menu 2 title"
    And I should not see the text "Test menu 1 description"
    And I should not see the text "Test menu 2 description"

  @api
  Scenario: Assert "When the following menu links exist/do not exist in the menu :menu_name"
    When the following menus:
      | label               | description             |
      | [TEST] menu 1 title | Test menu 1 description |
    And the following menu links exist in the menu "[TEST] menu 1 title" :
      | title             | enabled | uri                     | parent            |
      | Parent Link Title | 1       | https://www.example.com |                   |
      | Child Link Title  | 1       | https://www.example.com | Parent Link Title |
    And I am logged in as a user with the "administrator" role
    And I visit "/admin/structure/menu/manage/_test_menu_1_title"
    Then I should see "Parent Link Title"
    And I should see "Child Link Title"

    When the following menu links do not exist in the menu "[TEST] menu 1 title":
      | Child Link Title |
    And I visit "/admin/config/development/performance"
    And I press the "Clear all cache" button
    And I visit "/admin/structure/menu/manage/_test_menu_1_title"
    Then I should not see "Child Link Title"
    And I should see "Parent Link Title"
