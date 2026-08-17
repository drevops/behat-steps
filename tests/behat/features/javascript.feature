Feature: Check that JavascriptTrait works
  As Behat Steps library developer
  I want to automatically detect JavaScript errors during test execution
  So that users can catch JS errors in their scenarios by default

  @javascript
  Scenario: Clean page without JavaScript errors should pass
    Given I visit "/sites/default/files/javascript_clean1.html"
    Then I should see "Clean Page 1 Without JavaScript Errors"
    And I should see "Page 1 JavaScript is working correctly!"
    When I press "Click to update message"
    Then I should see "Message on page 1 updated successfully!"

  @javascript
  Scenario: Moving between pages without JavaScript errors should pass
    Given I visit "/sites/default/files/javascript_clean1.html"
    Then I should see "Clean Page 1 Without JavaScript Errors"
    And I should see "Page 1 JavaScript is working correctly!"
    When I press "Click to update message"
    Then I should see "Message on page 1 updated successfully!"

    Given I visit "/sites/default/files/javascript_clean2.html"
    Then I should see "Clean Page 2 Without JavaScript Errors"
    And I should see "Page 2 JavaScript is working correctly!"
    When I press "Click to update message"
    Then I should see "Message on page 2 updated successfully!"

  @trait:JavascriptTrait
  Scenario: Page with JavaScript errors should fail
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      Given I visit "/sites/default/files/javascript_errors1.html"
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
      URL: http://nginx:8080/sites/default/files/javascript_errors1.html
      """
    And the output should contain:
      """
      - Error: Error page 1 - console.error triggered by button
      """
    And the output should contain:
      """
      Total errors: 1
      """
    And the output should not contain:
      """
      - Error: Error page 1 - console.error triggered after 1000ms
      """
    And the output should not contain:
      """
      - Error: Error page 1 - console.error triggered after 2000ms
      """

  @trait:JavascriptTrait
  Scenario: Page with JavaScript errors should fail after longer wait with more errors
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      Given I visit "/sites/default/files/javascript_errors1.html"
      Then I should see "Page 1 with JavaScript Errors"
      When I press "Click to trigger error"
      And sleep for 4 seconds
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      JavaScript errors detected
      """
    And the output should contain:
      """
      URL: http://nginx:8080/sites/default/files/javascript_errors1.html
      """
    And the output should contain:
      """
      - Error: Error page 1 - console.error triggered by button
      """
    And the output should contain:
      """
      - Error: Error page 1 - console.error triggered after 1000ms
      """
    And the output should contain:
      """
      - Error: Error page 1 - console.error triggered after 2000ms
      """
    And the output should contain:
      """
      Total errors: 4
      """

  @trait:JavascriptTrait
  Scenario: Multiple pages - errors from different pages are tracked separately
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      Given I visit "/sites/default/files/javascript_errors1.html"
      Then I should see "Page 1 with JavaScript Errors"
      When I press "Click to trigger error"
      And sleep for 4 seconds

      When I visit "/sites/default/files/javascript_errors2.html"
      Then I should see "Page 2 with JavaScript Errors"
      When I press "Click to trigger error"
      And sleep for 4 seconds
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      JavaScript errors detected
      """
    And the output should contain:
      """
      URL: http://nginx:8080/sites/default/files/javascript_errors1.html
      """
    And the output should contain:
      """
      - Error: Error page 1 - console.error triggered by button
      """
    And the output should contain:
      """
      - Error: Error page 1 - console.error triggered after 1000ms
      """
    And the output should contain:
      """
      - Error: Error page 1 - console.error triggered after 2000ms
      """
    And the output should contain:
      """
      URL: http://nginx:8080/sites/default/files/javascript_errors2.html
      """
    And the output should contain:
      """
      - Error: Error page 2 - console.error triggered by button
      """
    And the output should contain:
      """
      - Error: Error page 2 - console.error triggered after 1000ms
      """
    And the output should contain:
      """
      - Error: Error page 2 - console.error triggered after 2000ms
      """
    And the output should contain:
      """
      Total errors: 8
      """

  # The assertions below record current behaviour, which is broken: the run
  # exits non-zero, but the failure is raised from an AfterScenario hook and
  # Behat dispatches that event before folding the teardown into the scenario
  # result. The summary therefore reports every scenario as passed, nothing
  # reaches the rerun cache, and "--rerun" runs the whole suite again.
  @trait:JavascriptTrait
  Scenario: Rerun after a scenario fails on a JavaScript error
    Given some behat configuration
    And a file named "features/stub.feature" with:
      """
      Feature: Stub feature
        @javascript
        Scenario: Passing scenario before the failure
          Given I visit "/sites/default/files/javascript_clean1.html"

        @javascript
        Scenario: Scenario with a JavaScript error
          Given I visit "/sites/default/files/javascript_errors1.html"
          When I press "Click to trigger error"

        @javascript
        Scenario: Passing scenario after the failure
          Given I visit "/sites/default/files/javascript_clean2.html"
      """
    When I run "behat --no-colors"
    Then it should fail with:
      """
      3 scenarios (3 passed)
      """
    When I run "behat --no-colors --rerun"
    Then it should fail with:
      """
      3 scenarios (3 passed)
      """

  @javascript @js-errors
  Scenario: Bypass tag allows page with errors to pass
    Given I visit "/sites/default/files/javascript_errors1.html"
    Then I should see "Page 1 with JavaScript Errors"
    When I press "Click to trigger error"
    And sleep for 4 second

  @javascript @trait:JavascriptTrait @behat-steps-skip:JavascriptTrait
  Scenario: Skip tag allows bypassing error checking
    Given I visit "/sites/default/files/javascript_errors1.html"
    Then I should see "Page 1 with JavaScript Errors"
    When I press "Click to trigger error"
    And sleep for 4 second

  Scenario: Non-JavaScript scenario should not check for errors
    Given I visit "/sites/default/files/javascript_errors1.html"
    Then I should see "Page 1 with JavaScript Errors"
