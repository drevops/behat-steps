Feature: Check that ElementTrait works
  As Behat Steps library developer
  I want to provide tools to verify HTML element attributes, properties, and visibility
  So that users can test DOM structure, styling, and UI element behaviors correctly

  Scenario: Assert "Then the element :selector with the attribute :attribute and the value :value should exist" works as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/elements.html"
    Then the element "html" with the attribute "dir" and the value "ltr" should exist

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value :value should exist" fails as expected when the element does not exist
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/elements.html"
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
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/elements.html"
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
      When I visit "/sites/default/files/elements.html"
      Then the element "html" with the attribute "dir" and the value "lt" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "dir" attribute exists on the element "html" with a value "ltr", but it does not have a value "lt".
      """

  Scenario: Assert "Then the element :selector with the attribute :attribute and the value containing :value should exist" works as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/elements.html"
    Then the element "html" with the attribute "dir" and the value containing "lt" should exist

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value containing :value should exist" fails as expected when the element does not exist
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/elements.html"
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
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/elements.html"
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
      When I visit "/sites/default/files/elements.html"
      Then the element "html" with the attribute "dir" and the value containing "ltr1" should exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "dir" attribute exists on the element "html" with a value "ltr", but it does not contain a value "ltr1".
      """

  Scenario: Assert "Then the element :selector with the attribute :attribute and the value :value should not exist" works as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/elements.html"
    Then the element "html" with the attribute "dir" and the value "nonexistingvalue" should not exist

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value :value should not exist" fails as expected when the element does not exist
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/elements.html"
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
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/elements.html"
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
      When I visit "/sites/default/files/elements.html"
      Then the element "html" with the attribute "dir" and the value "ltr" should not exist
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      The "dir" attribute exists on the element "html" with a value "ltr", but it should not.
      """

  Scenario: Assert "Then the element :selector with the attribute :attribute and the value containing :value should not exist" works as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/elements.html"
    Then the element "html" with the attribute "dir" and the value containing "nonexistingvalue" should not exist

  @trait:ElementTrait
  Scenario: Negative assertion for "Then the element :selector with the attribute :attribute and the value containing :value should not exist" fails as expected when the element does not exist
    Given some behat configuration
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/elements.html"
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
    And scenario steps:
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/elements.html"
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
      When I visit "/sites/default/files/elements.html"
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
  Scenario: Assert scroll to an element with selector uses center alignment by default
    Given I am on the phpserver test page
    When I scroll to the element "#main-inner"
    Then the element "#main-inner" should be centered in the viewport

  @javascript @phpserver
  Scenario: Assert scroll to an element with top alignment when configured
    Given I set scroll to top alignment
    And I am on the phpserver test page
    When I scroll to the element "#main-inner"
    Then the element "#main-inner" should be at the top of the viewport

  @javascript @phpserver
  Scenario: Assert selectors with quotes in attribute values work correctly
    Given I am on the phpserver test page
    When I scroll to the element "button[data-action='save']"
    Then the element "button[data-action='save']" should be at the top of the viewport
    When I scroll to the element "button[data-action='delete']"
    Then the element "button[data-action='delete']" should be at the top of the viewport
    When I trigger the JS event "click" on the element "button[data-action='edit']"

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
      Then the element "#hidden" should be displayed
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      None of the elements defined by "#hidden" selector are visible on the page.
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
      When I visit "/sites/default/files/elements_relative.html"
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
      When I visit "/sites/default/files/elements_relative.html"
      Then the element "#top" should not be displayed within a viewport
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element(s) defined by "#top" selector is displayed within a viewport, but should not be.
      """

  @api
  Scenario: Text appears after another text
    When I visit "/sites/default/files/elements.html"
    Then the text "Copyright 2024" should appear after the text "Welcome"

  @api
  Scenario: Assert "Then the element :selector1 should appear after the element :selector2" works as expected
    When I visit "/sites/default/files/elements.html"
    Then the element "body" should appear after the element "head"

  @trait:ElementTrait
  Scenario: Assert element order fails when first element is before second
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/elements.html"
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
    And scenario steps:
      """
      When I visit "/sites/default/files/elements.html"
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
    And scenario steps:
      """
      When I visit "/sites/default/files/elements.html"
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
    And scenario steps:
      """
      When I visit "/sites/default/files/elements.html"
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
    And scenario steps:
      """
      When I visit "/sites/default/files/elements.html"
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
    And scenario steps:
      """
      When I visit "/sites/default/files/elements.html"
      Then the text "Welcome" should appear after the text "NonExistentText123"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Text was not found: "NonExistentText123".
      """

  @javascript
  Scenario: Assert "When I hover over the element :selector" works as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/elements.html"
    Then the element "#hover-reveal" should not be displayed
    When I hover over the element "#hover-target"
    Then the element "#hover-reveal" should be displayed

  @trait:ElementTrait
  Scenario: Assert that "When I hover over the element :selector" fails when element does not exist
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/elements.html"
      And I hover over the element "#nonexistent-element"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#nonexistent-element" not found.
      """

  @javascript
  Scenario: Assert "When I focus on the element :selector" works as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/elements.html"
    And I focus on the element "#focus-input"
    Then the element "#focus-input" with the attribute "data-focused" and the value "true" should exist

  @trait:ElementTrait
  Scenario: Assert that "When I focus on the element :selector" fails when element does not exist
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/elements.html"
      And I focus on the element "#nonexistent-element"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#nonexistent-element" not found.
      """

  @javascript
  Scenario: Assert "Then the element :selector should have keyboard focus" and its negative form work as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/elements.html"
    And I focus on the element "#focus-input"
    Then the element "#focus-input" should have keyboard focus
    And the element "#focus-button-outline" should not have keyboard focus

  @javascript
  Scenario: Assert "Then the element :selector should have a visible focus outline" passes for an element with a CSS outline
    Given I am an anonymous user
    When I visit "/sites/default/files/elements.html"
    Then the element "#focus-button-outline" should have a visible focus outline
    And the element "#focus-button-no-outline" should not have a visible focus outline

  @javascript
  Scenario: Assert "Then the element :selector should have a visible focus outline" passes for an element using box-shadow as the indicator
    Given I am an anonymous user
    When I visit "/sites/default/files/elements.html"
    Then the element "#focus-button-shadow" should have a visible focus outline

  @trait:ElementTrait
  Scenario: Assert "Then the element :selector should have keyboard focus" fails when the element does not exist
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/elements.html"
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
      When I visit "/sites/default/files/elements.html"
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
      When I visit "/sites/default/files/elements.html"
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
      When I visit "/sites/default/files/elements.html"
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
      When I visit "/sites/default/files/elements.html"
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
      When I visit "/sites/default/files/elements.html"
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
      When I visit "/sites/default/files/elements.html"
      Then the element "#focus-button-outline" should not have a visible focus outline
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Expected element "#focus-button-outline" to not have a visible focus outline, but outline-style is "solid"
      """

  @javascript @phpserver
  Scenario: Assert click on element works
    Given I am on the phpserver test page
    When I click on the element "#overlay-trigger"
    Then I should see an ".overlay-visible" element

  @trait:ElementTrait
  Scenario: Assert click on element fails when element not found
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am on the phpserver test page
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
      Given I am on the phpserver test page
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
      Given I am on the phpserver test page
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
      Given I am on the phpserver test page
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
      Given I am on the phpserver test page
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
      Given I am on the phpserver test page
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
      Given I am on the phpserver test page
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
      Given I am on the phpserver test page
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
      Given I am on the phpserver test page
      Then the element "#top" should not be displayed within a viewport
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element(s) defined by "#top" selector is displayed within a viewport, but should not be.
      """

  @javascript @phpserver
  Scenario: Assert "When I click on the element :selector with the index :index" clicks the Nth match
    Given I am on the phpserver test page
    When I click on the element ".nth-btn" with the index 2
    Then I should see "Clicked 2"

  @javascript @phpserver
  Scenario: Assert "When I press the button :label with the index :index" presses the Nth match
    Given I am on the phpserver test page
    When I press the button "Repeated action" with the index 3
    Then I should see "Clicked 3"

  @api
  Scenario: Assert "When I follow the link :text with the index :index" follows the Nth match
    When I visit "/sites/default/files/elements.html"
    And I follow the link "Repeated link" with the index 2
    Then I should see "Link Testing Fixture"

  @api
  Scenario: Assert "Then the element :parent should contain :count element(s) matching :selector" counts matches within a parent
    When I visit "/sites/default/files/elements.html"
    Then the element "#nth-parent" should contain 3 elements matching ".nth-child"

  @trait:ElementTrait
  Scenario: Assert index-based interaction fails when the index is below 1
    Given some behat configuration
    And scenario steps:
      """
      When I visit "/sites/default/files/elements.html"
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
    And scenario steps:
      """
      When I visit "/sites/default/files/elements.html"
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
    And scenario steps:
      """
      When I visit "/sites/default/files/elements.html"
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
    And scenario steps:
      """
      When I visit "/sites/default/files/elements.html"
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
    And scenario steps:
      """
      When I visit "/sites/default/files/elements.html"
      Then the element "#does-not-exist" should contain 1 element matching ".nth-child"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element matching css "#does-not-exist" not found.
      """
