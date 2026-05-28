Feature: Check that AccessibilityTrait works
  As Behat Steps library developer
  I want to assess accessibility of rendered pages
  So that consumers can fail scenarios on WCAG violations

  @javascript
  Scenario: Clean page passes the explicit assertion
    Given I visit "/sites/default/files/accessibility_clean.html"
    Then the current page should pass accessibility checks

  @javascript
  Scenario: Clean page passes the explicit assertion for a specific tag set
    Given I visit "/sites/default/files/accessibility_clean.html"
    Then the current page should pass accessibility checks for tags "wcag2a"

  @javascript @accessibility
  Scenario: Auto mode passes when navigating between clean pages
    Given I visit "/sites/default/files/accessibility_clean.html"
    Then I should see "Clean Accessibility Page"
    When I follow "Go to second clean page"
    Then I should see "Second Clean Accessibility Page"

  @javascript @accessibility-warning
  Scenario: Auto mode with warning tag never fails even on a broken page
    Given I visit "/sites/default/files/accessibility_violations.html"
    Then I should see "Inaccessible Page"

  @trait:AccessibilityTrait
  Scenario: Explicit assertion fails on a page with violations
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      Given I visit "/sites/default/files/accessibility_violations.html"
      Then the current page should pass accessibility checks
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Accessibility gate failed on
      """
    And the output should contain:
      """
      violation [critical] image-alt
      """
    And the output should contain:
      """
      violation [critical] button-name
      """

  @trait:AccessibilityTrait
  Scenario: Auto mode fails on a page with violations
    Given some behat configuration
    And scenario steps tagged with "@javascript @accessibility":
      """
      Given I visit "/sites/default/files/accessibility_violations.html"
      Then I should see "Inaccessible Page"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Auto accessibility gate failed
      """
    And the output should contain:
      """
      violation [critical] image-alt
      """

  @trait:AccessibilityTrait
  Scenario: Auto mode with critical threshold ignores serious violations only
    Given some behat configuration
    And scenario steps tagged with "@javascript @accessibility-critical":
      """
      Given I visit "/sites/default/files/accessibility_violations.html"
      Then I should see "Inaccessible Page"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      threshold=critical
      """
    And the output should contain:
      """
      violation [critical] image-alt
      """
