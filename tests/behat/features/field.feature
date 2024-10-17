Feature: Check that FieldTrait works

  @api
  Scenario: Assert field exists
    Given I go to "form/test-form"
    Then I see field "field1"
    Then I see field "Field 1"

  @api
  Scenario: Assert field does not exist
    Given I go to "test-form"
    Then I don't see field "some_random_field"

  @api
  Scenario Outline: Assert field existence
    Given I go to "form/test-form"
    Then field "<field>" "<existence>" on the page
    Examples:
      | field        | existence  |
      | field1       | exists     |
      | Field 1      | exists     |
      | field2       | exists     |
      | Field 2      | exists     |
      | random_field | not exists |

  @api
  Scenario Outline: Assert if field is disabled
    Given I go to "form/test-form"
    Then field "<field>" is "<state>" on the page
    Examples:
      | field          | state    |
      | field1         | active   |
      | Field 1        | active   |
      | field2         | active   |
      | Field 2        | active   |
      | field3disabled | disabled |
      | Field 3        | disabled |

  @api
  Scenario Outline: Assert field presence and state
    Given I go to "form/test-form"
    Then field "<field>" should be "<presence>" on the page and have state "<state>"
    Examples:
      | field          | presence    | state    |
      | field1         | present     | active   |
      | Field 1        | present     | active   |
      | field2         | present     | active   |
      | Field 2        | present     | active   |
      | field3disabled | present     | disabled |
      | Field 3        | present     | disabled |
      | field_random   | not present | disabled |
      | field_random   | not present | active   |
      | field_random   | not present |          |

  @api @javascript
  Scenario: Assert fills in form color field with specified id|name|label|value.
    Given I visit "/sites/default/files/relative.html"
    Then color field "#edit-color-input" value is "#000000"
    And I fill color in "#edit-color-input" with "#ffffff"
    Then color field "#edit-color-input" value is "#ffffff"

  @api @javascript
  Scenario: Assert fills in form color field with specified id|name|label|value.
    Given I visit "/sites/default/files/relative.html"
    Then color field "#edit-color-input" value is "#000000"
    And I fill color in "#ffffff" for "#edit-color-input"
    Then color field "#edit-color-input" value is "#ffffff"

  @trait:FieldTrait
  Scenario: Assert that field exists on the page using id,name,label or value.
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "form/test-form"
      Then I see field "No existing field"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Form field with id|name|label|value "No existing field" not found.
      """

  @trait:FieldTrait
  Scenario: Assert that field does not exist on the page using id,name,label or value.
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "form/test-form"
      Then I don't see field "Field 1"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      A field "Field 1" appears on this page, but it should not.
      """

  @trait:FieldTrait
  Scenario: Assert that field does not exist on the page using id,name,label or value.
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "form/test-form"
      Then field "Field 1" "does not exist" on the page
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      A field "Field 1" appears on this page, but it should not.
      """

  @trait:FieldTrait
  Scenario: Assert whether the field has a state.
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "form/test-form"
      Then field "field3disabled" is "enabled" on the page
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      A field "field3disabled" should not be disabled, but it is.
      """

  @trait:FieldTrait
  Scenario: Assert whether the field has a state.
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "form/test-form"
      Then field "field1" is "disabled" on the page
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      A field "field1" should be disabled, but it is not.
      """

  @trait:FieldTrait
  Scenario: Assert whether the field exists on the page and has a state.
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "form/test-form"
      Then field "field1" should be "present" on the page and have state "disabled"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      A field "field1" should be disabled, but it is not.
      """

  @trait:FieldTrait
  Scenario: Assert whether the field exists on the page and has a state.
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "form/test-form"
      Then field "No existing field" should be "present" on the page and have state "disabled"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Form field with id|name|label|value "No existing field" not found.
      """
