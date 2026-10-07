Feature: Check that IframeTrait works
  As Behat Steps library developer
  I want to provide tools to switch between iframes
  So that users can test content inside iframes

  @javascript @phpserver
  Scenario: Assert "When I switch to the iframe :selector" works for named iframe
    Given the user is anonymous
    When I visit "http://cli:8888/iframes.html"
    And I switch to the iframe ".named-iframe"
    Then I should see "Content inside named iframe"
    When I switch to the root document
    Then I should see "Content in the root document"

  @javascript @phpserver
  Scenario: Assert "When I switch to the iframe :selector" works for unnamed iframe
    Given the user is anonymous
    When I visit "http://cli:8888/iframes.html"
    And I switch to the iframe ".unnamed-iframe"
    Then I should see "Content inside unnamed iframe"
    When I switch to the root document
    Then I should see "Content in the root document"

  @test-trait:IframeTrait
  Scenario: Assert that "When I switch to the iframe :selector" fails when iframe does not exist
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/iframes.html"
      And I switch to the iframe ".nonexistent-iframe"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Iframe matching css ".nonexistent-iframe" not found.
      """

  @test-trait:IframeTrait
  Scenario: Assert that switching iframes fails naming the capability on a driver that runs no JavaScript
    Given some behat configuration
    And scenario steps tagged with "@phpserver":
      """
      Given the user is anonymous
      When I visit "http://cli:8888/iframes.html"
      And I switch to the iframe ".named-iframe"
      """
    When I run "behat --no-colors"
    Then it should fail with a "Behat\Mink\Exception\UnsupportedDriverActionException" exception:
      """
      No browser capability "DrevOps\BehatSteps\Behat\Mink\Capability\JavascriptCapabilityInterface" is available for
      """
