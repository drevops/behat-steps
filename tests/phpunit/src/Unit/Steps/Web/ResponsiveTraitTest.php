<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use Behat\MinkExtension\Context\RawMinkContext;
use DrevOps\BehatSteps\Steps\Web\ResponsiveTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for ResponsiveTrait.
 *
 * phpcs:disable Drupal.Commenting.FunctionComment
 */
#[CoversTrait(ResponsiveTrait::class)]
class ResponsiveTraitTest extends UnitTestCase {

  /**
   * A test implementation of ResponsiveTrait.
   *
   * @var \DrevOps\BehatSteps\Tests\Unit\Steps\Web\ResponsiveTraitTestImplementation
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
  public function testExtractDimensions(string $dimensions, array $expected, ?string $expected_message = NULL): void {
    if ($expected_message) {
      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage($expected_message);
    }

    $result = $this->testObject->callExtractDimensions($dimensions);
    $this->assertSame($expected, $result);
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
        'Invalid breakpoint format: "1920". Expected format: WIDTHxHEIGHT (e.g., 1920x1080).',
      ],
      'invalid - missing width' => [
        'x1080',
        [],
        'Invalid breakpoint format: "x1080". Expected format: WIDTHxHEIGHT (e.g., 1920x1080).',
      ],
      'invalid - non-numeric width' => [
        'abcx1080',
        [],
        'Invalid breakpoint format: "abcx1080". Expected format: WIDTHxHEIGHT (e.g., 1920x1080).',
      ],
      'invalid - non-numeric height' => [
        '1920xabc',
        [],
        'Invalid breakpoint format: "1920xabc". Expected format: WIDTHxHEIGHT (e.g., 1920x1080).',
      ],
      'invalid - empty string' => [
        '',
        [],
        'Invalid breakpoint format: "". Expected format: WIDTHxHEIGHT (e.g., 1920x1080).',
      ],
      'invalid - only x' => [
        'x',
        [],
        'Invalid breakpoint format: "x". Expected format: WIDTHxHEIGHT (e.g., 1920x1080).',
      ],
      'invalid - with spaces' => [
        '1920 x 1080',
        [],
        'Invalid breakpoint format: "1920 x 1080". Expected format: WIDTHxHEIGHT (e.g., 1920x1080).',
      ],
      'invalid - negative width' => [
        '-1920x1080',
        [],
        'Invalid breakpoint format: "-1920x1080". Expected format: WIDTHxHEIGHT (e.g., 1920x1080).',
      ],
      'invalid - negative height' => [
        '1920x-1080',
        [],
        'Invalid breakpoint format: "1920x-1080". Expected format: WIDTHxHEIGHT (e.g., 1920x1080).',
      ],
      'invalid - float dimensions' => [
        '1920.5x1080.5',
        [],
        'Invalid breakpoint format: "1920.5x1080.5". Expected format: WIDTHxHEIGHT (e.g., 1920x1080).',
      ],
    ];
  }

  #[DataProvider('dataProviderGetBreakpoint')]
  public function testGetBreakpoint(array $custom_breakpoints, string $name, string $expected, ?string $expected_message = NULL): void {
    if (!empty($custom_breakpoints)) {
      $this->testObject->responsiveSetBreakpoints($custom_breakpoints);
    }

    if ($expected_message) {
      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessageMatches($expected_message);
    }

    $result = $this->testObject->responsiveGetBreakpoint($name);
    $this->assertSame($expected, $result);
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
      'custom breakpoint with a numeric name' => [
        ['1080' => '1920x1080'],
        '1080',
        '1920x1080',
      ],
      'non-existent breakpoint' => [
        [],
        'non_existent',
        '',
        '/Breakpoint "non_existent" not found\. Available breakpoints: .*/',
      ],
      'non-existent with custom breakpoints' => [
        ['custom' => '1000x2000'],
        'invalid',
        '',
        '/Breakpoint "invalid" not found\. Available breakpoints: .*/',
      ],
    ];
  }

  #[DataProvider('dataProviderGetAllBreakpoints')]
  public function testGetAllBreakpoints(array $custom_breakpoints, array $expected): void {
    if (!empty($custom_breakpoints)) {
      $this->testObject->responsiveSetBreakpoints($custom_breakpoints);
    }

    $result = $this->testObject->responsiveGetAllBreakpoints();
    $this->assertSame($expected, $result);
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
  public function testSetBreakpoints(array $breakpoints, ?string $expected_message = NULL): void {
    if ($expected_message) {
      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage($expected_message);
    }

    $this->testObject->responsiveSetBreakpoints($breakpoints);

    if (!$expected_message) {
      if (empty($breakpoints)) {
        $defaults = $this->testObject->responsiveGetAllBreakpoints();
        $this->assertNotEmpty($defaults);
        $this->assertArrayHasKey('mobile_portrait', $defaults);
      }
      else {
        foreach ($breakpoints as $name => $dimensions) {
          $result = $this->testObject->responsiveGetBreakpoint($name);
          $this->assertSame($dimensions, $result);
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
        'Invalid breakpoint format for "bad_format": "1920". Expected format: WIDTHxHEIGHT (e.g., 1920x1080).',
      ],
      'invalid format - non-numeric' => [
        ['bad_format' => 'widthxheight'],
        'Invalid breakpoint format for "bad_format": "widthxheight". Expected format: WIDTHxHEIGHT (e.g., 1920x1080).',
      ],
      'mixed valid and invalid' => [
        [
          'valid' => '1920x1080',
          'invalid' => '1920',
        ],
        'Invalid breakpoint format for "invalid": "1920". Expected format: WIDTHxHEIGHT (e.g., 1920x1080).',
      ],
    ];
  }

  /**
   * Tests the breakpoint the scenario and feature tags resolve to.
   *
   * @param list<string> $scenario_tags
   *   Tags on the scenario.
   * @param list<string> $feature_tags
   *   Tags on the feature.
   * @param string|null $expected
   *   The breakpoint expected to be applied, or NULL for none.
   */
  #[DataProvider('dataProviderBeforeScenarioResolvesBreakpoint')]
  public function testBeforeScenarioResolvesBreakpoint(array $scenario_tags, array $feature_tags, ?string $expected): void {
    $this->testObject->responsiveBeforeScenario($this->createBeforeScenarioScope($scenario_tags, $feature_tags));

    $this->assertSame($expected, $this->testObject->testGetBreakpointFromTag());
  }

  public static function dataProviderBeforeScenarioResolvesBreakpoint(): array {
    return [
      'no tags' => [[], [], NULL],
      'no breakpoint tag' => [['javascript'], ['javascript'], NULL],
      'on the scenario' => [['javascript', 'breakpoint:desktop'], [], 'desktop'],
      'on the feature' => [[], ['javascript', 'breakpoint:desktop'], 'desktop'],
      'javascript on the feature' => [['breakpoint:desktop'], ['javascript'], 'desktop'],
      'the scenario replaces the feature' => [['breakpoint:mobile_portrait'], ['javascript', 'breakpoint:desktop'], 'mobile_portrait'],
      'the same tag on both' => [['javascript', 'breakpoint:desktop'], ['breakpoint:desktop'], 'desktop'],
      'Behat 4 tags' => [['@javascript'], ['@breakpoint:desktop'], 'desktop'],
    ];
  }

  /**
   * Tests the tag combinations that fail the scenario.
   *
   * @param list<string> $scenario_tags
   *   Tags on the scenario.
   * @param list<string> $feature_tags
   *   Tags on the feature.
   * @param string $expected_message
   *   The expected exception message.
   */
  #[DataProvider('dataProviderBeforeScenarioRejectsBreakpoint')]
  public function testBeforeScenarioRejectsBreakpoint(array $scenario_tags, array $feature_tags, string $expected_message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $this->testObject->responsiveBeforeScenario($this->createBeforeScenarioScope($scenario_tags, $feature_tags));
  }

  public static function dataProviderBeforeScenarioRejectsBreakpoint(): array {
    return [
      '2 tags on the scenario' => [
        ['javascript', 'breakpoint:desktop', 'breakpoint:laptop'],
        [],
        'Only one @breakpoint tag is allowed per scenario. Found: @breakpoint:desktop, @breakpoint:laptop.',
      ],
      '2 tags on the feature' => [
        ['javascript'],
        ['breakpoint:desktop', 'breakpoint:laptop'],
        'Only one @breakpoint tag is allowed per feature. Found: @breakpoint:desktop, @breakpoint:laptop.',
      ],
      '2 tags on the feature under a scenario tag' => [
        ['javascript', 'breakpoint:tablet_portrait'],
        ['breakpoint:desktop', 'breakpoint:laptop'],
        'Only one @breakpoint tag is allowed per feature. Found: @breakpoint:desktop, @breakpoint:laptop.',
      ],
      'no javascript on the scenario' => [
        ['breakpoint:desktop'],
        [],
        '@breakpoint:desktop tag requires @javascript tag to resize viewport.',
      ],
      'no javascript under a feature tag' => [
        [],
        ['breakpoint:desktop'],
        '@breakpoint:desktop tag requires @javascript tag to resize viewport.',
      ],
      'an unknown breakpoint on the feature' => [
        ['javascript'],
        ['breakpoint:unknown'],
        'Breakpoint "unknown" not found.',
      ],
    ];
  }

  #[DataProvider('dataProviderGetCurrentDimensions')]
  public function testGetCurrentDimensions(mixed $width, mixed $height, array $expected, ?string $expected_message = NULL): void {
    $session = $this->attachSession();
    $session->method('evaluateScript')->willReturnMap([
      ['return window.innerWidth;', $width],
      ['return window.innerHeight;', $height],
    ]);

    if ($expected_message) {
      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage($expected_message);
    }

    $this->assertSame($expected, $this->testObject->responsiveGetCurrentDimensions());
  }

  public static function dataProviderGetCurrentDimensions(): array {
    return [
      'reported by the browser' => [1024, 768, ['width' => 1024, 'height' => 768]],
      'reported as numeric strings' => ['1024', '768', ['width' => 1024, 'height' => 768]],
      'width not reported' => [NULL, 768, [], 'The browser reported the viewport width as "null", but it should be a positive integer.'],
      'height reported as 0' => [1024, 0, [], 'The browser reported the viewport height as "0", but it should be a positive integer.'],
      'width reported as text' => ['wide', 768, [], 'The browser reported the viewport width as "wide", but it should be a positive integer.'],
    ];
  }

  public function testGetCurrentDimensionsFailsWithoutJavascript(): void {
    $session = $this->attachSession();
    $session->method('evaluateScript')->willThrowException(new UnsupportedDriverActionException('JS is not supported by %s', $this->createStub(DriverInterface::class)));

    $this->expectException(UnsupportedDriverActionException::class);

    $this->testObject->responsiveGetCurrentDimensions();
  }

  public function testResize(): void {
    $session = $this->attachSession();
    $session->expects($this->once())->method('resizeWindow')->with(1920, 1080, 'current');

    $this->testObject->responsiveResize(1920, 1080);
  }

  public function testResizeFailsWithoutResizeSupport(): void {
    $session = $this->attachSession();
    $session->expects($this->once())->method('resizeWindow')->willThrowException(new UnsupportedDriverActionException('Window resizing is not supported by %s', $this->createStub(DriverInterface::class)));

    $this->expectException(UnsupportedDriverActionException::class);
    $this->expectExceptionMessage('Window resizing is not supported by');

    $this->testObject->responsiveResize(1920, 1080);
  }

  /**
   * Attaches a started session double to the test object.
   *
   * @return \Behat\Mink\Session&\PHPUnit\Framework\MockObject\MockObject
   *   The session the test object reads.
   */
  protected function attachSession(): Session&MockObject {
    $session = $this->createMock(Session::class);
    $session->method('isStarted')->willReturn(TRUE);

    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');
    $this->testObject->setMink($mink);

    return $session;
  }

}

/**
 * Test implementation of ResponsiveTrait.
 */
class ResponsiveTraitTestImplementation extends RawMinkContext {

  use ResponsiveTrait;

  public function callExtractDimensions(string $dimensions, ?string $name = NULL): array {
    return $this->responsiveExtractDimensions($dimensions, $name);
  }

  public function testGetBreakpointFromTag(): ?string {
    return $this->responsiveBreakpointFromTag;
  }

}
