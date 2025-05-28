Feature: Check that ElementTrait works
  As Behat Steps library developer
  I want to provide tools to verify HTML element attributes, properties, and visibility
  So that users can test DOM structure, styling, and UI element behaviors correctly

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

  @javascript @phpserver
  Scenario: Assert click on element
    Given I am on the phpserver test page
    When I click on the element "#overlay-off-canvas-trigger"

  @javascript @phpserver
  Scenario: Assert trigger event on element
    Given I am on the phpserver test page
    Then I should not see an ".overlay-visible" element
    When I trigger the JS event "click" on the element "#overlay-off-canvas-trigger"
    Then I should see an ".overlay-visible" element

  @javascript @phpserver
  Scenario: Assert Accept/Not Accept confirmation
    Given I am on the phpserver test page
    Then I should see the button "Test confirm"
    And I should not see the button "You pressed OK!"
    When I accept all confirmation dialogs
    And I press the "Test confirm" button
    Then I should see the button "You pressed OK!"

  @javascript @phpserver
  Scenario: Assert Not Accept confirmation
    Given I am on the phpserver test page
    Then I should see the button "Test confirm"
    And I should not see the button "You canceled!"
    When I do not accept any confirmation dialogs
    And I press the "Test confirm" button
    Then I should see the button "You canceled!"

  @javascript @phpserver
  Scenario: Assert scroll to an element with selector
    Given I am on the phpserver test page
    When I scroll to the element "#main-inner"
    Then the element "#main-inner" should be at the top of the viewport

  @api @javascript @phpserver
  Scenario: Assert step definition "Then the element :selector should be displayed" succeeds as expected
    When I am on the phpserver test page
    Then the element "#top" should be displayed

  # Here and below: skipped because of Behat hanging in the child process.
  @trait:ElementTrait @skipped
  Scenario: Assert step definition "Then the element :selector should be displayed" fails as expected
    Given some behat configuration
    And scenario steps tagged with "@api @javascript @phpserver":
      """
      When I am on the phpserver test page
      Then the element "#hidden" should not be displayed
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element defined by "#hidden" selector is not visible on the page.
      """

  @api @javascript @phpserver
  Scenario: Assert step definition "Then the element :selector should not be displayed" succeeds as expected
    When I am on the phpserver test page
    Then the element "#hidden" should not be displayed

  @trait:ElementTrait @skipped
  Scenario: Assert step definition "Then the element :selector should not be displayed" fails as expected
    Given some behat configuration
    And scenario steps tagged with "@api @javascript @phpserver":
      """
      Given I am on the phpserver test page
      Then the element "#top" should not be displayed
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element defined by "#top" selector is visible on the page, but should not be.
      """

  @api @javascript @phpserver
  Scenario: Assert step definition "Then the element :selector should not be displayed within a viewport with a top offset of :number pixels" succeeds as expected
    Given I am on the phpserver test page
    Then the element "#hidden" should not be displayed within a viewport with a top offset of 10 pixels

  @api @javascript @phpserver
  Scenario: Assert step definition "Then the element :selector should be displayed within a viewport with a top offset of :number pixels" succeeds as expected
    Given I am on the phpserver test page
    Then the element "#top" should be displayed within a viewport with a top offset of 10 pixels

  @api @javascript @phpserver @skipped
  Scenario: Assert step definition "Then the element :selector should be displayed within a viewport with a top offset of :number pixels" fails as expected
    Given some behat configuration
    And scenario steps tagged with "@api @javascript @phpserver":
      """
      Given I am on the phpserver test page
      Then the element "#top" should be displayed within a viewport with a top offset of 1000 pixels
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element(s) defined by "#top" selector is not displayed within a viewport with a top offset of 1000 pixels.
      """

  @api @javascript @phpserver
  Scenario: Assert step definition "Then the element :selector should be displayed within a viewport" and "Then the element :selector should not be displayed within a viewport" succeeds as expected
    Given I am on the phpserver test page
    Then the element "#top" should be displayed within a viewport
    # Accessibility elements visible to screen readers are visible to normal
    # visibility assertion, but visually hidden.
    And the element "#sr-only" should be displayed
    And the element "#sr-only" should not be displayed within a viewport
    And the element "#sr-only-focusable" should not be displayed within a viewport

  @trait:ElementTrait @skipped
  Scenario: Assert step definition "Then the element :selector should be displayed within a viewport" fails as expected
    Given some behat configuration
    And scenario steps tagged with "@api @javascript":
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/relative.html"
      Then the element "#sr-only" should be displayed within a viewport
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element(s) defined by "#sr-only" selector is not displayed within a viewport.
      """

  @trait:ElementTrait @skipped
  Scenario: Assert step definition "Then the element :selector should not be displayed within a viewport" fails as expected
    Given some behat configuration
    And scenario steps tagged with "@api @javascript":
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/relative.html"
      Then the element "#top" should not be displayed within a viewport
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element(s) defined by "#top" selector is displayed within a viewport, but should not be.
      """

  @api
  Scenario: Text is after another text
    When I go to the homepage
    Then the text "Powered by Drupal" should be after the text "Welcome"

  @api @javascript
  Scenario: Assert "Then the element :selector1 should be after the element :selector2" works as expected
    When I go to the homepage
    Then the element "body" should be after the element "head"
