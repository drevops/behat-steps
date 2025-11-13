<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Mink\Driver\Selenium2Driver;

/**
 * Test responsive layouts with viewport control.
 *
 * - Default breakpoints: mobile_portrait, tablet_landscape, desktop, etc.
 * - Custom breakpoint registration via `responsiveSetBreakpoints()`
 * - Tag-based viewport control using `@breakpoint:NAME` tag
 * - Step-based viewport control during scenario execution
 * - Individual width/height control or combined dimensions.
 *
 * Tag-based viewport control:
 * @code
 * @javascript @breakpoint:mobile_portrait
 * Scenario: Mobile navigation test
 *   When I am on the homepage
 *   Then I should see the mobile menu
 * @endcode
 *
 * Step-based viewport control:
 * @code
 * @javascript
 * Scenario: Responsive layout test
 *   When I am on the homepage
 *   And I set the viewport to the "tablet_landscape" breakpoint
 *   Then I should see the tablet layout
 *   When I set the viewport to "1920" by "1080"
 *   Then I should see the desktop layout
 * @endcode
 *
 * Custom breakpoints:
 * @code
 * class FeatureContext extends DrupalContext {
 *   use ResponsiveTrait;
 *
 *   // @BeforeScenario
 *   public function setupCustomBreakpoints(): void {
 *     $this->responsiveSetBreakpoints([
 *       'iphone_12' => '390x844',
 *       '4k' => '3840x2160',
 *     ]);
 *   }
 * }
 * @endcode
 */
trait ResponsiveTrait {

  /**
   * Default breakpoint definitions.
   *
   * Format: 'name' => 'WIDTHxHEIGHT'
   *
   * @var array<string, string>
   */
  protected array $responsiveDefaultBreakpoints = [
    'mobile_portrait' => '360x640',
    'mobile_landscape' => '640x360',
    'tablet_portrait' => '768x1024',
    'tablet_landscape' => '1024x768',
    'laptop' => '1280x800',
    'desktop' => '2560x1440',
  ];

  /**
   * Custom breakpoint definitions.
   *
   * @var array<string, string>
   */
  protected array $responsiveCustomBreakpoints = [];

  /**
   * Set custom breakpoints.
   *
   * Custom breakpoints override default breakpoints with the same name.
   *
   * @param array<string, string> $breakpoints
   *   Array of breakpoints in format ['name' => 'WIDTHxHEIGHT'].
   *
   * @throws \RuntimeException
   *   If breakpoint format is invalid.
   */
  public function responsiveSetBreakpoints(array $breakpoints): void {
    foreach ($breakpoints as $name => $dimensions) {
      // Validate format by extracting dimensions.
      $this->responsiveExtractDimensions($dimensions, $name);
      $this->responsiveCustomBreakpoints[$name] = $dimensions;
    }
  }

  /**
   * Set custom responsive breakpoints from a table.
   *
   * @code
   * Given the following responsive breakpoints:
   *   | name       | dimensions |
   *   | iphone_12  | 390x844    |
   *   | 4k_display | 3840x2160  |
   * @endcode
   *
   * @Given the following responsive breakpoints:
   */
  public function responsiveSetBreakpointsFromTable(TableNode $table): void {
    $breakpoints = [];
    foreach ($table->getHash() as $row) {
      $breakpoints[$row['name']] = $row['dimensions'];
    }
    $this->responsiveSetBreakpoints($breakpoints);
  }

  /**
   * Process @breakpoint:NAME tag before scenario.
   *
   * @BeforeScenario
   */
  public function responsiveBeforeScenario(BeforeScenarioScope $scope): void {
    $tags = $scope->getScenario()->getTags();

    // Collect all breakpoint tags.
    $breakpoint_tags = [];
    foreach ($tags as $tag) {
      if (str_starts_with($tag, 'breakpoint:')) {
        $breakpoint_tags[] = $tag;
      }
    }

    // No breakpoint tags found - nothing to do.
    if (empty($breakpoint_tags)) {
      return;
    }

    // Multiple breakpoint tags - throw exception.
    if (count($breakpoint_tags) > 1) {
      throw new \RuntimeException(sprintf('Only one @breakpoint tag is allowed per scenario. Found: @%s', implode(', @', $breakpoint_tags)));
    }

    // Single breakpoint tag - validate and process.
    $tag = $breakpoint_tags[0];

    // Validate that @javascript tag is present.
    if (!in_array('javascript', $tags)) {
      throw new \RuntimeException(sprintf('@%s tag requires @javascript tag to resize viewport', $tag));
    }

    $breakpoint_name = substr($tag, strlen('breakpoint:'));
    $this->responsiveResizeToBreakpoint($breakpoint_name);
  }

  /**
   * Set the viewport to a specific breakpoint.
   *
   * @code
   * When I set the viewport to the "mobile_portrait" breakpoint
   * When I set the viewport to the "desktop" breakpoint
   * @endcode
   *
   * @When I set the viewport to the :breakpoint breakpoint
   *
   * @param string $breakpoint
   *   The breakpoint name.
   *
   * @throws \RuntimeException
   *   If breakpoint doesn't exist.
   */
  public function responsiveSetViewportToBreakpoint(string $breakpoint): void {
    $this->responsiveResizeToBreakpoint($breakpoint);
  }

  /**
   * Set the viewport width.
   *
   * @code
   * When I set the viewport width to "1920"
   * When I set the viewport width to "768"
   * @endcode
   *
   * @When I set the viewport width to :width
   *
   * @param string $width
   *   The width in pixels.
   */
  public function responsiveSetViewportWidth(string $width): void {
    $current_dimensions = $this->responsiveGetCurrentDimensions();
    $this->responsiveResize((int) $width, $current_dimensions['height']);
  }

  /**
   * Set the viewport height.
   *
   * @code
   * When I set the viewport height to "1080"
   * When I set the viewport height to "900"
   * @endcode
   *
   * @When I set the viewport height to :height
   *
   * @param string $height
   *   The height in pixels.
   */
  public function responsiveSetViewportHeight(string $height): void {
    $current_dimensions = $this->responsiveGetCurrentDimensions();
    $this->responsiveResize($current_dimensions['width'], (int) $height);
  }

  /**
   * Set the viewport to specific dimensions.
   *
   * @code
   * When I set the viewport to "1920" by "1080"
   * When I set the viewport to "375" by "667"
   * @endcode
   *
   * @When I set the viewport to :width by :height
   *
   * @param string $width
   *   The width in pixels.
   * @param string $height
   *   The height in pixels.
   */
  public function responsiveSetViewportDimensions(string $width, string $height): void {
    $this->responsiveResize((int) $width, (int) $height);
  }

  /**
   * Resize viewport to a named breakpoint.
   *
   * @param string $breakpoint
   *   The breakpoint name.
   *
   * @throws \RuntimeException
   *   If breakpoint doesn't exist.
   */
  protected function responsiveResizeToBreakpoint(string $breakpoint): void {
    $dimensions = $this->responsiveGetBreakpoint($breakpoint);
    $parsed = $this->responsiveExtractDimensions($dimensions);
    $this->responsiveResize($parsed['width'], $parsed['height']);
  }

  /**
   * Get breakpoint dimensions by name.
   *
   * @param string $name
   *   The breakpoint name.
   *
   * @return string
   *   The dimensions in WIDTHxHEIGHT format.
   *
   * @throws \RuntimeException
   *   If breakpoint doesn't exist.
   */
  protected function responsiveGetBreakpoint(string $name): string {
    $all_breakpoints = $this->responsiveGetAllBreakpoints();

    if (!isset($all_breakpoints[$name])) {
      $available = implode(', ', array_keys($all_breakpoints));
      throw new \RuntimeException(sprintf("Breakpoint '%s' not found. Available breakpoints: %s", $name, $available));
    }

    return $all_breakpoints[$name];
  }

  /**
   * Get all available breakpoints.
   *
   * Custom breakpoints override defaults with the same name.
   *
   * @return array<string, string>
   *   All breakpoints.
   */
  protected function responsiveGetAllBreakpoints(): array {
    return array_merge($this->responsiveDefaultBreakpoints, $this->responsiveCustomBreakpoints);
  }

  /**
   * Extract and validate dimensions from breakpoint string.
   *
   * @param string $dimensions
   *   Dimensions in WIDTHxHEIGHT format.
   * @param string|null $name
   *   Optional breakpoint name for error messages.
   *
   * @return array<string, int>
   *   Array with 'width' and 'height' keys.
   *
   * @throws \RuntimeException
   *   If format is invalid.
   */
  protected function responsiveExtractDimensions(string $dimensions, ?string $name = NULL): array {
    if (!preg_match('/^(\d+)x(\d+)$/i', $dimensions, $matches)) {
      if ($name) {
        throw new \RuntimeException(sprintf("Invalid breakpoint format for '%s': '%s'. Expected format: WIDTHxHEIGHT (e.g., 1920x1080)", $name, $dimensions));
      }
      throw new \RuntimeException(sprintf("Invalid breakpoint format: '%s'. Expected format: WIDTHxHEIGHT (e.g., 1920x1080)", $dimensions));
    }

    return [
      'width' => (int) $matches[1],
      'height' => (int) $matches[2],
    ];
  }

  /**
   * Get current viewport dimensions.
   *
   * @return array<string, int>
   *   Array with 'width' and 'height' keys.
   */
  protected function responsiveGetCurrentDimensions(): array {
    $driver = $this->getSession()->getDriver();

    // Default dimensions if unable to determine current size.
    $default_width = 1280;
    $default_height = 800;

    // @codeCoverageIgnoreStart
    if (!$driver instanceof Selenium2Driver) {
      return ['width' => $default_width, 'height' => $default_height];
    }
    // @codeCoverageIgnoreEnd
    try {
      $width = $this->getSession()->evaluateScript('return window.innerWidth;');
      $height = $this->getSession()->evaluateScript('return window.innerHeight;');

      return [
        'width' => $width ?: $default_width,
        'height' => $height ?: $default_height,
      ];
    }
    // @codeCoverageIgnoreStart
    catch (\Exception) {
      return ['width' => $default_width, 'height' => $default_height];
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Resize the browser window.
   *
   * @param int $width
   *   The width in pixels.
   * @param int $height
   *   The height in pixels.
   */
  protected function responsiveResize(int $width, int $height): void {
    $driver = $this->getSession()->getDriver();

    if (!$driver instanceof Selenium2Driver) {
      return;
    }

    try {
      // Ensure session is started before resizing.
      if (!$this->getSession()->isStarted()) {
        // @codeCoverageIgnoreStart
        $this->getSession()->start();
        // @codeCoverageIgnoreEnd
      }

      $this->getSession()->resizeWindow($width, $height, 'current');
    }
    // @codeCoverageIgnoreStart
    catch (\Exception) {
      // Silently fail if resize not supported.
    }
    // @codeCoverageIgnoreEnd
  }

}
