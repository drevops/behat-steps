@d7 @d8 @d9 @keyboard
Feature: Check that KeyboardTrait works

  @api @javascript
  Scenario: Assert step definition "Given I press the :keys keys on :selector" succeeds as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/relative.html"
    Then the "input1" field should not contain "hello"
    When I press the "hello" keys on "#input1"
    Then the "input1" field should contain "hello"

  @api @javascript
  Scenario: Assert step definition "Given I press the :char key on :selector" succeeds as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/relative.html"
    Then the "input1" field should not contain "hello"
    When I press the "h" key on "#input1"
    And I press the "e" key on "#input1"
    And I press the "l" key on "#input1"
    And I press the "l" key on "#input1"
    And I press the "o" key on "#input1"
    Then the "input1" field should contain "hello"

  @api @javascript
  Scenario: Assert step definition "Given I press the :char key" succeeds as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/relative.html"
    Then I should not see a visually hidden "#sr-only-focusable" element
    When I press the "tab" key
    And I should see a visually visible "#sr-only-focusable" element
