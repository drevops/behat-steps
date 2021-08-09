@d8 @d9
Feature: Check that MenuTrait works for D8

  @api
  Scenario: Assert "Given menus:"
    Given menus:
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
  Scenario: Assert "Given no menus:"
    Given menus:
      | label               | description             |
      | [TEST] menu 1 title | Test menu 1 description |
      | [TEST] menu 2 title | Test menu 2 description |
    Given no menus:
      | [TEST] menu 1 title      |
      | [TEST] menu 2 title      |
      | [TEST] non-existent menu |
    And I am logged in as a user with the "administrator" role
    When I visit "/admin/structure/menu"
    Then I should not see the text "[TEST] menu 1 title"
    And I should not see the text "[TEST] menu 2 title"
    And I should not see the text "Test menu 1 description"
    And I should not see the text "Test menu 2 description"

  @api
  Scenario: Assert "Given menu_links:"
    Given menus:
      | label               | description             |
      | [TEST] menu 1 title | Test menu 1 description |
    Given "[TEST] menu 1 title" menu_links:
      | title             | enabled | uri                     | parent            |
      | Parent Link Title | 1       | https://www.example.com |                   |
      | Child Link Title  | 1       | https://www.example.com | Parent Link Title |
    And I am logged in as a user with the "administrator" role
    When I visit "/admin/structure/menu/manage/_test_menu_1_title"
    And I should see "Parent Link Title"
    And I should see "Child Link Title"
