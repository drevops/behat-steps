@api @javascript
Feature: Check that WaitTrait works

  Scenario: Anonymous user visits homepage
    Given I go to the homepage
    And wait 1 second
    Then I save screenshot
    And wait 2 seconds
    Then I save screenshot
    And I wait 1 second
    Then I save screenshot
    And I wait 2 seconds

  Scenario: Wait ajax
    Given I am logged in as a user with the "administrator" role
    Then I visit "admin/structure/types/manage/page/form-display"
    Then I should not see an "input[name=fields\[title\]\[settings_edit_form\]\[settings\]\[placeholder\]]" element
    Then I press the "title_settings_edit" button
    Then I wait "5" seconds for AJAX to finish
    Then I should see an "input[name=fields\[title\]\[settings_edit_form\]\[settings\]\[placeholder\]]" element

  @trait:WaitTrait
  Scenario: Assert wait number of seconds for AJAX to finish can be used only with JS-capable driver
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "admin/structure/types/manage/page/form-display"
      Then I should not see an "input[name=fields\[title\]\[settings_edit_form\]\[settings\]\[placeholder\]]" element
      Then I press the "title_settings_edit" button
      Then I wait "5" seconds for AJAX to finish
      Then I should see an "input[name=fields\[title\]\[settings_edit_form\]\[settings\]\[placeholder\]]" element
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Method can be used only with JS-capable driver
      """
