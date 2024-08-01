@api
Feature: Check that ElementTrait works

  Scenario: Assert step definition "Then I should see the :selector element with the :attribute attribute set to :value" works as expected
    Given I am an anonymous user
    When I visit "/"
    Then I should see the "html" element with the "dir" attribute set to "ltr"

  @trait:ElementTrait
  Scenario: Assert that an element with selector and attribute with a value exists.
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then I should see the "#invalid-element" element with the "dir" attribute set to "ltr"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "#invalid-element" element was not found on the page.
      """

  @trait:ElementTrait
  Scenario: Assert that an element with selector and attribute with a value exists.
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then I should see the "html" element with the "no-existing-attribute" attribute set to "ltr"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "no-existing-attribute" attribute was not found on the element "html".
      """

  @trait:ElementTrait
  Scenario: Assert that an element with selector and attribute with a value exists.
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then I should see the "html" element with the "dir" attribute set to "not-match-value"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "dir" attribute was found on the element "html", but does not contain a value "not-match-value".
      """

  Scenario: Assert step definition "I( should) see the :selector element with a(n) :attribute attribute containing :value" works as expected
    Given I am an anonymous user
    When I visit "/"
    Then I should see the "html" element with a "dir" attribute containing "ltr"

  Scenario: Assert step definition "I( should) see the :selector element with a(n) :attribute attribute containing :value" works as expected
    Given I am an anonymous user
    When I visit "/"
    Then I should see the "html" element with a "dir" attribute containing "lt*"

  @trait:ElementTrait
  Scenario: Assert that an element with selector and attribute with a value exists.
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then I should see the "#invalid-element" element with a "dir" attribute containing "ltr"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "#invalid-element" element was not found on the page.
      """

  @trait:ElementTrait
  Scenario: Assert that an element with selector and attribute with a value exists.
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then I should see the "html" element with a "no-existing-attribute" attribute containing "ltr"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "no-existing-attribute" attribute was not found on the element "html".
      """

  @trait:ElementTrait
  Scenario: Assert that an element with selector and attribute with a value exists.
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then I should see the "html" element with a "dir" attribute containing "not-match-value"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      No element with "dir" attribute matching the pattern "not-match-value" found.
      """

  Scenario: Assert that an element with selector contains text.
    Given I am an anonymous user
    When I visit "/"
    Then I should see an element ".site-branding__name" using "css" contains "Drush Site-Install" text

  Scenario: Assert that an element with selector contains text.
    Given I am an anonymous user
    When I visit "/"
    Then I should see an element "//div[@class='site-branding__name']" using "xpath" contains "Drush Site-Install" text

  @trait:ElementTrait
  Scenario: Assert that an element with selector contains text.
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then I should see an element "//div[@class='site-branding__name']" using "invalid-selector-type" contains "Drush Site-Install" text
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Selector type must be "css" or "xpath".
      """

  @trait:ElementTrait
  Scenario: Assert that an element with selector contains text.
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then I should see an element "//div[@class='site-branding__name_invalid']" using "xpath" contains "Drush Site-Install" text
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Tag matching xpath "//div[@class='site-branding__name_invalid']" not found.
      """

  @trait:ElementTrait
  Scenario: Assert that an element with selector contains text.
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/"
      Then I should see an element "//div[@class='site-branding__name']" using "xpath" contains "Drush Site-Install Fail" text
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The text "Drush Site-Install Fail" was not found in the element "//div[@class='site-branding__name']" using xpath.
      """
