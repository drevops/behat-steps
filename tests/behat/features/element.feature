Feature: Check that ElementTrait works
  As Behat Steps library developer
  I want to provide tools to verify HTML element attributes, properties, and visibility
  So that users can test DOM structure, styling, and UI element behaviors correctly

  @phpserver
  Scenario: Assert "Then the element :selector with the attribute :attribute and the value :value should exist" works as expected
    Given I am an anonymous user
    When I visit "http://cli:8888/elements.html"
    Then the element "html" with the attribute "dir" and the value "ltr" should exist

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value :value should exist" fails as expected when the element does not exist
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
      Then the element "#nonexisting-element" with the attribute "dir" and the value "ltr" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#nonexisting-element" not found.
      """

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value :value should exist" fails as expected when the attribute does not exist
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
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
    And scenario steps tagged with "@phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
      Then the element "html" with the attribute "dir" and the value "lt" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "dir" attribute exists on the element "html" with a value "ltr", but it does not have a value "lt".
      """

  @phpserver
  Scenario: Assert "Then the element :selector with the attribute :attribute and the value containing :value should exist" works as expected
    Given I am an anonymous user
    When I visit "http://cli:8888/elements.html"
    Then the element "html" with the attribute "dir" and the value containing "lt" should exist

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value containing :value should exist" fails as expected when the element does not exist
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
      Then the element "#nonexisting-element" with the attribute "dir" and the value containing "ltr" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#nonexisting-element" not found.
      """

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value containing :value should exist" fails as expected when the attribute is not found
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
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
    And scenario steps tagged with "@phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
      Then the element "html" with the attribute "dir" and the value containing "ltr1" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "dir" attribute exists on the element "html" with a value "ltr", but it does not contain a value "ltr1".
      """

  @phpserver
  Scenario: Assert "Then the element :selector with the attribute :attribute and the value :value should not exist" works as expected
    Given I am an anonymous user
    When I visit "http://cli:8888/elements.html"
    Then the element "html" with the attribute "dir" and the value "nonexistingvalue" should not exist

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value :value should not exist" fails as expected when the element does not exist
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
      Then the element "#nonexisting-element" with the attribute "dir" and the value "ltr" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#nonexisting-element" not found.
      """

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value :value should not exist" fails as expected when the attribute does not exist
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
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
    And scenario steps tagged with "@phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
      Then the element "html" with the attribute "dir" and the value "ltr" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "dir" attribute exists on the element "html" with a value "ltr", but it should not.
      """

  @phpserver
  Scenario: Assert "Then the element :selector with the attribute :attribute and the value containing :value should not exist" works as expected
    Given I am an anonymous user
    When I visit "http://cli:8888/elements.html"
    Then the element "html" with the attribute "dir" and the value containing "nonexistingvalue" should not exist

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value containing :value should not exist" fails as expected when the element does not exist
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
      Then the element "#nonexisting-element" with the attribute "dir" and the value containing "ltr" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#nonexisting-element" not found.
      """

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value containing :value should not exist" fails as expected when the attribute does not exist
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
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
    And scenario steps tagged with "@phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
      Then the element "html" with the attribute "dir" and the value containing "lt" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "dir" attribute exists on the element "html" with a value containing "lt", but it should not.
      """

  @javascript @phpserver
  Scenario: Assert click on element
    Given I visit "http://cli:8888/elements_relative.html"
    When I click on the element "#overlay-off-canvas-trigger"

  @javascript @phpserver
  Scenario: Assert trigger event on element
    Given I visit "http://cli:8888/elements_relative.html"
    Then I should not see an ".overlay-visible" element
    When I trigger the JS event "click" on the element "#overlay-off-canvas-trigger"
    Then I should see an ".overlay-visible" element

  @javascript @phpserver
  Scenario: Assert Accept/Not Accept confirmation
    Given I visit "http://cli:8888/elements_relative.html"
    Then I should see the button "Test confirm"
    And I should not see the button "You pressed OK!"
    When I accept all confirmation dialogs
    And I press the "Test confirm" button
    Then I should see the button "You pressed OK!"

  @javascript @phpserver
  Scenario: Assert Not Accept confirmation
    Given I visit "http://cli:8888/elements_relative.html"
    Then I should see the button "Test confirm"
    And I should not see the button "You canceled!"
    When I do not accept any confirmation dialogs
    And I press the "Test confirm" button
    Then I should see the button "You canceled!"

  @javascript @phpserver
  Scenario: Assert scroll to an element with selector uses center alignment by default
    Given I visit "http://cli:8888/elements_relative.html"
    When I scroll to the element "#main-inner"
    Then the element "#main-inner" should be centered in the viewport

  @javascript @phpserver
  Scenario: Assert scroll to an element with top alignment when configured
    Given I set scroll to top alignment
    And I visit "http://cli:8888/elements_relative.html"
    When I scroll to the element "#main-inner"
    Then the element "#main-inner" should be at the top of the viewport

  @javascript @phpserver
  Scenario: Assert selectors with quotes in attribute values work correctly
    Given I visit "http://cli:8888/elements_relative.html"
    When I scroll to the element "button[data-action='save']"
    Then the element "button[data-action='save']" should be at the top of the viewport
    When I scroll to the element "button[data-action='delete']"
    Then the element "button[data-action='delete']" should be at the top of the viewport
    When I trigger the JS event "click" on the element "button[data-action='edit']"

  @api @javascript @phpserver
  Scenario: Assert step definition "Then the element :selector should be displayed" succeeds as expected
    When I visit "http://cli:8888/elements_relative.html"
    Then the element "#top" should be displayed

  # Here and below: skipped because of Behat hanging in the child process.
  @trait:ElementTrait @skipped
  Scenario: Assert step definition "Then the element :selector should be displayed" fails as expected
    Given some behat configuration
    And scenario steps tagged with "@api @javascript @phpserver":
      """
      When I visit "http://cli:8888/elements_relative.html"
      Then the element "#hidden" should be displayed
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      None of the elements defined by "#hidden" selector are visible on the page.
      """

  @api @javascript @phpserver
  Scenario: Assert step definition "Then the element :selector should not be displayed" succeeds as expected
    When I visit "http://cli:8888/elements_relative.html"
    Then the element "#hidden" should not be displayed

  @trait:ElementTrait @skipped
  Scenario: Assert step definition "Then the element :selector should not be displayed" fails as expected
    Given some behat configuration
    And scenario steps tagged with "@api @javascript @phpserver":
      """
      Given I visit "http://cli:8888/elements_relative.html"
      Then the element "#top" should not be displayed
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element defined by "#top" selector is visible on the page, but should not be.
      """

  @api @javascript @phpserver
  Scenario: Assert step definition "Then the element :selector should not be displayed within a viewport with a top offset of :number pixels" succeeds as expected
    Given I visit "http://cli:8888/elements_relative.html"
    Then the element "#hidden" should not be displayed within a viewport with a top offset of 10 pixels

  @api @javascript @phpserver
  Scenario: Assert step definition "Then the element :selector should be displayed within a viewport with a top offset of :number pixels" succeeds as expected
    Given I visit "http://cli:8888/elements_relative.html"
    Then the element "#top" should be displayed within a viewport with a top offset of 10 pixels

  @api @javascript @phpserver @skipped
  Scenario: Assert step definition "Then the element :selector should be displayed within a viewport with a top offset of :number pixels" fails as expected
    Given some behat configuration
    And scenario steps tagged with "@api @javascript @phpserver":
      """
      Given I visit "http://cli:8888/elements_relative.html"
      Then the element "#top" should be displayed within a viewport with a top offset of 1000 pixels
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element(s) defined by "#top" selector is not displayed within a viewport with a top offset of 1000 pixels.
      """

  @api @javascript @phpserver
  Scenario: Assert step definition "Then the element :selector should be displayed within a viewport" and "Then the element :selector should not be displayed within a viewport" succeeds as expected
    Given I visit "http://cli:8888/elements_relative.html"
    Then the element "#top" should be displayed within a viewport
    # Accessibility elements visible to screen readers are visible to normal
    # visibility assertion, but visually hidden.
    And the element "#sr-only" should be displayed
    And the element "#sr-only" should not be displayed within a viewport
    And the element "#sr-only-focusable" should not be displayed within a viewport

  @trait:ElementTrait @skipped
  Scenario: Assert step definition "Then the element :selector should be displayed within a viewport" fails as expected
    Given some behat configuration
    And scenario steps tagged with "@api @javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements_relative.html"
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
    And scenario steps tagged with "@api @javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements_relative.html"
      Then the element "#top" should not be displayed within a viewport
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element(s) defined by "#top" selector is displayed within a viewport, but should not be.
      """

  @api @phpserver
  Scenario: Text appears after another text
    When I visit "http://cli:8888/elements.html"
    Then the text "Copyright 2024" should appear after the text "Welcome"

  @api @phpserver
  Scenario: Assert "Then the element :selector1 should appear after the element :selector2" works as expected
    When I visit "http://cli:8888/elements.html"
    Then the element "body" should appear after the element "head"

  @trait:ElementTrait
  Scenario: Assert element order fails when first element is before second
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/elements.html"
      Then the element "head" should appear after the element "body"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element "head" appears before "body".
      """

  @trait:ElementTrait
  Scenario: Assert text order fails when first text is before second
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/elements.html"
      Then the text "Welcome" should appear after the text "Copyright 2024"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Text "Welcome" appears before "Copyright 2024".
      """

  @trait:ElementTrait
  Scenario: Assert element order fails when first element is not found
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/elements.html"
      Then the element "#nonexistent" should appear after the element "body"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#nonexistent" not found.
      """

  @trait:ElementTrait
  Scenario: Assert element order fails when second element is not found
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/elements.html"
      Then the element "body" should appear after the element "#nonexistent"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#nonexistent" not found.
      """

  @trait:ElementTrait
  Scenario: Assert text order fails when first text is not found
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/elements.html"
      Then the text "NonExistentText123" should appear after the text "Welcome"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Text was not found: "NonExistentText123".
      """

  @trait:ElementTrait
  Scenario: Assert text order fails when second text is not found
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/elements.html"
      Then the text "Welcome" should appear after the text "NonExistentText123"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Text was not found: "NonExistentText123".
      """

  @javascript @phpserver
  Scenario: Assert "When I hover over the element :selector" works as expected
    Given I am an anonymous user
    When I visit "http://cli:8888/elements.html"
    Then the element "#hover-reveal" should not be displayed
    When I hover over the element "#hover-target"
    Then the element "#hover-reveal" should be displayed

  @trait:ElementTrait
  Scenario: Assert that "When I hover over the element :selector" fails when element does not exist
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
      And I hover over the element "#nonexistent-element"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#nonexistent-element" not found.
      """

  @javascript @phpserver
  Scenario: Assert "When I focus on the element :selector" works as expected
    Given I am an anonymous user
    When I visit "http://cli:8888/elements.html"
    And I focus on the element "#focus-input"
    Then the element "#focus-input" with the attribute "data-focused" and the value "true" should exist

  @trait:ElementTrait
  Scenario: Assert that "When I focus on the element :selector" fails when element does not exist
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
      And I focus on the element "#nonexistent-element"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#nonexistent-element" not found.
      """

  @javascript @phpserver
  Scenario: Assert "Then the element :selector should have keyboard focus" and its negative form work as expected
    Given I am an anonymous user
    When I visit "http://cli:8888/elements.html"
    And I focus on the element "#focus-input"
    Then the element "#focus-input" should have keyboard focus
    And the element "#focus-button-outline" should not have keyboard focus

  @javascript @phpserver
  Scenario: Assert "Then the element :selector should have a visible focus outline" passes for an element with a CSS outline
    Given I am an anonymous user
    When I visit "http://cli:8888/elements.html"
    Then the element "#focus-button-outline" should have a visible focus outline
    And the element "#focus-button-no-outline" should not have a visible focus outline

  @javascript @phpserver
  Scenario: Assert "Then the element :selector should have a visible focus outline" passes for an element using box-shadow as the indicator
    Given I am an anonymous user
    When I visit "http://cli:8888/elements.html"
    Then the element "#focus-button-shadow" should have a visible focus outline

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector should have keyboard focus" fails when the element does not exist
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
      Then the element "#nonexistent-element" should have keyboard focus
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#nonexistent-element" not found.
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector should have keyboard focus" fails when a different element is focused
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
      And I focus on the element "#focus-input"
      Then the element "#focus-button-outline" should have keyboard focus
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected element "#focus-button-outline" to have keyboard focus, but focus is on:
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector should have keyboard focus" fails when no element is focused
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
      Then the element "#focus-input" should have keyboard focus
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected element "#focus-input" to have keyboard focus, but no element is focused.
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector should not have keyboard focus" fails when the element is focused
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
      And I focus on the element "#focus-input"
      Then the element "#focus-input" should not have keyboard focus
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected element "#focus-input" to not have keyboard focus, but it does.
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector should have a visible focus outline" fails when the element does not exist
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
      Then the element "#nonexistent-element" should have a visible focus outline
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#nonexistent-element" not found.
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector should have a visible focus outline" fails when the element has no visible indicator
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
      Then the element "#focus-button-no-outline" should have a visible focus outline
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected element "#focus-button-no-outline" to have a visible focus outline, but outline-style is "none"
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector should not have a visible focus outline" fails when the element has an outline
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements.html"
      Then the element "#focus-button-outline" should not have a visible focus outline
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected element "#focus-button-outline" to not have a visible focus outline, but outline-style is "solid"
      """

  @javascript @phpserver
  Scenario: Assert click on element works
    Given I visit "http://cli:8888/elements_relative.html"
    When I click on the element "#overlay-trigger"
    Then I should see an ".overlay-visible" element

  @trait:ElementTrait
  Scenario: Assert click on element fails when element not found
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I visit "http://cli:8888/elements_relative.html"
      When I click on the element "#nonexistent-element"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#nonexistent-element" not found.
      """

  @trait:ElementTrait
  Scenario: Assert scroll to element not at top of viewport fails
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I visit "http://cli:8888/elements_relative.html"
      Then the element "#bottom" should be at the top of the viewport
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element with selector "#bottom" is not at the top of the viewport.
      """

  @trait:ElementTrait
  Scenario: Assert element visibility fails when element is not present
    Given some behat configuration
    And scenario steps tagged with "@api @javascript @phpserver":
      """
      Given I visit "http://cli:8888/elements_relative.html"
      Then the element "#nonexistent" should be displayed
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#nonexistent" not found.
      """

  @trait:ElementTrait
  Scenario: Assert element visibility fails when no elements are visible
    Given some behat configuration
    And scenario steps tagged with "@api @javascript @phpserver":
      """
      Given I visit "http://cli:8888/elements_relative.html"
      Then the element "#hidden" should be displayed
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      None of the elements defined by "#hidden" selector are visible on the page.
      """

  @trait:ElementTrait
  Scenario: Assert element not visible fails when element is visible
    Given some behat configuration
    And scenario steps tagged with "@api @javascript @phpserver":
      """
      Given I visit "http://cli:8888/elements_relative.html"
      Then the element "#top" should not be displayed
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element defined by "#top" selector is visible on the page, but should not be.
      """

  @trait:ElementTrait
  Scenario: Assert element visually visible fails when not in viewport
    Given some behat configuration
    And scenario steps tagged with "@api @javascript @phpserver":
      """
      Given I visit "http://cli:8888/elements_relative.html"
      Then the element "#sr-only" should be displayed within a viewport
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element(s) defined by "#sr-only" selector is not displayed within a viewport.
      """

  @trait:ElementTrait
  Scenario: Assert element visually visible with offset fails when not in viewport
    Given some behat configuration
    And scenario steps tagged with "@api @javascript @phpserver":
      """
      Given I visit "http://cli:8888/elements_relative.html"
      Then the element "#top" should be displayed within a viewport with a top offset of 10000 pixels
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element(s) defined by "#top" selector is not displayed within a viewport with a top offset of 10000 pixels.
      """

  @trait:ElementTrait
  Scenario: Assert element not visually visible with offset fails when visible
    Given some behat configuration
    And scenario steps tagged with "@api @javascript @phpserver":
      """
      Given I visit "http://cli:8888/elements_relative.html"
      Then the element "#top" should not be displayed within a viewport with a top offset of 0 pixels
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element(s) defined by "#top" selector is displayed within a viewport with a top offset of 0 pixels, but should not be.
      """

  @trait:ElementTrait
  Scenario: Assert element visually hidden fails when visible in viewport
    Given some behat configuration
    And scenario steps tagged with "@api @javascript @phpserver":
      """
      Given I visit "http://cli:8888/elements_relative.html"
      Then the element "#top" should not be displayed within a viewport
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element(s) defined by "#top" selector is displayed within a viewport, but should not be.
      """

  @javascript @phpserver
  Scenario: Assert "When I click on the element :selector with the index :index" clicks the Nth match
    Given I visit "http://cli:8888/elements_relative.html"
    When I click on the element ".nth-btn" with the index 2
    Then I should see "Clicked 2"

  @javascript @phpserver
  Scenario: Assert "When I press the button :label with the index :index" presses the Nth match
    Given I visit "http://cli:8888/elements_relative.html"
    When I press the button "Repeated action" with the index 3
    Then I should see "Clicked 3"

  @phpserver
  Scenario: Assert "When I follow the link :text with the index :index" follows the Nth match
    When I visit "http://cli:8888/elements.html"
    And I follow the link "Repeated link" with the index 2
    Then I should see "Link Testing Fixture"

  @phpserver
  Scenario: Assert "Then the element :parent should contain :count element(s) matching :selector" counts matches within a parent
    When I visit "http://cli:8888/elements.html"
    Then the element "#nth-parent" should contain 3 elements matching ".nth-child"

  @trait:ElementTrait
  Scenario: Assert index-based interaction fails when the index is below 1
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/elements.html"
      When I click on the element ".nth-child" with the index 0
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The index must be 1 or greater, but "0" was given.
      """

  @trait:ElementTrait
  Scenario: Assert index-based interaction fails when the index is out of range
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/elements.html"
      When I click on the element ".nth-child" with the index 99
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Cannot use the element matching ".nth-child" at index 99: only 3 found.
      """

  @trait:ElementTrait
  Scenario: Assert index-based interaction fails when no element matches
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/elements.html"
      When I click on the element ".does-not-exist" with the index 1
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching ".does-not-exist" not found.
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :parent should contain :count element(s) matching :selector" fails on a count mismatch
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/elements.html"
      Then the element "#nth-parent" should contain 5 elements matching ".nth-child"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected the element "#nth-parent" to contain 5 element(s) matching ".nth-child", but found 3.
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :parent should contain :count element(s) matching :selector" fails when the parent is missing
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      When I visit "http://cli:8888/elements.html"
      Then the element "#does-not-exist" should contain 1 element matching ".nth-child"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#does-not-exist" not found.
      """

  @javascript @phpserver
  Scenario: Assert "Then the element :selector should have the CSS property :property with the value :value" works as expected
    Given I am an anonymous user
    When I visit "http://cli:8888/elements_css.html"
    Then the element "#css-box" should have the CSS property "background-color" with the value "rgb(0, 0, 255)"
    And the element "#css-box" should have the CSS property "backgroundColor" with the value "rgb(0, 0, 255)"
    And the element "#css-box" should have the CSS property "--css-brand" with the value "teal"
    And the element "#css-box" should not have the CSS property "display" with the value "none"
    And the element "#css-hidden" should have the CSS property "display" with the value "none"

  @javascript @phpserver
  Scenario: Assert "Then the element :selector should have the CSS property :property with the value containing :value" works as expected
    Given I am an anonymous user
    When I visit "http://cli:8888/elements_css.html"
    Then the element "#css-box" should have the CSS property "box-shadow" with the value containing "rgb(255, 0, 0)"
    And the element "#css-box" should not have the CSS property "box-shadow" with the value containing "inset"

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector should have the CSS property :property with the value :value" fails when the element does not exist
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements_css.html"
      Then the element "#nonexistent-element" should have the CSS property "display" with the value "block"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#nonexistent-element" not found.
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector should have the CSS property :property with the value :value" fails when the property has no computed value
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements_css.html"
      Then the element "#css-box" should have the CSS property "bogus-property" with the value "block"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSS property "bogus-property" has no computed value on the element "#css-box".
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector should have the CSS property :property with the value :value" fails on a value mismatch
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements_css.html"
      Then the element "#css-box" should have the CSS property "background-color" with the value "rgb(255, 0, 0)"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSS property "background-color" on the element "#css-box" has a computed value "rgb(0, 0, 255)", but it should have a value "rgb(255, 0, 0)".
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector should not have the CSS property :property with the value :value" fails when the value matches
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements_css.html"
      Then the element "#css-box" should not have the CSS property "background-color" with the value "rgb(0, 0, 255)"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The CSS property "background-color" on the element "#css-box" has a computed value "rgb(0, 0, 255)", but it should not.
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector should have the CSS property :property with the value containing :value" fails when the value is not contained
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements_css.html"
      Then the element "#css-box" should have the CSS property "box-shadow" with the value containing "inset"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      but it should contain a value "inset".
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector should not have the CSS property :property with the value containing :value" fails when the value is contained
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements_css.html"
      Then the element "#css-box" should not have the CSS property "box-shadow" with the value containing "rgb(255, 0, 0)"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      containing "rgb(255, 0, 0)", but it should not.
      """

  @javascript @phpserver
  Scenario: Assert "Then the element :selector1 should stack above the element :selector2" compares the z-index of both elements
    Given I am an anonymous user
    When I visit "http://cli:8888/elements_css.html"
    Then the element "#stack-high" should stack above the element "#stack-low"
    And the element "#stack-low" should stack below the element "#stack-high"

  @javascript @phpserver
  Scenario: Assert stacking order resolves the effective z-index across stacking contexts
    Given I am an anonymous user
    When I visit "http://cli:8888/elements_css.html"
    Then the element "#stack-sibling" should stack above the element "#stack-trap-child"
    And the element "#stack-trap-child" should stack below the element "#stack-sibling"

  @javascript @phpserver
  Scenario: Assert stacking order falls back to document order for an equal z-index
    Given I am an anonymous user
    When I visit "http://cli:8888/elements_css.html"
    Then the element "#stack-second" should stack above the element "#stack-first"
    And the element "#stack-first" should stack below the element "#stack-second"

  @javascript @phpserver
  Scenario: Assert stacking order of an element nested in another element
    Given I am an anonymous user
    When I visit "http://cli:8888/elements_css.html"
    Then the element "#stack-child" should stack above the element "#stack-parent"
    And the element "#stack-parent" should stack below the element "#stack-child"
    And the element "#stack-behind" should stack below the element "#stack-parent"
    And the element "#stack-parent" should stack above the element "#stack-behind"

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector1 should stack above the element :selector2" fails when the first element does not exist
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements_css.html"
      Then the element "#nonexistent-element" should stack above the element "#stack-low"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#nonexistent-element" not found.
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector1 should stack above the element :selector2" fails when the second element does not exist
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements_css.html"
      Then the element "#stack-low" should stack above the element "#nonexistent-element"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#nonexistent-element" not found.
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector1 should stack above the element :selector2" fails when both selectors match the same element
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements_css.html"
      Then the element "#stack-low" should stack above the element "#stack-low"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The selectors "#stack-low" and "#stack-low" match the same element.
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector1 should stack above the element :selector2" fails when the element stacks below
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements_css.html"
      Then the element "#stack-low" should stack above the element "#stack-high"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected element "#stack-low" to stack above the element "#stack-high", but it stacks below it: their effective z-indexes are 1 and 5.
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector1 should stack below the element :selector2" fails when the element stacks above
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements_css.html"
      Then the element "#stack-second" should stack below the element "#stack-first"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected element "#stack-second" to stack below the element "#stack-first", but it stacks above it: both have an effective z-index of 0 and "#stack-first" comes earlier in the document.
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector1 should stack above the element :selector2" fails when the first element is nested in the second one
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements_css.html"
      Then the element "#stack-behind" should stack above the element "#stack-parent"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected element "#stack-behind" to stack above the element "#stack-parent", but it stacks below it: "#stack-behind" sits inside the stacking context of "#stack-parent" with an effective z-index of -1.
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector1 should stack above the element :selector2" fails when the second element is nested in the first one
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements_css.html"
      Then the element "#stack-parent" should stack above the element "#stack-child"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected element "#stack-parent" to stack above the element "#stack-child", but it stacks below it: "#stack-child" sits inside the stacking context of "#stack-parent" with an effective z-index of 0.
      """

  @javascript @phpserver
  Scenario: Assert "Then the element :selector should be pinned to the top of the viewport" works as expected
    Given I am an anonymous user
    When I visit "http://cli:8888/elements_css.html"
    Then the element "#pinned-header" should be pinned to the top of the viewport
    And the element "#not-pinned" should not be pinned to the top of the viewport
    And the element "#pinned-hidden" should not be pinned to the top of the viewport

  @javascript @phpserver
  Scenario: Assert "Then the element :selector should be pinned to the top of the viewport within :tolerance pixels" works as expected
    Given I am an anonymous user
    When I visit "http://cli:8888/elements_css.html"
    Then the element "#pinned-offset" should be pinned to the top of the viewport within 25 pixels
    And the element "#pinned-offset" should not be pinned to the top of the viewport

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector should be pinned to the top of the viewport" fails when the element does not exist
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements_css.html"
      Then the element "#nonexistent-element" should be pinned to the top of the viewport
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#nonexistent-element" not found.
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector should be pinned to the top of the viewport" fails when the element is not at the top
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements_css.html"
      Then the element "#not-pinned" should be pinned to the top of the viewport
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected element "#not-pinned" to be pinned to the top of the viewport within 2 pixel(s), but its top edge is at
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector should be pinned to the top of the viewport" fails when the element is not rendered
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements_css.html"
      Then the element "#pinned-hidden" should be pinned to the top of the viewport
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected element "#pinned-hidden" to be pinned to the top of the viewport, but it is not rendered.
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector should not be pinned to the top of the viewport" fails when the element is pinned
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements_css.html"
      Then the element "#pinned-header" should not be pinned to the top of the viewport
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected element "#pinned-header" to not be pinned to the top of the viewport, but its top edge is at
      """

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector should be pinned to the top of the viewport within :tolerance pixels" fails when the tolerance is negative
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "http://cli:8888/elements_css.html"
      Then the element "#pinned-header" should be pinned to the top of the viewport within -5 pixels
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The tolerance must be 0 or greater, but "-5" was given.
      """
