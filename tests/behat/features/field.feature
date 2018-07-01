@d7
Feature: Check that FieldTrait works

  Scenario: Assert field exists
    Given I go to "test-form"
    Then I see field "field1"
    Then I see field "Field 1"

  Scenario: Assert field does not exist
    Given I go to "test-form"
    Then I don't see field "some_random_field"

  Scenario Outline: Assert field existence
    Given I go to "test-form"
    Then field "<field>" "<existence>" on the page
    Examples:
      | field        | existence  |
      | field1       | exists     |
      | Field 1      | exists     |
      | field2       | exists     |
      | Field 2      | exists     |
      | random_field | not exists |

  Scenario Outline: Assert if field is disabled
    Given I go to "test-form"
    Then field "<field>" is "<state>" on the page
    Examples:
      | field          | state    |
      | field1         | active   |
      | Field 1        | active   |
      | field2         | active   |
      | Field 2        | active   |
      | field3disabled | disabled |
      | Field 3        | disabled |

  Scenario Outline: Assert field presence and state
    Given I go to "test-form"
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
