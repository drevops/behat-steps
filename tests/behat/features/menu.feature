Feature: Check that MenuTrait works

  @api
  Scenario: Assert "Given the following menus:"
    Given the following menus:
      | label               | description             |
      | [TEST] menu 1 title | Test menu 1 description |
      | [TEST] menu 2 title | Test menu 2 description |
    And I am logged in as a user with the "administrator" role
    When I visit "/admin/structure/menu"
    Then I should see the text "[TEST] menu 1 title"
    And I should see the text "[TEST] menu 2 title"
    And I should see the text "Test menu 1 description"
    And I should see the text "Test menu 2 description"

  @api
  Scenario: Assert "Given the menu :menu_name does not exist"
    Given the following menus:
      | label               | description             |
      | [TEST] menu 1 title | Test menu 1 description |
      | [TEST] menu 2 title | Test menu 2 description |
    Given the menu "[TEST] menu 1 title" does not exist
    Given the menu "[TEST] menu 2 title" does not exist
    Given the menu "[TEST] non-existent menu" does not exist
    And I am logged in as a user with the "administrator" role
    When I visit "/admin/structure/menu"
    Then I should not see the text "[TEST] menu 1 title"
    And I should not see the text "[TEST] menu 2 title"
    And I should not see the text "Test menu 1 description"
    And I should not see the text "Test menu 2 description"

  @api
  Scenario: Assert "Given the following menu links do not exist in the menu menu_name:" and "Given the following menu links exist in the menu :menu_name"
    Given the following menus:
      | label               | description             |
      | [TEST] menu 1 title | Test menu 1 description |
    Given the following menu links exist in the menu "[TEST] menu 1 title" :
      | title             | enabled | uri                     | parent            |
      | Parent Link Title | 1       | https://www.example.com |                   |
      | Child Link Title  | 1       | https://www.example.com | Parent Link Title |
    And I am logged in as a user with the "administrator" role
    When I visit "/admin/structure/menu/manage/_test_menu_1_title"
    And I should see "Parent Link Title"
    And I should see "Child Link Title"
    Then the following menu links do not exist in the menu "[TEST] menu 1 title":
      | Child Link Title |
    Then I visit "/admin/config/development/performance"
    # We should not need clear cache at here. Re-check later.
    Then I press the "Clear all cache" button
    Then I visit "/admin/structure/menu/manage/_test_menu_1_title"
    And I should not see "Child Link Title"
    And I should see "Parent Link Title"
