@keyboard
Feature: Check that KeyboardTrait works
  As Behat Steps library developer
  I want to provide tools to interact with web elements using keyboard keys
  So that users can test keyboard navigation and input

  @api @javascript
  Scenario: Assert step definition "When I press the keys :keys on the element :selector" succeeds as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/relative.html"
    Then the "input1" field should not contain "hello"
    When I press the keys "hello" on the element "#input1"
    Then the "input1" field should contain "hello"

  @api @javascript
  Scenario: Assert step definition "When I press the key :char on the element :selector" succeeds as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/relative.html"
    Then the "input1" field should not contain "hello"
    When I press the key "h" on the element "#input1"
    And I press the key "e" on the element "#input1"
    And I press the key "l" on the element "#input1"
    And I press the key "l" on the element "#input1"
    And I press the key "o" on the element "#input1"
    Then the "input1" field should contain "hello"

  @trait:KeyboardTrait
  Scenario: Assert that negative assertion for "When I press the keys :keys on the element :selector" step throws an exception for using with a driver other than Selenium2Driver
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      And I visit "/sites/default/files/relative.html"
      When I press the key "h" on the element "#input1"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Behat\Mink\Exception\UnsupportedDriverActionException" exception:
      """
      Method can be used only with Selenium2 driver
      """

  @api @javascript
  Scenario: Assert step definition "When I press the key :char" succeeds as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/relative.html"
    Then the element "#sr-only-focusable" should not be displayed within a viewport
    When I press the key "tab"
    Then the element "#sr-only-focusable" should be displayed within a viewport

  @api @javascript
  Scenario: Assert step definition "When I press the key :char on the element :selector" succeeds as expected with "tab" key
    Given I am an anonymous user
    When I visit "/sites/default/files/relative.html"
    Then the "input2" field should not contain "h"
    When I press the key "tab" on the element "#input1"
    And I press the key "h" on the element "#input2"
    Then the "input2" field should contain "h"
