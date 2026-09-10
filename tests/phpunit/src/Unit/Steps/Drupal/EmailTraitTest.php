<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use DrevOps\BehatSteps\Steps\Drupal\EmailTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\Behat\Context\RawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for EmailTrait.
 */
#[CoversTrait(EmailTrait::class)]
class EmailTraitTest extends UnitTestCase {

  #[DataProvider('dataProviderExtractLinks')]
  public function testExtractLinks(string $input, array $expected): void {
    $result = EmailTraitTestImplementation::callEmailExtractLinks($input);
    $this->assertEquals($expected, $result);
  }

  public static function dataProviderExtractLinks(): array {
    return [
      'single link' => [
        'Please visit http://example.com for more information.',
        ['http://example.com'],
      ],
      'multiple links' => [
        'Please visit http://example.com or http://example.org for more information.',
        ['http://example.com', 'http://example.org'],
      ],
      'www link without protocol' => [
        'Please visit www.example.com for more information.',
        ['http://www.example.com'],
      ],
      'link with path' => [
        'Please visit http://example.com/page/123 for more information.',
        ['http://example.com/page/123'],
      ],
      'link with query parameters' => [
        'Please visit http://example.com/?param=value&another=123 for more information.',
        ['http://example.com/?param=value&another=123'],
      ],
      'link with hash' => [
        'Please visit http://example.com/#section for more information.',
        ['http://example.com/#section'],
      ],
      'link with parentheses' => [
        'Please visit (http://example.com) for more information.',
        ['http://example.com'],
      ],
      'no links' => [
        'This text does not contain any links.',
        [],
      ],
      'link with special characters' => [
        'Check this link: http://example.com/path/with-dash_underscore+plus~tilde?q=search&x=y#fragment',
        ['http://example.com/path/with-dash_underscore+plus~tilde?q=search&x=y#fragment'],
      ],
      'malformed link missing protocol' => [
        'This is a malformed link: example.com/page',
        ['http://example.com/page'],
      ],
      'links in HTML context' => [
        '<p>Visit our <a href="http://example.com">website</a> for more information.</p>',
        ['http://example.com'],
      ],
      'links in markdown context' => [
        'Visit our [website](http://example.com) or click on https://example.org directly.',
        ['http://example.com', 'https://example.org'],
      ],
    ];
  }

}

/**
 * Test implementation of EmailTrait.
 *
 * Exposes the protected link extractor under test.
 */
class EmailTraitTestImplementation extends RawContext {

  use EmailTrait;

  /**
   * Extract all links from provided string.
   *
   * @param string $string
   *   String to extract links from.
   *
   * @return array<int, string>
   *   Array of extracted links.
   */
  public static function callEmailExtractLinks(string $string): array {
    return static::emailExtractLinks($string);
  }

}
