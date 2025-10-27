Feature: Check that JavascriptTrait works
  As Behat Steps library developer
  I want to automatically detect JavaScript errors during test execution
  So that users can catch JS errors in their scenarios by default

  @javascript
  Scenario: Clean page without JavaScript errors should pass
    When I visit "/sites/default/files/clean.html"
    Then I should see "Clean Page Without JavaScript Errors"

  @trait:JavascriptTrait
  Scenario: Page with JavaScript errors should fail automatically
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      When I visit "/sites/default/files/errors.html"
      Then I should see "Page with JavaScript Errors"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      JavaScript errors detected
      """

  @javascript @js-errors
  Scenario: Bypass tag allows page with errors to pass
    When I visit "/sites/default/files/errors.html"
    Then I should see "Page with JavaScript Errors"
    # This scenario should pass even though page has JS errors

  Scenario: Non-JavaScript scenario should not check for errors
    When I visit "/sites/default/files/errors.html"
    Then I should see "Page with JavaScript Errors"
    # This scenario should pass because it doesn't have @javascript tag

  @javascript
  Scenario: URL change detection - multiple visits collect errors
    When I visit "/sites/default/files/clean.html"
    Then I should see "Clean Page Without JavaScript Errors"
    When I visit "/sites/default/files/clean.html"
    Then I should see "Clean Page Without JavaScript Errors"
    # Multiple clean page visits should all pass

  @trait:JavascriptTrait
  Scenario: Multiple pages - errors from different pages are tracked separately
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      When I visit "/sites/default/files/clean.html"
      Then I should see "Clean Page"
      When I visit "/sites/default/files/errors.html"
      Then I should see "Page with JavaScript Errors"
      When I visit "/sites/default/files/errors2.html"
      Then I should see "Second Page with Different JavaScript Errors"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      JavaScript errors detected
      """
    And it should fail with an error:
      """
      errors.html
      """
    And it should fail with an error:
      """
      errors2.html
      """

  @trait:JavascriptTrait
  Scenario: Console.error messages are captured
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      When I visit "/sites/default/files/errors.html"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      console.error
      """

  @javascript @trait:JavascriptTrait @behat-steps-skip:javascriptAfterScenario
  Scenario: Skip hook allows bypassing error checking
    When I visit "/sites/default/files/errors.html"
    Then I should see "Page with JavaScript Errors"
    # This scenario should pass because afterScenario hook is skipped

  @javascript @trait:JavascriptTrait @behat-steps-skip:JavascriptTrait
  Scenario: Skip trait tag disables all hooks
    When I visit "/sites/default/files/errors.html"
    Then I should see "Page with JavaScript Errors"
    # This scenario should pass because entire trait is skipped

  @trait:JavascriptTrait
  Scenario: Navigation between pages collects errors from each page
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      When I visit "/"
      When I visit "/sites/default/files/errors.html"
      Then I should see "Page with JavaScript Errors"
      When I visit "/sites/default/files/clean.html"
      Then I should see "Clean Page"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      JavaScript errors detected
      """

  @trait:JavascriptTrait
  Scenario: Bypass tag can be used with meta scenarios
    Given some behat configuration
    And scenario steps tagged with "@javascript @js-errors":
      """
      When I visit "/sites/default/files/errors.html"
      Then I should see "Page with JavaScript Errors"
      """
    When I run "behat --no-colors"
    Then it should pass

  @trait:JavascriptTrait
  Scenario: Error messages include source and line numbers
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      When I visit "/sites/default/files/errors.html"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Source:
      """
    And it should fail with an error:
      """
      line
      """
