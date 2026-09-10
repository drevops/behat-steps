<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Driver\Core\Field\Parser\Exception;

use DrevOps\BehatSteps\Driver\Core\Field\Parser\Exception\ParseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the position-aware parse error message.
 *
 * @group core
 * @group fields
 */
#[CoversClass(ParseException::class)]
#[Group('core')]
#[Group('fields')]
class ParseExceptionTest extends TestCase {

  /**
   * Tests that the message carries the cell, a caret and the description.
   */
  public function testTheMessagePointsAtTheOffendingCharacter(): void {
    $exception = new ParseException('unquoted_semicolon', 3, 'abc; d', 'Semicolons are not allowed.');

    $this->assertSame("abc; d\n   ^\nunquoted_semicolon at offset 3: Semicolons are not allowed.", $exception->getMessage());
  }

  /**
   * Tests that a hint is appended as its own line.
   */
  public function testHintIsAppended(): void {
    $exception = new ParseException('unclosed_quote', 0, '"abc', 'Missing a closing quote.', 'Add a closing double quote.');

    $this->assertStringEndsWith("\nHint: Add a closing double quote.", $exception->getMessage());
  }

  /**
   * Tests that an empty hint adds no line.
   */
  public function testAnEmptyHintIsOmitted(): void {
    $exception = new ParseException('unclosed_quote', 0, '"abc', 'Missing a closing quote.', '');

    $this->assertStringNotContainsString('Hint:', $exception->getMessage());
  }

  /**
   * Tests that a negative offset still renders a caret.
   */
  public function testNegativeOffsetRendersTheCaretFirst(): void {
    $exception = new ParseException('unknown', -5, 'abc', 'Something went wrong.');

    $this->assertStringContainsString("abc\n^\n", $exception->getMessage());
  }

  /**
   * Tests that the reported details stay readable on the exception.
   */
  public function testTheDetailsAreExposed(): void {
    $previous = new \RuntimeException('cause');
    $exception = new ParseException('unknown_escape', 2, 'a\\qb', 'Unknown escape.', 'Use a known escape.', $previous);

    $this->assertSame('unknown_escape', $exception->errorCode);
    $this->assertSame(2, $exception->offset);
    $this->assertSame('a\\qb', $exception->cell);
    $this->assertSame('Unknown escape.', $exception->description);
    $this->assertSame('Use a known escape.', $exception->hint);
    $this->assertSame($previous, $exception->getPrevious());
  }

}
