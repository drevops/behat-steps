Feature: Check that AccessibilityTrait works
  As Behat Steps library developer
  I want to assess accessibility of rendered pages
  So that consumers can fail scenarios on WCAG violations

  @javascript @phpserver
  Scenario: Clean page passes the explicit assertion
    Given I visit "http://cli:8888/accessibility_clean.html"
    Then the page should pass accessibility checks

  @javascript @phpserver
  Scenario: Clean page passes the explicit assertion for a specific tag set
    Given I visit "http://cli:8888/accessibility_clean.html"
    Then the page should pass accessibility checks for the tags "wcag2a"

  @javascript @accessibility @phpserver
  Scenario: Auto mode passes when navigating between clean pages
    Given I visit "http://cli:8888/accessibility_clean.html"
    Then I should see "Clean Accessibility Page"
    When I follow "Go to second clean page"
    Then I should see "Second Clean Accessibility Page"

  @javascript @accessibility:warning @phpserver
  Scenario: Auto mode with warning tag never fails even on a broken page
    Given I visit "http://cli:8888/accessibility_violations.html"
    Then I should see "Inaccessible Page"

  @test-trait:AccessibilityTrait
  Scenario: Explicit assertion fails on a page with violations
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I visit "http://cli:8888/accessibility_violations.html"
      Then the page should pass accessibility checks
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Accessibility gate failed on
      """
    And the output should contain:
      """
      Accessibility gate failed on http://cli:8888/accessibility_violations.html
      """
    And the output should contain:
      """
      violation [critical] image-alt
      """
    And the output should contain:
      """
      violation [critical] button-name
      """

  @test-trait:AccessibilityTrait
  Scenario: Auto mode fails on a page with violations
    Given some behat configuration
    And scenario steps tagged with "@javascript @accessibility @phpserver":
      """
      Given I visit "http://cli:8888/accessibility_violations.html"
      Then I should see "Inaccessible Page"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Auto accessibility gate failed
      """
    And the output should contain:
      """
      violation [critical] image-alt on http://cli:8888/accessibility_violations.html
      """

  @test-trait:AccessibilityTrait
  Scenario: Auto mode with critical threshold ignores serious violations only
    Given some behat configuration
    And scenario steps tagged with "@javascript @accessibility:critical @phpserver":
      """
      Given I visit "http://cli:8888/accessibility_violations.html"
      Then I should see "Inaccessible Page"
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      threshold: critical
      """
    And the output should contain:
      """
      violation [critical] image-alt
      """

  @test-trait:AccessibilityTrait
  Scenario: A cross-page aggregate report is written after the suite
    Given some behat configuration
    And scenario steps tagged with "@javascript @accessibility:warning @phpserver":
      """
      Given I visit "http://cli:8888/accessibility_violations.html"
      Then I should see "Inaccessible Page"
      When I visit "http://cli:8888/accessibility_clean.html"
      Then I should see "Clean Accessibility Page"
      """
    When I run "behat --no-colors"
    Then it should pass
    And a file matching ".logs/test_results/accessibility/accessibility_report_*.html" should exist

  @test-trait:AccessibilityTrait
  Scenario: Warning mode writes a JUnit report with no failures
    Given some behat configuration
    And scenario steps tagged with "@javascript @accessibility:warning @phpserver":
      """
      Given I visit "http://cli:8888/accessibility_violations.html"
      Then I should see "Inaccessible Page"
      """
    When I run "behat --no-colors"
    Then it should pass
    And a file matching ".logs/test_results/accessibility/junit-*.xml" should contain:
      """
      failures="0"
      """
    And a file matching ".logs/test_results/accessibility/junit-*.xml" should not contain:
      """
      <failure
      """
    And a file matching ".logs/test_results/accessibility/junit-*.xml" should contain:
      """
      <system-out>
      """

  @test-trait:AccessibilityTrait
  Scenario: Console summary is not printed by default
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I visit "http://cli:8888/accessibility_clean.html"
      Then the page should pass accessibility checks
      """
    When I run "behat --no-colors"
    Then it should pass
    And the output should not contain:
      """
      [accessibility]
      """

  @test-trait:AccessibilityTrait
  Scenario: Console summary is printed when the environment variable is set
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given I visit "http://cli:8888/accessibility_clean.html"
      Then the page should pass accessibility checks
      """
    When the "BEHAT_ACCESSIBILITY_PRINT" environment variable is set to "1"
    And I run "behat --no-colors"
    Then it should pass
    And the output should contain:
      """
      [accessibility] http://cli:8888/accessibility_clean.html: 0 violations,
      """

  @javascript @page-sweep
  Scenario: Pages in a table pass the accessibility assessment
    When I assess the accessibility of the following pages:
      | path                                           |
      | /sites/default/files/accessibility_clean.html  |
      | /sites/default/files/accessibility_clean2.html |

  @javascript @page-sweep
  Scenario: Pages in a table pass the accessibility assessment for a specific tag set
    When I assess the accessibility of the following pages for the tags "wcag2a":
      | path                                           |
      | /sites/default/files/accessibility_clean.html  |
      | /sites/default/files/accessibility_clean2.html |

  @test-trait:AccessibilityTrait @page-sweep
  Scenario: A table sweep assesses every page before it fails on violations
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      When I assess the accessibility of the following pages:
        | path                                               |
        | /sites/default/files/accessibility_violations.html |
        | /sites/default/files/accessibility_clean.html      |
      """
    When the "BEHAT_ACCESSIBILITY_PRINT" environment variable is set to "1"
    And I run "behat --no-colors"
    Then it should fail with an error:
      """
      Accessibility gate failed on /sites/default/files/accessibility_violations.html
      """
    And the output should contain:
      """
      [accessibility] /sites/default/files/accessibility_clean.html: 0 violations,
      """
    And the output should not contain:
      """
      Accessibility gate failed on /sites/default/files/accessibility_clean.html
      """

  @test-trait:AccessibilityTrait @page-sweep
  Scenario: A table sweep in automatic warning mode leaves the outcome to the gate
    Given some behat configuration
    And scenario steps tagged with "@javascript @accessibility:warning":
      """
      When I assess the accessibility of the following pages:
        | path                                               |
        | /sites/default/files/accessibility_violations.html |
        | /sites/default/files/accessibility_clean.html      |
      """
    When I run "behat --no-colors"
    Then it should pass

  @test-trait:AccessibilityTrait @page-sweep
  Scenario: A table sweep skips a page it cannot assess and assesses the rest
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      When I assess the accessibility of the following pages:
        | path                                                  |
        | /sites/default/files/accessibility_broken_engine.html |
        | /sites/default/files/accessibility_clean.html         |
      """
    When the "BEHAT_ACCESSIBILITY_PRINT" environment variable is set to "1"
    And I run "behat --no-colors"
    Then it should pass
    And the output should contain:
      """
      [accessibility] /sites/default/files/accessibility_clean.html: 0 violations,
      """
    And a file matching ".logs/test_results/accessibility/junit-*.xml" should contain:
      """
      <skipped message="Accessibility engine failed: engine disabled by the fixture."/>
      """

  @test-trait:AccessibilityTrait @page-sweep
  Scenario: A table sweep lists the skipped pages when it fails on violations
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      When I assess the accessibility of the following pages:
        | path                                                  |
        | /sites/default/files/accessibility_broken_engine.html |
        | /sites/default/files/accessibility_violations.html    |
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Accessibility gate failed on /sites/default/files/accessibility_violations.html
      """
    And the output should contain:
      """
      skipped "/sites/default/files/accessibility_broken_engine.html": Accessibility engine failed: engine disabled by the fixture.
      """

  @test-trait:AccessibilityTrait @page-sweep
  Scenario: A table sweep fails when no page could be assessed
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      When I assess the accessibility of the following pages:
        | path                                                  |
        | /sites/default/files/accessibility_broken_engine.html |
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      No page could be assessed:
      """
    And the output should contain:
      """
      skipped "/sites/default/files/accessibility_broken_engine.html": Accessibility engine failed: engine disabled by the fixture.
      """

  @javascript @page-sweep
  Scenario: Pages in a sitemap pass the accessibility assessment
    When I assess the accessibility of the pages in the sitemap "/sites/default/files/accessibility_sitemap.xml"

  @javascript @page-sweep
  Scenario: Pages in a sitemap up to a path depth pass the accessibility assessment
    When I assess the accessibility of the pages in the sitemap "/sites/default/files/accessibility_sitemap.xml" with a path depth of at most 4

  @test-trait:AccessibilityTrait @page-sweep
  Scenario: A sitemap sweep assesses only the pages within the limit
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      When I assess the accessibility of the pages in the sitemap "/sites/default/files/accessibility_sitemap.xml" with a path depth of at most 4 and a limit of 1 page
      """
    When the "BEHAT_ACCESSIBILITY_PRINT" environment variable is set to "1"
    And I run "behat --no-colors"
    Then it should pass
    And the output should contain:
      """
      [accessibility] /sites/default/files/accessibility_clean.html: 0 violations,
      """
    And the output should not contain:
      """
      [accessibility] /sites/default/files/accessibility_clean2.html
      """

  @test-trait:AccessibilityTrait @page-sweep
  Scenario: A sitemap sweep follows every sitemap of a sitemap index
    Given some behat configuration
    And scenario steps tagged with "@javascript @accessibility:warning":
      """
      When I assess the accessibility of the pages in the sitemap "/sites/default/files/accessibility_sitemap_index.xml"
      """
    When the "BEHAT_ACCESSIBILITY_PRINT" environment variable is set to "1"
    And I run "behat --no-colors"
    Then it should pass
    And the output should contain:
      """
      [accessibility] /sites/default/files/accessibility_clean2.html: 0 violations,
      """
    And the output should contain:
      """
      [accessibility] /sites/default/files/accessibility_violations.html:
      """
    And the output should not contain:
      """
      [accessibility] /sites/default/files/accessibility_sitemap.xml
      """

  @test-trait:AccessibilityTrait @page-sweep
  Scenario: A sitemap sweep reads the configured sitemap
    Given a configuration with the step options:
      """
      'accessibility' => ['sitemap_path' => '/sites/default/files/accessibility_sitemap_violations.xml'],
      """
    And some behat configuration
    And scenario steps tagged with "@javascript":
      """
      When I assess the accessibility of the pages in the sitemap
      """
    When I run "behat --no-colors"
    Then it should fail with an error:
      """
      Accessibility gate failed on /sites/default/files/accessibility_violations.html
      """

  @test-trait:AccessibilityTrait @page-sweep
  Scenario: A sitemap sweep fails when no page is within the path depth
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      When I assess the accessibility of the pages in the sitemap "/sites/default/files/accessibility_sitemap.xml" with a path depth of at most 3
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The sitemap "/sites/default/files/accessibility_sitemap.xml" lists no pages with a path depth of at most 3.
      """

  @test-trait:AccessibilityTrait @page-sweep
  Scenario: A sitemap sweep fails when the sitemap cannot be fetched
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      When I assess the accessibility of the pages in the sitemap "/sites/default/files/accessibility_sitemap_missing.xml"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The sitemap "/sites/default/files/accessibility_sitemap_missing.xml" returned HTTP status 404.
      """

  @test-trait:AccessibilityTrait @page-sweep
  Scenario: A sitemap sweep fails when the sitemap is not XML
    Given some behat configuration
    And scenario steps tagged with "@javascript":
      """
      When I assess the accessibility of the pages in the sitemap "/sites/default/files/json_valid.json"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      The sitemap "/sites/default/files/json_valid.json" is not valid XML.
      """
