@d7 @d8 @d9
Feature: Check that VisibilityTrait works

  @api @javascript
  Scenario: Assert step definition "Then /^(?:|I )should see a visible "(?P<selector>[^"]*)" element" succeeds as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/relative.html"
    Then I should see a visible "#top" element

  @trait:VisibilityTrait
  Scenario: Assert step definition "Then /^(?:|I )should see a visible "(?P<selector>[^"]*)" element" fails as expected
    Given some behat configuration
    And scenario steps tagged with "@api @javascript":
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/relative.html"
      Then I should see a visible "#hidden" element
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element defined by "#hidden" selector is not visible on the page.
      """

  @api @javascript
  Scenario: Assert step definition "Then /^(?:|I )should not see a visible "(?P<selector>[^"]*)" element" succeeds as expected
    Given I am an anonymous user
    When I visit "/sites/default/files/relative.html"
    Then I should not see a visible "#hidden" element

  @trait:VisibilityTrait
  Scenario: Assert step definition "Then /^(?:|I )should not see a visible "(?P<selector>[^"]*)" element" fails as expected
    Given some behat configuration
    And scenario steps tagged with "@api @javascript":
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/relative.html"
      Then I should not see a visible "#top" element
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Element defined by "#top" selector is visible on the page, but should not be.
      """
