@keyboard
Feature: Check that KeyboardTrait works
  As Behat Steps library developer
  I want to provide tools to interact with web elements using keyboard keys
  So that users can test keyboard navigation and input

  @api @javascript
  Scenario: Assert step definition "When I press the keys :keys on the element :selector" succeeds as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/elements_relative.html"
    Then the "input1" field should not contain "hello"
    When I press the keys "hello" on the element "#input1"
    Then the "input1" field should contain "hello"

  @api @javascript
  Scenario: Assert step definition "When I press the key :char on the element :selector" succeeds as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/elements_relative.html"
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
      And I visit "/sites/default/files/elements_relative.html"
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
    When I visit "/sites/default/files/elements_relative.html"
    Then the element "#sr-only-focusable" should not be displayed within a viewport
    When I press the key "tab"
    Then the element "#sr-only-focusable" should be displayed within a viewport

  @api @javascript
  Scenario: Assert step definition "When I press the key :char on the element :selector" succeeds as expected with "tab" key
    Given I am an anonymous user
    When I visit "/sites/default/files/elements_relative.html"
    Then the "input2" field should not contain "h"
    When I press the key "tab" on the element "#input1"
    And I press the key "h" on the element "#input2"
    Then the "input2" field should contain "h"

  @api @javascript
  Scenario: Assert step definition "When I press the keys :keys" succeeds as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/elements_relative.html"
    And I fill in "input1" with ""
    Then the "input1" field should not contain "helloworld"
    When I press the keys "hello" on the element "#input1"
    And I press the keys "world"
    Then the "input1" field should contain "helloworld"

  @trait:KeyboardTrait
  Scenario: Assert negative assertion for empty key throws an exception
    Given some behat configuration
    And scenario steps tagged with "@api @javascript":
      """
      Given I am an anonymous user
      And I visit "/sites/default/files/elements_relative.html"
      When I press the key "" on the element "#input1"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      keyPress($char) was invoked but the $char parameter was empty.
      """

  @trait:KeyboardTrait
  Scenario: Assert negative assertion for unsupported key throws an exception
    Given some behat configuration
    And scenario steps tagged with "@api @javascript":
      """
      Given I am an anonymous user
      And I visit "/sites/default/files/elements_relative.html"
      When I press the key "unsupportedkey" on the element "#input1"
      """
    When I run "behat --no-colors"
    Then it should fail with a "RuntimeException" exception:
      """
      Unsupported key "unsupportedkey" provided
      """

  @trait:KeyboardTrait
  Scenario: Assert negative assertion for non-existent element throws an exception
    Given some behat configuration
    And scenario steps tagged with "@api @javascript":
      """
      Given I am an anonymous user
      And I visit "/sites/default/files/elements_relative.html"
      When I press the key "h" on the element "#non-existent-element"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Behat\Mink\Exception\ElementNotFoundException" exception:
      """
      Element matching css "#non-existent-element" not found.
      """

  @trait:KeyboardTrait
  Scenario: Assert negative assertion for no focused element throws an exception
    Given some behat configuration
    And scenario steps tagged with "@api @javascript":
      """
      Given I am an anonymous user
      And I visit "/sites/default/files/elements_relative.html"
      When I press the keys "abc"
      """
    When I run "behat --no-colors"
    Then it should fail with a "RuntimeException" exception:
      """
      No element is currently focused. Please focus an element first using a step with a selector.
      """
