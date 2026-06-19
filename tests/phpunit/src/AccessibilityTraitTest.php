<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\AccessibilityTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for AccessibilityTrait.
 */
#[CoversClass(AccessibilityTrait::class)]
class AccessibilityTraitTest extends UnitTestCase {

  /**
   * A test implementation of AccessibilityTrait.
   */
  protected AccessibilityTraitTestImplementation $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new AccessibilityTraitTestImplementation();
    AccessibilityTraitTestImplementation::testSetBaseDir(NULL);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    AccessibilityTraitTestImplementation::testSetBaseDir(NULL);

    parent::tearDown();
  }

  #[DataProvider('dataProviderFormatUrl')]
  public function testFormatUrl(string $base_url, string $url, string $expected): void {
    $this->testObject->baseUrl = $base_url;

    $this->assertSame($expected, $this->testObject->testFormatUrl($url));
  }

  public static function dataProviderFormatUrl(): array {
    return [
      'path under base' => ['http://nginx:8080', 'http://nginx:8080/contact', '/contact'],
      'base root with trailing slash' => ['http://nginx:8080', 'http://nginx:8080/', '/'],
      'base root without trailing slash' => ['http://nginx:8080', 'http://nginx:8080', '/'],
      'nested path' => ['http://nginx:8080', 'http://nginx:8080/a/b/c', '/a/b/c'],
      'query string preserved' => ['http://nginx:8080', 'http://nginx:8080/search?q=x', '/search?q=x'],
      'base configured with trailing slash' => ['http://nginx:8080/', 'http://nginx:8080/contact', '/contact'],
      'cross-origin kept absolute' => ['http://nginx:8080', 'https://example.com/page', 'https://example.com/page'],
      'similar prefix not stripped' => ['http://nginx:8080', 'http://nginx:8080extra/foo', 'http://nginx:8080extra/foo'],
      'no base url returns input unchanged' => ['', 'http://nginx:8080/contact', 'http://nginx:8080/contact'],
    ];
  }

  public function testGetReportDirUsesCapturedBaseDir(): void {
    AccessibilityTraitTestImplementation::testSetBaseDir('/sentinel/base');

    // chdir() to a different directory to prove the report directory anchors to
    // the captured base rather than the live working directory.
    $original = getcwd();
    chdir(static::locationsTmp());

    try {
      $this->assertSame('/sentinel/base' . DIRECTORY_SEPARATOR . '.logs/test_results/accessibility', $this->testObject->testGetReportDir());
    }
    finally {
      if (is_string($original)) {
        chdir($original);
      }
    }
  }

  public function testGetReportDirFallsBackToCwdWhenUnset(): void {
    AccessibilityTraitTestImplementation::testSetBaseDir(NULL);

    $expected = (getcwd() ?: '') . DIRECTORY_SEPARATOR . '.logs/test_results/accessibility';

    $this->assertSame($expected, $this->testObject->testGetReportDir());
  }

  public function testCaptureBaseDirSetsWhenUnset(): void {
    AccessibilityTraitTestImplementation::testSetBaseDir(NULL);

    AccessibilityTraitTestImplementation::accessibilityCaptureBaseDir();

    $this->assertSame(getcwd(), AccessibilityTraitTestImplementation::testGetBaseDir());
  }

  public function testCaptureBaseDirDoesNotOverwrite(): void {
    AccessibilityTraitTestImplementation::testSetBaseDir('/sentinel/base');

    AccessibilityTraitTestImplementation::accessibilityCaptureBaseDir();

    $this->assertSame('/sentinel/base', AccessibilityTraitTestImplementation::testGetBaseDir());
  }

}

/**
 * Test implementation of AccessibilityTrait.
 */
class AccessibilityTraitTestImplementation {

  use AccessibilityTrait;

  /**
   * Base URL returned by the stubbed Mink parameter accessor.
   */
  public string $baseUrl = '';

  public function getMinkParameter(string $name): mixed {
    return $name === 'base_url' ? $this->baseUrl : NULL;
  }

  public function testFormatUrl(string $url): string {
    return $this->accessibilityFormatUrl($url);
  }

  public function testGetReportDir(): string {
    return $this->accessibilityGetReportDir();
  }

  public static function testSetBaseDir(?string $dir): void {
    self::$accessibilityBaseDir = $dir;
  }

  public static function testGetBaseDir(): ?string {
    return self::$accessibilityBaseDir;
  }

}
