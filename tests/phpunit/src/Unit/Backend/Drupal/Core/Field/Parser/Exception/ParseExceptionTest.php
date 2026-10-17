<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal\Core\Field\Parser\Exception;

use DrevOps\BehatSteps\Backend\Drupal\Core\Field\Parser\Exception\ParseException;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the position-aware message of a single parse error.
 */
#[CoversClass(ParseException::class)]
#[Group('exception')]
class ParseExceptionTest extends UnitTestCase {

  /**
   * Tests the cell, caret and description lines of the message.
   *
   * @param int $offset
   *   The offset of the error within the cell.
   * @param string|null $hint
   *   The suggested fix, or NULL for none.
   * @param array<int, string> $expected_lines
   *   The lines the message is expected to hold.
   */
  #[DataProvider('dataProviderMessage')]
  public function testMessage(int $offset, ?string $hint, array $expected_lines): void {
    $exception = new ParseException('unclosed_quote', $offset, '"abc', 'Quoted string is missing a closing double quote.', $hint);

    $this->assertSame(implode(PHP_EOL, $expected_lines), $exception->getMessage());
  }

  public static function dataProviderMessage(): \Iterator {
    yield 'caret under the first character, with a hint' => [
      0,
      'Add a closing double quote.',
      ['"abc', '^', 'unclosed_quote at offset 0: Quoted string is missing a closing double quote.', 'Hint: Add a closing double quote.'],
    ];
    yield 'caret under a later character' => [
      3,
      NULL,
      ['"abc', '   ^', 'unclosed_quote at offset 3: Quoted string is missing a closing double quote.'],
    ];
    yield 'empty hint left out' => [
      0,
      '',
      ['"abc', '^', 'unclosed_quote at offset 0: Quoted string is missing a closing double quote.'],
    ];
    yield 'negative offset keeps the caret at the start' => [
      -2,
      NULL,
      ['"abc', '^', 'unclosed_quote at offset -2: Quoted string is missing a closing double quote.'],
    ];
  }

  public function testPropertiesAndPreviousArePropagated(): void {
    $previous = new \RuntimeException('root cause');

    $exception = new ParseException('unknown_escape', 2, '"a\qb"', 'Unknown escape sequence "\q".', 'Use a supported escape.', $previous);

    $this->assertSame('unknown_escape', $exception->errorCode);
    $this->assertSame(2, $exception->offset);
    $this->assertSame('"a\qb"', $exception->cell);
    $this->assertSame('Unknown escape sequence "\q".', $exception->description);
    $this->assertSame('Use a supported escape.', $exception->hint);
    $this->assertSame(0, $exception->getCode());
    $this->assertSame($previous, $exception->getPrevious());
  }

}
