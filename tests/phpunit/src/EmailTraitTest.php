<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\EmailTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for EmailTrait.
 */
#[CoversClass(EmailTrait::class)]
class EmailTraitTest extends UnitTestCase {

  use EmailTrait;

  #[DataProvider('dataProviderExtractLinks')]
  public function testExtractLinks(string $input, array $expected): void {
    $result = $this->emailExtractLinks($input);
    $this->assertEquals($expected, $result);
  }

  public static function dataProviderExtractLinks(): array {
    return [
      'single link' => [
        'Please visit https://example.com for more information.',
        ['https://example.com'],
      ],
      'multiple links' => [
        'Please visit https://example.com or http://example.org for more information.',
        ['https://example.com', 'http://example.org'],
      ],
      'www link without protocol' => [
        'Please visit www.example.com for more information.',
        ['http://www.example.com'],
      ],
      'link with path' => [
        'Please visit https://example.com/page/123 for more information.',
        ['https://example.com/page/123'],
      ],
      'link with query parameters' => [
        'Please visit https://example.com/?param=value&another=123 for more information.',
        ['https://example.com/?param=value&another=123'],
      ],
      'link with hash' => [
        'Please visit https://example.com/#section for more information.',
        ['https://example.com/#section'],
      ],
      'link with parentheses' => [
        'Please visit (https://example.com) for more information.',
        ['https://example.com'],
      ],
      'no links' => [
        'This text does not contain any links.',
        [],
      ],
      'link with special characters' => [
        'Check this link: https://example.com/path/with-dash_underscore+plus~tilde?q=search&x=y#fragment',
        ['https://example.com/path/with-dash_underscore+plus~tilde?q=search&x=y#fragment'],
      ],
      'malformed link missing protocol' => [
        'This is a malformed link: example.com/page',
        ['http://example.com/page'],
      ],
      'links in HTML context' => [
        '<p>Visit our <a href="https://example.com">website</a> for more information.</p>',
        ['https://example.com'],
      ],
      'links in markdown context' => [
        'Visit our [website](https://example.com) or click on https://example.org directly.',
        ['https://example.com', 'https://example.org'],
      ],
    ];
  }

}
