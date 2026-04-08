Feature: Check that IframeTrait works
  As Behat Steps library developer
  I want to provide tools to switch between iframes
  So that users can test content inside iframes

  @javascript @phpserver
  Scenario: Assert "When I switch to iframe with locator :locator" works for named iframe
    Given I am an anonymous user
    When I visit "/sites/default/files/iframes.html"
    And I switch to iframe with locator ".named-iframe"
    Then I should see "Content inside named iframe"
    When I switch to the root document
    Then I should see "Content in the root document"

  @javascript @phpserver
  Scenario: Assert "When I switch to iframe with locator :locator" works for unnamed iframe
    Given I am an anonymous user
    When I visit "/sites/default/files/iframes.html"
    And I switch to iframe with locator ".unnamed-iframe"
    Then I should see "Content inside unnamed iframe"
    When I switch to the root document
    Then I should see "Content in the root document"

  @trait:IframeTrait
  Scenario: Assert that "When I switch to iframe with locator :locator" fails when iframe does not exist
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I am an anonymous user
      When I visit "/sites/default/files/iframes.html"
      And I switch to iframe with locator ".nonexistent-iframe"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Iframe matching css ".nonexistent-iframe" not found.
      """
