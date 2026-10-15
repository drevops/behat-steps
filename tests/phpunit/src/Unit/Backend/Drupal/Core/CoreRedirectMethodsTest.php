<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the normalization of redirect sources and destinations on Core.
 */
#[CoversClass(Core::class)]
#[Group('backends')]
#[Group('drupal')]
class CoreRedirectMethodsTest extends UnitTestCase {

  /**
   * Tests that a destination is stored the way the redirect module stores it.
   *
   * @param string $uri
   *   The destination as written in a step.
   * @param string $expected
   *   The stored form.
   */
  #[DataProvider('dataProviderNormalizeRedirectDestination')]
  public function testNormalizeRedirectDestination(string $uri, string $expected): void {
    $this->assertSame($expected, (new NormalizationExposingCore(__DIR__))->callNormalizeRedirectDestination($uri));
  }

  public static function dataProviderNormalizeRedirectDestination(): \Iterator {
    yield 'an internal path' => ['/about', 'internal:/about'];
    yield 'an internal path without a leading slash' => ['about', 'internal:/about'];
    yield 'an internal path with a query string' => ['/about?page=1', 'internal:/about?page=1'];
    yield 'an internal path already prefixed' => ['internal:/about', 'internal:/about'];
    yield 'a URL' => ['https://example.com/promo', 'https://example.com/promo'];
    yield 'a URL without a path' => ['http://example.com', 'http://example.com'];
    yield 'a URL with another scheme' => ['mailto:jane@example.com', 'mailto:jane@example.com'];
    yield 'a protocol-relative URL' => ['//example.com/promo', '//example.com/promo'];
  }

  /**
   * Tests that a source path is stored the way the redirect module stores it.
   *
   * @param string $path
   *   The source path as written in a step.
   * @param string $expected
   *   The stored form.
   */
  #[DataProvider('dataProviderNormalizeRedirectSource')]
  public function testNormalizeRedirectSource(string $path, string $expected): void {
    $this->assertSame($expected, (new NormalizationExposingCore(__DIR__))->callNormalizeRedirectSource($path));
  }

  public static function dataProviderNormalizeRedirectSource(): \Iterator {
    yield 'a path with a leading slash' => ['/about', 'about'];
    yield 'a path without a leading slash' => ['about', 'about'];
    yield 'a path with surrounding whitespace' => [' /about ', 'about'];
    yield 'a nested path' => ['/old/about', 'old/about'];
    yield 'the front page' => ['/', ''];
  }

}

/**
 * Core exposing its redirect normalization.
 */
class NormalizationExposingCore extends Core {

  public function callNormalizeRedirectDestination(string $uri): string {
    return $this->normalizeRedirectDestination($uri);
  }

  public function callNormalizeRedirectSource(string $path): string {
    return $this->normalizeRedirectSource($path);
  }

}
