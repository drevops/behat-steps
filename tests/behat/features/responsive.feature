Feature: Check that ResponsiveTrait works
  As Behat Steps library developer
  I want to provide tools to test responsive layouts with viewport control
  So that users can verify their responsive designs at various breakpoints

  @javascript
  Scenario: Resize viewport to default breakpoints
    When I am on "/page.html"
    And I set the viewport to the "mobile_portrait" breakpoint
    And I set the viewport to the "mobile_landscape" breakpoint
    And I set the viewport to the "tablet_portrait" breakpoint
    And I set the viewport to the "tablet_landscape" breakpoint
    And I set the viewport to the "laptop" breakpoint
    And I set the viewport to the "desktop" breakpoint

  @javascript
  Scenario: Set custom viewport dimensions
    When I am on "/page.html"
    And I set the viewport to "1920" by "1080"
    And I set the viewport to "800" by "600"
    And I set the viewport to "1366" by "768"

  @javascript
  Scenario: Set individual viewport width and height
    When I am on "/page.html"
    And I set the viewport to "1024" by "768"
    And I set the viewport width to "1280"
    And I set the viewport height to "1024"

  @javascript @breakpoint:tablet_landscape
  Scenario: Tag-based breakpoint control
    When I am on "/page.html"

  @javascript @breakpoint:mobile_portrait
  Scenario: Tag-based mobile breakpoint
    When I am on "/page.html"

  @javascript @breakpoint:desktop
  Scenario: Tag-based desktop breakpoint
    When I am on "/page.html"

  @javascript
  Scenario: Test multiple breakpoints in sequence
    When I am on "/page.html"
    And I set the viewport to the "mobile_portrait" breakpoint
    And I set the viewport to the "tablet_portrait" breakpoint
    And I set the viewport to the "desktop" breakpoint

  @trait:ResponsiveTrait
  Scenario: Invalid breakpoint should throw exception
    Given some behat configuration
    And scenario steps:
      """
      @javascript
      Scenario: Test invalid breakpoint
        When I am on "/page.html"
        And I set the viewport to the "non_existent_breakpoint" breakpoint
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Breakpoint 'non_existent_breakpoint' not found
      """

  @trait:ResponsiveTrait
  Scenario: Invalid breakpoint tag should throw exception
    Given some behat configuration
    And scenario steps:
      """
      @javascript @breakpoint:invalid_breakpoint_tag
      Scenario: Test invalid breakpoint tag
        When I am on "/page.html"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Breakpoint 'invalid_breakpoint_tag' not found
      """

  @trait:ResponsiveTrait
  Scenario: Missing @javascript tag with @breakpoint should throw exception
    Given some behat configuration
    And scenario steps:
      """
      @breakpoint:mobile_portrait
      Scenario: Test missing javascript tag
        When I am on "/page.html"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      @breakpoint:mobile_portrait tag requires @javascript tag to resize viewport
      """

  @trait:ResponsiveTrait
  Scenario: Multiple @breakpoint tags should throw exception
    Given some behat configuration
    And scenario steps:
      """
      @javascript @breakpoint:mobile_portrait @breakpoint:desktop
      Scenario: Test multiple breakpoint tags
        When I am on "/page.html"
      """
    When I run "behat --no-colors"
    Then it should fail with an exception:
      """
      Only one @breakpoint tag is allowed per scenario. Found: @breakpoint:mobile_portrait, @breakpoint:desktop
      """
