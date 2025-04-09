Feature: Check that FieldTrait works

  @api
  Scenario: Assert field exists
    Given I go to "form/test-form"
    Then the field "field1" should exist
    Then the field "Field 1" should exist

  @api
  Scenario: Assert field does not exist
    Given I go to "test-form"
    Then the field "some_random_field" should not exist

  @api
  Scenario Outline: Assert field existence
    Given I go to "form/test-form"
    Then the field "<field>" should <existence>
    Examples:
      | field        | existence  |
      | field1       | exist      |
      | Field 1      | exist      |
      | field2       | exist      |
      | Field 2      | exist      |
      | random_field | not exist  |

  @api
  Scenario Outline: Assert if field is disabled
    Given I go to "form/test-form"
    Then the field "<field>" should be "<state>"
    Examples:
      | field          | state    |
      | field1         | enabled  |
      | Field 1        | enabled  |
      | field2         | enabled  |
      | Field 2        | enabled  |
      | field3disabled | disabled |
      | Field 3        | disabled |

  @api @javascript
  Scenario: Assert fills in form color field with specified id|name|label|value.
    Given I visit "/sites/default/files/relative.html"
    Then the color field "#edit-color-input" should have the value "#000000"
    And I fill color in "#edit-color-input" with "#ffffff"
    Then the color field "#edit-color-input" should have the value "#ffffff"

  @api @javascript
  Scenario: Assert fills in form color field with specified id|name|label|value using an alternate step definition.
    Given I visit "/sites/default/files/relative.html"
    Then the color field "#edit-color-input" should have the value "#000000"
    And I fill in the color field "#edit-color-input" with the value "#ffffff"
    Then the color field "#edit-color-input" should have the value "#ffffff"

  @trait:FieldTrait
  Scenario: Assert that negative assertion for "The field :field should exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "form/test-form"
      Then the field "No existing field" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Form field with id|name|label|value "No existing field" not found.
      """

  @trait:FieldTrait
  Scenario: Assert that negative assertion for "The field :name should not exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "form/test-form"
      Then the field "Field 1" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      A field "Field 1" appears on this page, but it should not.
      """

  @trait:FieldTrait
  Scenario: Assert that negative assertion for "The field :field should not exist" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "form/test-form"
      Then the field "field1" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      A field "field1" appears on this page, but it should not.
      """

  @trait:FieldTrait
  Scenario: Assert that "The field :field should be :state" fails when the state is wrong
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "form/test-form"
      Then the field "field3disabled" should be "enabled"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      A field "field3disabled" should not be disabled, but it is.
      """

  @trait:FieldTrait
  Scenario: Assert that negative assertion for "The field :field1 should be :state" fails with an error
    Given some behat configuration
    And scenario steps:
      """
      Given I go to "form/test-form"
      Then the field "field1" should be "disabled"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      A field "field1" should be disabled, but it is not.
      """
