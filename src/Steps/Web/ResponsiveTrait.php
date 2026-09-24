<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeStep;
use Behat\Step\Given;
use Behat\Step\When;
use DrevOps\BehatSteps\Attribute\Steps;
use DrevOps\BehatSteps\Behat\Tag;

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
 *   #[BeforeScenario]
 *   public function setupCustomBreakpoints(): void {
 *     $this->responsiveSetBreakpoints([
 *       'iphone_12' => '390x844',
 *       '4k' => '3840x2160',
 *     ]);
 *   }
 * }
 * @endcode
 *
 * @phpstan-require-extends \Behat\MinkExtension\Context\RawMinkContext
 */
#[Steps]
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
   * Breakpoint name from tag to apply before the first step.
   */
  protected ?string $responsiveBreakpointFromTag = NULL;

  /**
   * Validate @breakpoint:NAME tag before scenario.
   */
  #[BeforeScenario]
  public function responsiveBeforeScenario(BeforeScenarioScope $scope): void {
    $tags = Tag::on($scope->getScenario());

    $breakpoint_tags = [];
    foreach ($tags as $tag) {
      if (str_starts_with($tag, 'breakpoint:')) {
        $breakpoint_tags[] = $tag;
      }
    }

    if (empty($breakpoint_tags)) {
      return;
    }

    if (count($breakpoint_tags) > 1) {
      throw new \RuntimeException(sprintf('Only one @breakpoint tag is allowed per scenario. Found: @%s.', implode(', @', $breakpoint_tags)));
    }

    $tag = $breakpoint_tags[0];

    if (!in_array('javascript', $tags, TRUE)) {
      throw new \RuntimeException(sprintf('@%s tag requires @javascript tag to resize viewport.', $tag));
    }

    $breakpoint = substr($tag, strlen('breakpoint:'));

    // Validate the breakpoint exists.
    $this->responsiveGetBreakpoint($breakpoint);

    // Store for deferred resize in beforeStep when session is ready.
    $this->responsiveBreakpointFromTag = $breakpoint;
  }

  /**
   * Apply deferred @breakpoint tag resize before the first step.
   */
  #[BeforeStep]
  public function responsiveBeforeStep(BeforeStepScope $scope): void {
    if ($this->responsiveBreakpointFromTag === NULL) {
      return;
    }

    $breakpoint = $this->responsiveBreakpointFromTag;
    $this->responsiveBreakpointFromTag = NULL;
    $this->responsiveResizeToBreakpoint($breakpoint);
  }

  /**
   * Set custom responsive breakpoints from a table.
   *
   * @code
   * Given the following responsive breakpoints exist:
   *   | name       | dimensions |
   *   | iphone_12  | 390x844    |
   *   | 4k_display | 3840x2160  |
   * @endcode
   */
  #[Given('the following responsive breakpoints exist:')]
  public function responsiveSetBreakpointsFromTable(TableNode $table): void {
    $breakpoints = [];
    foreach ($table->getHash() as $row) {
      $breakpoints[$row['name']] = $row['dimensions'];
    }
    $this->responsiveSetBreakpoints($breakpoints);
  }

  /**
   * Set the viewport to a specific breakpoint.
   *
   * @code
   * When I set the viewport to the "mobile_portrait" breakpoint
   * When I set the viewport to the "desktop" breakpoint
   * @endcode
   *
   * @param string $breakpoint
   *   The breakpoint name.
   *
   * @throws \RuntimeException
   *   If breakpoint doesn't exist.
   */
  #[When('I set the viewport to the :breakpoint breakpoint')]
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
   * @param string $width
   *   The width in pixels.
   */
  #[When('I set the viewport width to :width')]
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
   * @param string $height
   *   The height in pixels.
   */
  #[When('I set the viewport height to :height')]
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
   * @param string $width
   *   The width in pixels.
   * @param string $height
   *   The height in pixels.
   */
  #[When('I set the viewport to :width by :height')]
  public function responsiveSetViewportDimensions(string $width, string $height): void {
    $this->responsiveResize((int) $width, (int) $height);
  }

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
   * Resize viewport to a named breakpoint.
   *
   * @param string $breakpoint
   *   The breakpoint name.
   *
   * @throws \RuntimeException
   *   If breakpoint doesn't exist.
   */
  public function responsiveResizeToBreakpoint(string $breakpoint): void {
    $dimensions = $this->responsiveGetBreakpoint($breakpoint);
    $parsed = $this->responsiveExtractDimensions($dimensions);
    $this->responsiveResize($parsed['width'], $parsed['height']);
  }

  /**
   * Get breakpoint dimensions by name.
   *
   * @param string $breakpoint
   *   The breakpoint name.
   *
   * @return string
   *   The dimensions in WIDTHxHEIGHT format.
   *
   * @throws \RuntimeException
   *   If breakpoint doesn't exist.
   */
  public function responsiveGetBreakpoint(string $breakpoint): string {
    $all_breakpoints = $this->responsiveGetAllBreakpoints();

    if (!isset($all_breakpoints[$breakpoint])) {
      $available = implode(', ', array_keys($all_breakpoints));
      throw new \RuntimeException(sprintf("Breakpoint '%s' not found. Available breakpoints: %s", $breakpoint, $available));
    }

    return $all_breakpoints[$breakpoint];
  }

  /**
   * Get all available breakpoints.
   *
   * Custom breakpoints override defaults with the same name.
   *
   * @return array<string, string>
   *   All breakpoints.
   */
  public function responsiveGetAllBreakpoints(): array {
    return array_merge($this->responsiveDefaultBreakpoints, $this->responsiveCustomBreakpoints);
  }

  /**
   * Extract and validate dimensions from breakpoint string.
   *
   * @param string $dimensions
   *   Dimensions in WIDTHxHEIGHT format.
   * @param string|null $breakpoint
   *   Optional breakpoint name for error messages.
   *
   * @return array<string, int>
   *   Array with 'width' and 'height' keys.
   *
   * @throws \RuntimeException
   *   If format is invalid.
   */
  protected function responsiveExtractDimensions(string $dimensions, ?string $breakpoint = NULL): array {
    if (!preg_match('/^(\d+)x(\d+)$/i', $dimensions, $matches)) {
      if ($breakpoint) {
        throw new \RuntimeException(sprintf("Invalid breakpoint format for '%s': '%s'. Expected format: WIDTHxHEIGHT (e.g., 1920x1080)", $breakpoint, $dimensions));
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
  public function responsiveGetCurrentDimensions(): array {
    $default_width = 1280;
    $default_height = 800;

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
  public function responsiveResize(int $width, int $height): void {
    try {
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
