Feature: Check that ElementTrait works

  Scenario: Assert "Then the element :selector with the attribute :attribute and the value :value should exist" works as expected
    Given I am an anonymous user
    When I visit "/"
    Then the element "html" with the attribute "dir" and the value "ltr" should exist

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value :value should exist" fails as expected when the element does not exist
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then the element "#nonexisting-element" with the attribute "dir" and the value "ltr" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "#nonexisting-element" element does not exist.
      """

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value :value should exist" fails as expected when the attribute does not exist
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then the element "html" with the attribute "no-existing-attribute" and the value "ltr" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "no-existing-attribute" attribute does not exist on the element "html".
      """

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value :value should exist" fails as expected when the attribute does not contain the exact value
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then the element "html" with the attribute "dir" and the value "lt" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "dir" attribute exists on the element "html" with a value "ltr", but it does not have a value "lt".
      """

  Scenario: Assert "Then the element :selector with the attribute :attribute and the value containing :value should exist" works as expected
    Given I am an anonymous user
    When I visit "/"
    Then the element "html" with the attribute "dir" and the value containing "lt" should exist

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value containing :value should exist" fails as expected when the element does not exist
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then the element "#nonexisting-element" with the attribute "dir" and the value containing "ltr" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "#nonexisting-element" element does not exist.
      """

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value containing :value should exist" fails as expected when the attribute is not found
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then the element "html" with the attribute "no-existing-attribute" and the value containing "ltr" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "no-existing-attribute" attribute does not exist on the element "html".
      """

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value containing :value should exist" fails as expected when the attribute does not contain the partial value
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then the element "html" with the attribute "dir" and the value containing "ltr1" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "dir" attribute exists on the element "html" with a value "ltr", but it does not contain a value "ltr1".
      """

  Scenario: Assert "Then the element :selector with the attribute :attribute and the value :value should not exist" works as expected
    Given I am an anonymous user
    When I visit "/"
    Then the element "html" with the attribute "dir" and the value "nonexistingvalue" should not exist

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value :value should not exist" fails as expected when the element does not exist
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then the element "#nonexisting-element" with the attribute "dir" and the value "ltr" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "#nonexisting-element" element does not exist.
      """

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value :value should not exist" fails as expected when the attribute does not exist
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then the element "html" with the attribute "no-existing-attribute" and the value "ltr" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "no-existing-attribute" attribute does not exist on the element "html".
      """

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value :value should not exist" fails as expected when the attribute does not contain the exact value
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then the element "html" with the attribute "dir" and the value "ltr" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "dir" attribute exists on the element "html" with a value "ltr", but it should not.
      """

  Scenario: Assert "Then the element :selector with the attribute :attribute and the value containing :value should not exist" works as expected
    Given I am an anonymous user
    When I visit "/"
    Then the element "html" with the attribute "dir" and the value containing "nonexistingvalue" should not exist

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value containing :value should not exist" fails as expected when the element does not exist
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then the element "#nonexisting-element" with the attribute "dir" and the value containing "ltr" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "#nonexisting-element" element does not exist.
      """

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value containing :value should not exist" fails as expected when the attribute does not exist
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then the element "html" with the attribute "no-existing-attribute" and the value containing "ltr" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "no-existing-attribute" attribute does not exist on the element "html".
      """

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value containing :value should not exist" fails as expected when the attribute does not contain the exact value
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then the element "html" with the attribute "dir" and the value containing "lt" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "dir" attribute exists on the element "html" with a value containing "lt", but it should not.
      """
