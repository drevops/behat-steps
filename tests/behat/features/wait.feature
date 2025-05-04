Feature: Check that WaitTrait works
  As Behat Steps library developer
  I want to provide tools to wait for elements or time periods
  So that users can synchronize tests with page loading and AJAX events

  @api
  Scenario: Assert "When I wait for :seconds second(s)"
    When I go to the homepage
    And I wait for 1 second
    Then I save screenshot
    When I wait for 2 seconds
    Then I save screenshot
    When I wait for 1 second
    Then I save screenshot
    When I wait for 2 seconds

  @api @javascript
  Scenario: Assert "When I wait for :seconds second(s) for AJAX to finish"
    Given I am logged in as a user with the "administrator" role
    When I visit "admin/structure/types/manage/page/form-display"
    Then I should not see an "input[name=fields\[title\]\[settings_edit_form\]\[settings\]\[placeholder\]]" element
    When I press the "title_settings_edit" button
    And I wait for "5" seconds for AJAX to finish
    Then I should see an "input[name=fields\[title\]\[settings_edit_form\]\[settings\]\[placeholder\]]" element

  @trait:WaitTrait
  Scenario: Assert that negative assertion for "When I wait for :seconds second(s) for AJAX to finish" can be used only with JS-capable driver
    Given some behat configuration
    And scenario steps tagged with "@api":
      """
      Given I am logged in as a user with the "administrator" role
      Then I visit "admin/structure/types/manage/page/form-display"
      Then I should not see an "input[name=fields\[title\]\[settings_edit_form\]\[settings\]\[placeholder\]]" element
      Then I press the "title_settings_edit" button
      Then I wait for "5" seconds for AJAX to finish
      Then I should see an "input[name=fields\[title\]\[settings_edit_form\]\[settings\]\[placeholder\]]" element
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Method can be used only with JS-capable driver
      """
