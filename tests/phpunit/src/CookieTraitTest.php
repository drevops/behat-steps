<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\CookieTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for CookieTrait.
 */
#[CoversClass(CookieTrait::class)]
class CookieTraitTest extends UnitTestCase {
  use CookieTrait;

  #[DataProvider('dataProviderCookieParseHeader')]
  public function testCookieParseHeader(string $header, array $expected): void {
    $result = static::cookieParseHeader($header);
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for testCookieParseHeader().
   */
  public static function dataProviderCookieParseHeader(): array {
    return [
      'single cookie' => [
        'session_id=abc123',
        [
          [
            'name' => 'session_id',
            'value' => 'abc123',
            'secure' => FALSE,
          ],
        ],
      ],
      'multiple cookies' => [
        'session_id=abc123; user_id=456',
        [
          [
            'name' => 'session_id',
            'value' => 'abc123',
            'secure' => FALSE,
          ],
          [
            'name' => 'user_id',
            'value' => '456',
            'secure' => FALSE,
          ],
        ],
      ],
      'cookie with url-encoded value' => [
        'preferences=%7B%22theme%22%3A%22dark%22%7D',
        [
          [
            'name' => 'preferences',
            'value' => '{"theme":"dark"}',
            'secure' => FALSE,
          ],
        ],
      ],
      'complex header with multiple cookies' => [
        'session_id=abc123; user_id=456; preferences=%7B%22theme%22%3A%22dark%22%7D',
        [
          [
            'name' => 'session_id',
            'value' => 'abc123',
            'secure' => FALSE,
          ],
          [
            'name' => 'user_id',
            'value' => '456',
            'secure' => FALSE,
          ],
          [
            'name' => 'preferences',
            'value' => '{"theme":"dark"}',
            'secure' => FALSE,
          ],
        ],
      ],
      'cookie with special characters' => [
        'complex=value%20with%2Bspecial%26chars',
        [
          [
            'name' => 'complex',
            'value' => 'value with+special&chars',
            'secure' => FALSE,
          ],
        ],
      ],
      'empty string' => [
        '',
        [],
      ],
    ];
  }

}
