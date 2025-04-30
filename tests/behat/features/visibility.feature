@visibility
Feature: Check that VisibilityTrait works

  @api @javascript @phpserver
  Scenario: Assert step definition "Then the element :selector should be displayed" succeeds as expected
    Given I am on the phpserver test page
    Then the element "#top" should be displayed

  # Here and below: skipped because of Behat hanging in the child process.
  @trait:VisibilityTrait @skipped
  Scenario: Assert step definition "Then the element :selector should be displayed" fails as expected
    Given some behat configuration
    And scenario steps tagged with "@api @javascript @phpserver":
      """
      Given I am on the phpserver test page
      Then the element "#hidden" should not be displayed
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element defined by "#hidden" selector is not visible on the page.
      """

  @api @javascript @phpserver
  Scenario: Assert step definition "Then the element :selector should not be displayed" succeeds as expected
    Given I am on the phpserver test page
    Then the element "#hidden" should not be displayed

  @trait:VisibilityTrait @skipped
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
    Then the element "#sr-only" should not be displayed within a viewport
    Then the element "#sr-only-focusable" should not be displayed within a viewport

  @trait:VisibilityTrait @skipped
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

  @trait:VisibilityTrait @skipped
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
