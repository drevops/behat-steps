Feature: Check that WaitTrait works
  As Behat Steps library developer
  I want to provide tools to wait for elements or time periods
  So that users can synchronize tests with page loading and AJAX events

  Scenario: Assert "When I wait for :seconds second(s)"
    When I go to the homepage
    And I wait for 1 second
    Then I save screenshot
    When I wait for 2 seconds
    Then I save screenshot
    When I wait for 1 second
    Then I save screenshot
    When I wait for 2 seconds

  @javascript
  Scenario: Assert "When I wait for :seconds second(s) for AJAX to finish"
    When I log in as a user with the role "administrator"
    When I visit "admin/structure/types/manage/page/form-display"
    Then I should not see an "input[name=fields\[title\]\[settings_edit_form\]\[settings\]\[placeholder\]]" element
    When I press "title_settings_edit"
    And I wait for "5" seconds for AJAX to finish
    Then I should see an "input[name=fields\[title\]\[settings_edit_form\]\[settings\]\[placeholder\]]" element

  @test-trait:WaitTrait
  Scenario: Assert that "When I wait for :seconds second(s) for AJAX to finish" fails when AJAX does not complete in time
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      When I visit "http://cli:8888/ajax_timeout.html"
      And I wait for "2" seconds for AJAX to finish
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Unable to complete an AJAX request.
      """

  @test-trait:WaitTrait
  Scenario Outline: Assert that a wait step fails when the number of seconds is not an integer of 0 or more
    Given some behat configuration
    And scenario steps:
      """
      <step>
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      <message>
      """
    Examples:
      | step                                             | message                                                          |
      | When I wait for "a few" seconds                  | The number of seconds must be an integer, but "a few" was given. |
      | When I wait for "-1" seconds                     | The number of seconds must be 0 or greater, but "-1" was given.  |
      | When I wait for "1.5" seconds for AJAX to finish | The number of seconds must be an integer, but "1.5" was given.   |

  @test-trait:WaitTrait
  Scenario: Assert that negative assertion for "When I wait for :seconds second(s) for AJAX to finish" can be used only with JS-capable driver
    Given some behat configuration
    And scenario steps:
      """
      When I log in as a user with the role "administrator"
      Then I visit "admin/structure/types/manage/page/form-display"
      Then I should not see an "input[name=fields\[title\]\[settings_edit_form\]\[settings\]\[placeholder\]]" element
      Then I press "title_settings_edit"
      Then I wait for "5" seconds for AJAX to finish
      Then I should see an "input[name=fields\[title\]\[settings_edit_form\]\[settings\]\[placeholder\]]" element
      """
    When I run "behat --no-colors"
    Then it should fail with a "Behat\Mink\Exception\UnsupportedDriverActionException" exception:
      """
      No browser capability "DrevOps\BehatSteps\Behat\Mink\Capability\JavascriptCapabilityInterface" is available for
      """
