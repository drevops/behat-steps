@visibility
Feature: Check that VisibilityTrait works

  @api @javascript @phpserver
  Scenario: Assert step definition "Then /^(?:|I )should see a visible "(?P<selector>[^"]*)" element" succeeds as expected
    Given I am on the phpserver test page
    Then I should see a visible "#top" element

  # Here and below: skipped because of Behat hanging in the child process.
  @trait:VisibilityTrait @skipped
  Scenario: Assert step definition "Then /^(?:|I )should see a visible "(?P<selector>[^"]*)" element" fails as expected
    Given some behat configuration
    And scenario steps tagged with "@api @javascript @phpserver":
      """
      Given I am on the phpserver test page
      Then I should see a visible "#hidden" element
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element defined by "#hidden" selector is not visible on the page.
      """

  @api @javascript @phpserver
  Scenario: Assert step definition "Then /^(?:|I )should not see a visible "(?P<selector>[^"]*)" element" succeeds as expected
    Given I am on the phpserver test page
    Then I should not see a visible "#hidden" element

  @trait:VisibilityTrait @skipped
  Scenario: Assert step definition "Then /^(?:|I )should not see a visible "(?P<selector>[^"]*)" element" fails as expected
    Given some behat configuration
    And scenario steps tagged with "@api @javascript @phpserver":
      """
      Given I am on the phpserver test page
      Then I should not see a visible "#top" element
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element defined by "#top" selector is visible on the page, but should not be.
      """

  @api @javascript @phpserver
  Scenario: Assert step definition "Then /^(?:|I )should see a visually visible "(?P<selector>[^"]*)" element" and "Then /^(?:|I )should not see a visually hidden "(?P<selector>[^"]*)" element" succeeds as expected
    Given I am on the phpserver test page
    Then I should see a visually visible "#top" element
    # Accessibility element visible to screen reader are visible to normal
    # visibility assertion, but visually hidden.
    And I should see a visible "#sr-only" element
    And I should not see a visually hidden "#sr-only" element
    And I should not see a visually hidden "#sr-only-focusable" element

  @trait:VisibilityTrait @skipped
  Scenario: Assert step definition "Then /^(?:|I )should see a visually visible "(?P<selector>[^"]*)" element" fails as expected
    Given some behat configuration
    And scenario steps tagged with "@api @javascript":
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/relative.html"
      Then I should see a visually visible "#sr-only" element
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element(s) defined by "#sr-only" selector is not visually visible on the page.
      """

  @trait:VisibilityTrait @skipped
  Scenario: Assert step definition "Then /^(?:|I )should not see a visually hidden "(?P<selector>[^"]*)" element" fails as expected
    Given some behat configuration
    And scenario steps tagged with "@api @javascript":
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/relative.html"
      Then I should not see a visually hidden "#top" element
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element(s) defined by "#top" selector is visually visible on the page, but should not be.
      """
