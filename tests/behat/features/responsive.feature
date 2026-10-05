Feature: Check that ResponsiveTrait works
  As Behat Steps library developer
  I want to provide tools to test responsive layouts with viewport control
  So that users can verify their responsive designs at various breakpoints

  @javascript @phpserver
  Scenario: Resize viewport to default breakpoints
    When I am on "http://cli:8888/javascript_clean1.html"
    And I set the viewport to the breakpoint "mobile_portrait"
    And I set the viewport to the breakpoint "mobile_landscape"
    And I set the viewport to the breakpoint "tablet_portrait"
    And I set the viewport to the breakpoint "tablet_landscape"
    And I set the viewport to the breakpoint "laptop"
    And I set the viewport to the breakpoint "desktop"

  @javascript @phpserver
  Scenario: Set custom viewport dimensions
    When I am on "http://cli:8888/javascript_clean1.html"
    And I set the viewport to "1920" by "1080"
    And I set the viewport to "800" by "600"
    And I set the viewport to "1366" by "768"

  @javascript @phpserver
  Scenario: Set individual viewport width and height
    When I am on "http://cli:8888/javascript_clean1.html"
    And I set the viewport to "1024" by "768"
    And I set the viewport width to "1280"
    And I set the viewport height to "1024"

  @javascript @breakpoint:tablet_landscape @phpserver
  Scenario: Tag-based breakpoint control
    When I am on "http://cli:8888/javascript_clean1.html"

  @javascript @breakpoint:mobile_portrait @phpserver
  Scenario: Tag-based mobile breakpoint
    When I am on "http://cli:8888/javascript_clean1.html"

  @javascript @breakpoint:desktop @phpserver
  Scenario: Tag-based desktop breakpoint
    When I am on "http://cli:8888/javascript_clean1.html"

  @javascript @breakpoint:tablet_landscape @phpserver
  Scenario: Tag-based breakpoint should actually resize viewport
    When I am on "http://cli:8888/javascript_clean1.html"
    Then the viewport should have the width of "1024"

  @javascript @phpserver
  Scenario: Step-based breakpoint should resize viewport
    When I am on "http://cli:8888/javascript_clean1.html"
    And I set the viewport to the breakpoint "tablet_landscape"
    Then the viewport should have the width of "1024"

  @javascript @phpserver
  Scenario: Test multiple breakpoints in sequence
    When I am on "http://cli:8888/javascript_clean1.html"
    And I set the viewport to the breakpoint "mobile_portrait"
    And I set the viewport to the breakpoint "tablet_portrait"
    And I set the viewport to the breakpoint "desktop"

  @test-trait:ResponsiveTrait
  Scenario: Invalid breakpoint should throw exception
    Given some behat configuration
    And scenario steps:
      """
      @javascript @phpserver
      Scenario: Test invalid breakpoint
        When I am on "http://cli:8888/javascript_clean1.html"
        And I set the viewport to the breakpoint "non_existent_breakpoint"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Breakpoint "non_existent_breakpoint" not found
      """

  @test-trait:ResponsiveTrait
  Scenario: Invalid breakpoint tag should throw exception
    Given some behat configuration
    And scenario steps:
      """
      @javascript @breakpoint:invalid_breakpoint_tag @phpserver
      Scenario: Test invalid breakpoint tag
        When I am on "http://cli:8888/javascript_clean1.html"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Breakpoint "invalid_breakpoint_tag" not found
      """

  @test-trait:ResponsiveTrait
  Scenario: Missing @javascript tag with @breakpoint should throw exception
    Given some behat configuration
    And scenario steps:
      """
      @breakpoint:mobile_portrait @phpserver
      Scenario: Test missing javascript tag
        When I am on "http://cli:8888/javascript_clean1.html"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      @breakpoint:mobile_portrait tag requires @javascript tag to resize viewport
      """

  @test-trait:ResponsiveTrait
  Scenario: Multiple @breakpoint tags should throw exception
    Given some behat configuration
    And scenario steps:
      """
      @javascript @breakpoint:mobile_portrait @breakpoint:desktop @phpserver
      Scenario: Test multiple breakpoint tags
        When I am on "http://cli:8888/javascript_clean1.html"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Only one @breakpoint tag is allowed per scenario. Found: @breakpoint:mobile_portrait, @breakpoint:desktop
      """

  @test-trait:ResponsiveTrait
  Scenario: Multiple @breakpoint tags on the feature should throw exception
    Given some behat configuration
    And a file named "features/stub.feature" with:
      """
      @javascript @breakpoint:mobile_portrait @breakpoint:desktop @phpserver
      Feature: Stub feature

        Scenario: Test multiple breakpoint tags on the feature
          When I am on "http://cli:8888/javascript_clean1.html"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Only one @breakpoint tag is allowed per feature. Found: @breakpoint:mobile_portrait, @breakpoint:desktop
      """

  @test-trait:ResponsiveTrait
  Scenario: A @breakpoint tag on the feature applies to every scenario and a scenario tag overrides it
    Given some behat configuration
    And a file named "features/stub.feature" with:
      """
      @javascript @breakpoint:tablet_landscape @phpserver
      Feature: Stub feature

        Scenario: The feature tag sets the viewport
          When I am on "http://cli:8888/javascript_clean1.html"
          Then the viewport should have the width of "1024"

        @breakpoint:tablet_portrait
        Scenario: The scenario tag overrides the feature tag
          When I am on "http://cli:8888/javascript_clean1.html"
          Then the viewport should have the width of "768"
      """
    When I run "behat --no-colors"
    Then it should pass with:
      """
      2 scenarios (2 passed)
      """

  @javascript @phpserver
  Scenario: Custom breakpoints can be registered and used
    Given the following responsive breakpoints exist:
      | name       | dimensions |
      | iphone_12  | 390x844    |
      | 4k_display | 3840x2160  |
    When I am on "http://cli:8888/javascript_clean1.html"
    And I set the viewport to the breakpoint "iphone_12"
    And I set the viewport to the breakpoint "4k_display"

  @test-trait:ResponsiveTrait
  Scenario: Invalid custom breakpoint format should throw exception
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given the following responsive breakpoints exist:
        | name     | dimensions |
        | invalid  | 1920-1080  |
      When I am on "http://cli:8888/javascript_clean1.html"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Invalid breakpoint format for "invalid": "1920-1080". Expected format: WIDTHxHEIGHT
      """

  @test-trait:ResponsiveTrait
  Scenario: Invalid custom breakpoint format with letters should throw exception
    Given some behat configuration
    And scenario steps tagged with "@javascript @phpserver":
      """
      Given the following responsive breakpoints exist:
        | name     | dimensions |
        | invalid  | 1920xABC   |
      When I am on "http://cli:8888/javascript_clean1.html"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Invalid breakpoint format for "invalid": "1920xABC". Expected format: WIDTHxHEIGHT
      """

  @javascript @phpserver
  Scenario: Custom breakpoint overrides default breakpoint
    Given the following responsive breakpoints exist:
      | name            | dimensions |
      | mobile_portrait | 375x812    |
    When I am on "http://cli:8888/javascript_clean1.html"
    And I set the viewport to the breakpoint "mobile_portrait"

  @phpserver
  Scenario: Viewport steps without JavaScript driver should not throw exceptions
    When I am on "http://cli:8888/javascript_clean1.html"
    And I set the viewport to the breakpoint "mobile_portrait"
    And I set the viewport to "1920" by "1080"
    And I set the viewport width to "1280"
    And I set the viewport height to "1024"

  @javascript @phpserver
  Scenario: Resize before visiting any page should start session
    When I set the viewport to "1920" by "1080"
    And I am on "http://cli:8888/javascript_clean1.html"
