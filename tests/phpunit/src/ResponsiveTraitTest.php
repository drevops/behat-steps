<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\ResponsiveTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for ResponsiveTrait.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment
 */
#[CoversClass(ResponsiveTrait::class)]
class ResponsiveTraitTest extends UnitTestCase {

  /**
   * A test implementation of ResponsiveTrait.
   *
   * @var \DrevOps\BehatSteps\Tests\ResponsiveTraitTestImplementation
   */
  protected $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new ResponsiveTraitTestImplementation();
  }

  #[DataProvider('dataProviderExtractDimensions')]
  public function testExtractDimensions(string $dimensions, array $expected, ?string $exception = NULL): void {
    if ($exception) {
      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage($exception);
    }

    $result = $this->testObject->testResponsiveExtractDimensions($dimensions);
    $this->assertEquals($expected, $result);
  }

  public static function dataProviderExtractDimensions(): array {
    return [
      'valid mobile portrait' => [
        '360x640',
        ['width' => 360, 'height' => 640],
      ],
      'valid desktop' => [
        '1920x1080',
        ['width' => 1920, 'height' => 1080],
      ],
      'valid 4K' => [
        '3840x2160',
        ['width' => 3840, 'height' => 2160],
      ],
      'valid small dimensions' => [
        '1x1',
        ['width' => 1, 'height' => 1],
      ],
      'valid large dimensions' => [
        '9999x9999',
        ['width' => 9999, 'height' => 9999],
      ],
      'case insensitive - uppercase X' => [
        '1920X1080',
        ['width' => 1920, 'height' => 1080],
      ],
      'invalid - missing height' => [
        '1920',
        [],
        "Invalid breakpoint format: '1920'. Expected format: WIDTHxHEIGHT (e.g., 1920x1080)",
      ],
      'invalid - missing width' => [
        'x1080',
        [],
        "Invalid breakpoint format: 'x1080'. Expected format: WIDTHxHEIGHT (e.g., 1920x1080)",
      ],
      'invalid - non-numeric width' => [
        'abcx1080',
        [],
        "Invalid breakpoint format: 'abcx1080'. Expected format: WIDTHxHEIGHT (e.g., 1920x1080)",
      ],
      'invalid - non-numeric height' => [
        '1920xabc',
        [],
        "Invalid breakpoint format: '1920xabc'. Expected format: WIDTHxHEIGHT (e.g., 1920x1080)",
      ],
      'invalid - empty string' => [
        '',
        [],
        "Invalid breakpoint format: ''. Expected format: WIDTHxHEIGHT (e.g., 1920x1080)",
      ],
      'invalid - only x' => [
        'x',
        [],
        "Invalid breakpoint format: 'x'. Expected format: WIDTHxHEIGHT (e.g., 1920x1080)",
      ],
      'invalid - with spaces' => [
        '1920 x 1080',
        [],
        "Invalid breakpoint format: '1920 x 1080'. Expected format: WIDTHxHEIGHT (e.g., 1920x1080)",
      ],
      'invalid - negative width' => [
        '-1920x1080',
        [],
        "Invalid breakpoint format: '-1920x1080'. Expected format: WIDTHxHEIGHT (e.g., 1920x1080)",
      ],
      'invalid - negative height' => [
        '1920x-1080',
        [],
        "Invalid breakpoint format: '1920x-1080'. Expected format: WIDTHxHEIGHT (e.g., 1920x1080)",
      ],
      'invalid - float dimensions' => [
        '1920.5x1080.5',
        [],
        "Invalid breakpoint format: '1920.5x1080.5'. Expected format: WIDTHxHEIGHT (e.g., 1920x1080)",
      ],
    ];
  }

  #[DataProvider('dataProviderGetBreakpoint')]
  public function testGetBreakpoint(array $custom_breakpoints, string $name, string $expected, ?string $exception = NULL): void {
    if (!empty($custom_breakpoints)) {
      $this->testObject->responsiveSetBreakpoints($custom_breakpoints);
    }

    if ($exception) {
      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessageMatches($exception);
    }

    $result = $this->testObject->testResponsiveGetBreakpoint($name);
    $this->assertEquals($expected, $result);
  }

  public static function dataProviderGetBreakpoint(): array {
    return [
      'default breakpoint - mobile_portrait' => [
        [],
        'mobile_portrait',
        '360x640',
      ],
      'default breakpoint - desktop' => [
        [],
        'desktop',
        '2560x1440',
      ],
      'default breakpoint - tablet_landscape' => [
        [],
        'tablet_landscape',
        '1024x768',
      ],
      'custom breakpoint' => [
        ['iphone_12' => '390x844'],
        'iphone_12',
        '390x844',
      ],
      'custom breakpoint overrides default' => [
        ['mobile_portrait' => '400x700'],
        'mobile_portrait',
        '400x700',
      ],
      'multiple custom breakpoints' => [
        [
          'iphone_12' => '390x844',
          '4k' => '3840x2160',
        ],
        '4k',
        '3840x2160',
      ],
      'non-existent breakpoint' => [
        [],
        'non_existent',
        '',
        "/Breakpoint 'non_existent' not found\. Available breakpoints: .*/",
      ],
      'non-existent with custom breakpoints' => [
        ['custom' => '1000x2000'],
        'invalid',
        '',
        "/Breakpoint 'invalid' not found\. Available breakpoints: .*/",
      ],
    ];
  }

  #[DataProvider('dataProviderGetAllBreakpoints')]
  public function testGetAllBreakpoints(array $custom_breakpoints, array $expected): void {
    if (!empty($custom_breakpoints)) {
      $this->testObject->responsiveSetBreakpoints($custom_breakpoints);
    }

    $result = $this->testObject->testResponsiveGetAllBreakpoints();
    $this->assertEquals($expected, $result);
  }

  public static function dataProviderGetAllBreakpoints(): array {
    $defaults = [
      'mobile_portrait' => '360x640',
      'mobile_landscape' => '640x360',
      'tablet_portrait' => '768x1024',
      'tablet_landscape' => '1024x768',
      'laptop' => '1280x800',
      'desktop' => '2560x1440',
    ];

    return [
      'only default breakpoints' => [
        [],
        $defaults,
      ],
      'with single custom breakpoint' => [
        ['iphone_12' => '390x844'],
        array_merge($defaults, ['iphone_12' => '390x844']),
      ],
      'with multiple custom breakpoints' => [
        [
          'iphone_12' => '390x844',
          '4k' => '3840x2160',
          'ultrawide' => '3440x1440',
        ],
        array_merge($defaults, [
          'iphone_12' => '390x844',
          '4k' => '3840x2160',
          'ultrawide' => '3440x1440',
        ]),
      ],
      'custom overrides default' => [
        ['mobile_portrait' => '400x700'],
        array_merge($defaults, ['mobile_portrait' => '400x700']),
      ],
      'custom overrides multiple defaults' => [
        [
          'mobile_portrait' => '400x700',
          'desktop' => '1920x1080',
        ],
        array_merge($defaults, [
          'mobile_portrait' => '400x700',
          'desktop' => '1920x1080',
        ]),
      ],
    ];
  }

  #[DataProvider('dataProviderSetBreakpoints')]
  public function testSetBreakpoints(array $breakpoints, ?string $exception = NULL): void {
    if ($exception) {
      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage($exception);
    }

    $this->testObject->responsiveSetBreakpoints($breakpoints);

    // Verify breakpoints were set correctly.
    if (!$exception) {
      if (empty($breakpoints)) {
        // For empty array, just verify all defaults still exist.
        $defaults = $this->testObject->testResponsiveGetAllBreakpoints();
        $this->assertNotEmpty($defaults);
        $this->assertArrayHasKey('mobile_portrait', $defaults);
      }
      else {
        foreach ($breakpoints as $name => $dimensions) {
          $result = $this->testObject->testResponsiveGetBreakpoint($name);
          $this->assertEquals($dimensions, $result);
        }
      }
    }
  }

  public static function dataProviderSetBreakpoints(): array {
    return [
      'single valid breakpoint' => [
        ['iphone_12' => '390x844'],
      ],
      'multiple valid breakpoints' => [
        [
          'iphone_12' => '390x844',
          '4k' => '3840x2160',
          'ultrawide' => '3440x1440',
        ],
      ],
      'override default breakpoint' => [
        ['mobile_portrait' => '400x700'],
      ],
      'empty array' => [
        [],
      ],
      'invalid format - missing height' => [
        ['bad_format' => '1920'],
        "Invalid breakpoint format for 'bad_format': '1920'. Expected format: WIDTHxHEIGHT (e.g., 1920x1080)",
      ],
      'invalid format - non-numeric' => [
        ['bad_format' => 'widthxheight'],
        "Invalid breakpoint format for 'bad_format': 'widthxheight'. Expected format: WIDTHxHEIGHT (e.g., 1920x1080)",
      ],
      'mixed valid and invalid' => [
        [
          'valid' => '1920x1080',
          'invalid' => '1920',
        ],
        "Invalid breakpoint format for 'invalid': '1920'. Expected format: WIDTHxHEIGHT (e.g., 1920x1080)",
      ],
    ];
  }

}

/**
 * Test implementation of ResponsiveTrait.
 */
class ResponsiveTraitTestImplementation {

  use ResponsiveTrait;

  /**
   * Expose protected method for testing.
   */
  public function testResponsiveExtractDimensions(string $dimensions, ?string $name = NULL): array {
    return $this->responsiveExtractDimensions($dimensions, $name);
  }

  /**
   * Expose protected method for testing.
   */
  public function testResponsiveGetBreakpoint(string $name): string {
    return $this->responsiveGetBreakpoint($name);
  }

  /**
   * Expose protected method for testing.
   */
  public function testResponsiveGetAllBreakpoints(): array {
    return $this->responsiveGetAllBreakpoints();
  }

}
