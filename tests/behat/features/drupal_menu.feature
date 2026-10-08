Feature: Check that MenuTrait works
  As Behat Steps library developer
  I want to provide tools to manage menus programmatically
  So that users can test menu functionality

  Scenario: Assert "When the following menus exist:"
    When the following menus exist:
      | label               | description             |
      | [TEST] menu 1 title | Test menu 1 description |
      | [TEST] menu 2 title | Test menu 2 description |
    And I log in as a user with the role "administrator"
    And I visit "/admin/structure/menu"
    Then I should see "[TEST] menu 1 title"
    And I should see "[TEST] menu 2 title"
    And I should see "Test menu 1 description"
    And I should see "Test menu 2 description"

  Scenario: Assert "When the menu :menu_name does not exist"
    Given the following menus exist:
      | label               | description             |
      | [TEST] menu 1 title | Test menu 1 description |
      | [TEST] menu 2 title | Test menu 2 description |
    When the menu "[TEST] menu 1 title" does not exist
    And the menu "[TEST] menu 2 title" does not exist
    And the menu "[TEST] non-existent menu" does not exist
    And I log in as a user with the role "administrator"
    And I visit "/admin/structure/menu"
    Then I should not see "[TEST] menu 1 title"
    And I should not see "[TEST] menu 2 title"
    And I should not see "Test menu 1 description"
    And I should not see "Test menu 2 description"

  Scenario: Assert "When the menu :menu_name does not exist" removes every menu with the label
    Given the following menus exist:
      | id                    | label                 | description                       |
      | test_duplicate_menu_1 | [TEST] Duplicate menu | Test duplicate menu 1 description |
      | test_duplicate_menu_2 | [TEST] Duplicate menu | Test duplicate menu 2 description |
    When the menu "[TEST] Duplicate menu" does not exist
    And I log in as a user with the role "administrator"
    And I visit "/admin/structure/menu"
    Then I should not see "[TEST] Duplicate menu"
    And I should not see "Test duplicate menu 1 description"
    And I should not see "Test duplicate menu 2 description"

  Scenario: Assert "When the following menu links do not exist in the menu :menu_name" removes every link with the title
    Given the following menus exist:
      | label               | description             |
      | [TEST] menu 1 title | Test menu 1 description |
    And the following menu links exist in the menu "[TEST] menu 1 title":
      | title                 | enabled | uri                     |
      | [TEST] Duplicate link | 1       | https://www.example.com |
      | [TEST] Duplicate link | 1       | https://www.example.org |
      | [TEST] Other link     | 1       | https://www.example.net |
    When the following menu links do not exist in the menu "[TEST] menu 1 title":
      | [TEST] Duplicate link |
    And I log in as a user with the role "administrator"
    And I visit "/admin/config/development/performance"
    And I press "Clear all cache"
    And I visit "/admin/structure/menu/manage/_test_menu_1_title"
    Then I should not see "[TEST] Duplicate link"
    And I should see "[TEST] Other link"

  Scenario: Assert "When the following menu links exist/do not exist in the menu :menu_name"
    When the following menus exist:
      | label               | description             |
      | [TEST] menu 1 title | Test menu 1 description |
    And the following menu links exist in the menu "[TEST] menu 1 title":
      | title             | enabled | uri                     | parent            |
      | Parent Link Title | 1       | https://www.example.com |                   |
      | Child Link Title  | 1       | https://www.example.com | Parent Link Title |
    And I log in as a user with the role "administrator"
    And I visit "/admin/structure/menu/manage/_test_menu_1_title"
    Then I should see "Parent Link Title"
    And I should see "Child Link Title"

    When the following menu links do not exist in the menu "[TEST] menu 1 title":
      | Child Link Title         |
      | [TEST] Non-existent link |
    And the following menu links do not exist in the menu "[TEST] non-existent menu":
      | Parent Link Title |
    And I visit "/admin/config/development/performance"
    And I press "Clear all cache"
    And I visit "/admin/structure/menu/manage/_test_menu_1_title"
    Then I should not see "Child Link Title"
    And I should see "Parent Link Title"

  @test-trait:Drupal\MenuTrait
  Scenario: Menu links in a menu that does not exist fail with an exception
    Given some behat configuration
    And scenario steps:
      """
      Given the following menu links exist in the menu "[TEST] non-existent menu":
        | title       | uri                     |
        | Orphan Link | https://www.example.com |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Menu "[TEST] non-existent menu" was not found.
      """
