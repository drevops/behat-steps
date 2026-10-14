<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use DrevOps\BehatSteps\Steps\Drupal\RedirectTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the normalization of redirect destinations.
 */
#[CoversTrait(RedirectTrait::class)]
class RedirectTraitTest extends UnitTestCase {

  /**
   * Tests that a destination is stored the way the redirect module stores it.
   *
   * @param string $uri
   *   The destination as written in a step.
   * @param string $expected
   *   The stored form.
   */
  #[DataProvider('dataProviderNormalizeDestination')]
  public function testNormalizeDestination(string $uri, string $expected): void {
    $this->assertSame($expected, (new RedirectTraitTestImplementation())->callNormalizeDestination($uri));
  }

  public static function dataProviderNormalizeDestination(): \Iterator {
    yield 'an internal path' => ['/about', 'internal:/about'];
    yield 'an internal path without a leading slash' => ['about', 'internal:/about'];
    yield 'an internal path with a query string' => ['/about?page=1', 'internal:/about?page=1'];
    yield 'an internal path already prefixed' => ['internal:/about', 'internal:/about'];
    yield 'a URL' => ['https://example.com/promo', 'https://example.com/promo'];
    yield 'a URL without a path' => ['http://example.com', 'http://example.com'];
    yield 'a URL with another scheme' => ['mailto:jane@example.com', 'mailto:jane@example.com'];
    yield 'a protocol-relative URL' => ['//example.com/promo', '//example.com/promo'];
  }

}

/**
 * Test implementation of RedirectTrait.
 *
 * Exposes the destination normalization.
 */
class RedirectTraitTestImplementation extends WebRawContext {

  use RedirectTrait;

  public function callNormalizeDestination(string $uri): string {
    return $this->redirectNormalizeDestination($uri);
  }

}
