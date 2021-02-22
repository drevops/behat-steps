@d8 @wip
Feature: Check that MenuTrait works for D8

  @api
  Scenario: Assert "Given menus:"
    Given menus:
      | id          | label               | description             |
      | test_menu_1 | [TEST] menu 1 title | Test menu 1 description |
      | test_menu_2 | [TEST] menu 2 title | Test menu 2 description |
    And I am logged in as a user with the "administrator" role
    When I visit "/admin/structure/menu"
    Then I should see the text "[TEST] menu 1 title"
    And I should see the text "[TEST] menu 2 title"
    And I should see the text "Test menu 1 description"
    And I should see the text "Test menu 2 description"

  @api
  Scenario: Assert "Given no menus:"
    Given no menus:
      | id          |
      | test_menu_1 |
      | test_menu_2 |
    And I am logged in as a user with the "administrator" role
    When I visit "/admin/structure/menu"
    Then I should not see the text "[TEST] menu 1 title"
    And I should not see the text "[TEST] menu 2 title"
    And I should not see the text "Test menu 1 description"
    And I should not see the text "Test menu 2 description"

  @api @wip
  Scenario: Assert "Given menu_links:"
    Given menus:
      | id          | label               | description             |
      | test_menu_1 | [TEST] menu 1 title | Test menu 1 description |
    Given menu_links:
      | id    | uuid                                  | title             | menu_name   | enabled | link__uri               | parent                                                  |
      | 99991 | aaaaaaaa-bbbb-ccccc-dddd-999999999991 | Parent Link Title | test_menu_1 | 1       | https://www.example.com |                                                         |
      | 99992 | aaaaaaaa-bbbb-ccccc-dddd-999999999992 | Child Link Title  | test_menu_1 | 1       | https://www.example.com | menu_link_content:aaaaaaaa-bbbb-ccccc-dddd-999999999991 |
    And I am logged in as a user with the "administrator" role
    When I visit "/admin/structure/menu/manage/test_menu_1"
    And I should see "Parent Link Title"
    And I should see "Child Link Title"
    And I visit "/admin/structure/menu/item/99992/edit"
    And select "menu_parent" should have option "test_menu_1:menu_link_content:aaaaaaaa-bbbb-ccccc-dddd-999999999991" selected
