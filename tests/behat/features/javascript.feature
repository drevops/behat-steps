Feature: Check that JavascriptTrait works
  As Behat Steps library developer
  I want to automatically detect JavaScript errors during test execution
  So that users can catch JS errors in their scenarios by default

  @javascript @phpserver
  Scenario: Clean page without JavaScript errors should pass
    Given I visit "http://cli:8888/javascript_clean1.html"
    Then I should see "Clean Page 1 Without JavaScript Errors"
    And I should see "Page 1 JavaScript is working correctly!"
    When I press "Click to update message"
    Then I should see "Message on page 1 updated successfully!"

  @javascript @phpserver
  Scenario: Moving between pages without JavaScript errors should pass
    Given I visit "http://cli:8888/javascript_clean1.html"
    Then I should see "Clean Page 1 Without JavaScript Errors"
    And I should see "Page 1 JavaScript is working correctly!"
    When I press "Click to update message"
    Then I should see "Message on page 1 updated successfully!"

    Given I visit "http://cli:8888/javascript_clean2.html"
    Then I should see "Clean Page 2 Without JavaScript Errors"
    And I should see "Page 2 JavaScript is working correctly!"
    When I press "Click to update message"
    Then I should see "Message on page 2 updated successfully!"

  @trait:JavascriptTrait
  Scenario: Page with JavaScript errors should fail
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I visit "http://cli:8888/javascript_errors1.html"
      Then I should see "Page 1 with JavaScript Errors"
      When I press "Click to trigger error"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      JavaScript errors detected
      """
    And the output should contain:
      """
      URL: http://cli:8888/javascript_errors1.html
      """
    And the output should contain:
      """
      - Error: Error page 1 - console.error triggered by button
      """

  @trait:JavascriptTrait
  Scenario: All errors collected during a step are reported together
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I visit "http://cli:8888/javascript_errors3.html"
      When I press "Click to trigger errors"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      JavaScript errors detected
      """
    And the output should contain:
      """
      URL: http://cli:8888/javascript_errors3.html
      """
    And the output should contain:
      """
      - Error: Error page 3 - first console.error triggered by button
      """
    And the output should contain:
      """
      - Error: Error page 3 - second console.error triggered by button
      """
    And the output should contain:
      """
      - Error: Error page 3 - third console.error triggered by button
      """
    And the output should contain:
      """
      Total errors: 3
      """

  @trait:JavascriptTrait
  Scenario: Errors from different pages are tracked separately
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I visit "http://cli:8888/javascript_errors3.html"
      When I press "Click to trigger errors"
      And I visit "http://cli:8888/javascript_errors2.html"
      And I press "Click to trigger error"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      JavaScript errors detected
      """
    And the output should contain:
      """
      URL: http://cli:8888/javascript_errors3.html
      """
    And the output should contain:
      """
      - Error: Error page 3 - first console.error triggered by button
      """
    And the output should contain:
      """
      URL: http://cli:8888/javascript_errors2.html
      """
    And the output should contain:
      """
      - Error: Error page 2 - console.error triggered by button
      """
    And the output should contain:
      """
      Total errors: 4
      """

  @trait:JavascriptTrait
  Scenario: Errors are reported when an earlier step failed
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I visit "http://cli:8888/javascript_errors3.html"
      When I press "Click to trigger errors"
      Then I should see "text that is not on the page"
      And I visit "http://cli:8888/javascript_clean1.html"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      JavaScript errors detected
      """
    And the output should contain:
      """
      Total errors: 3
      """

  @trait:JavascriptTrait
  Scenario: Rerun after a scenario fails on a JavaScript error
    Given some behat configuration
    And a file named "features/stub.feature" with:
      """
      Feature: Stub feature
        @javascript @phpserver
        Scenario: Passing scenario before the failure
          Given I visit "http://cli:8888/javascript_clean1.html"

        @javascript @phpserver
        Scenario: Scenario with a JavaScript error
          Given I visit "http://cli:8888/javascript_errors1.html"
          When I press "Click to trigger error"

        @javascript @phpserver
        Scenario: Passing scenario after the failure
          Given I visit "http://cli:8888/javascript_clean2.html"
      """
    When I run "behat --no-colors"
    Then it should fail with:
      """
      3 scenarios (2 passed, 1 failed)
      """
    When I run "behat --no-colors --rerun"
    Then it should fail with:
      """
      1 scenario (1 failed)
      """

  @javascript @js-errors @phpserver
  Scenario: Bypass tag allows page with errors to pass
    Given I visit "http://cli:8888/javascript_errors1.html"
    Then I should see "Page 1 with JavaScript Errors"
    When I press "Click to trigger error"
    And sleep for 4 second

  @javascript @trait:JavascriptTrait @behat-steps-skip:JavascriptTrait @phpserver
  Scenario: Skip tag allows bypassing error checking
    Given I visit "http://cli:8888/javascript_errors1.html"
    Then I should see "Page 1 with JavaScript Errors"
    When I press "Click to trigger error"
    And sleep for 4 second

  @phpserver
  Scenario: Non-JavaScript scenario should not check for errors
    Given I visit "http://cli:8888/javascript_errors1.html"
    Then I should see "Page 1 with JavaScript Errors"
