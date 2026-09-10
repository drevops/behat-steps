<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Driver\Core\Field\Parser\Exception;

use DrevOps\BehatSteps\Driver\Core\Field\Parser\Exception\MultipleParseException;
use DrevOps\BehatSteps\Driver\Core\Field\Parser\Exception\ParseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the aggregate parse error.
 *
 * @group core
 * @group fields
 */
#[CoversClass(MultipleParseException::class)]
#[Group('core')]
#[Group('fields')]
class MultipleParseExceptionTest extends TestCase {

  /**
   * Tests that several errors are summarised by their codes.
   */
  public function testSeveralErrorsAreSummarisedByCode(): void {
    $errors = [
      new ParseException('unclosed_quote', 0, 'a', 'First problem.'),
      new ParseException('unknown_escape', 4, 'a', 'Second problem.'),
    ];

    $exception = new MultipleParseException($errors, 'the cell');

    $this->assertSame('2 parse errors: unclosed_quote, unknown_escape', $exception->description);
    $this->assertSame($errors, $exception->errors);
  }

  /**
   * Tests that the first error supplies the code and offset.
   */
  public function testTheFirstErrorSuppliesTheCodeAndOffset(): void {
    $exception = new MultipleParseException([
      new ParseException('unclosed_quote', 7, 'a', 'First problem.'),
      new ParseException('unknown_escape', 4, 'a', 'Second problem.'),
    ], 'the cell');

    $this->assertSame('unclosed_quote', $exception->errorCode);
    $this->assertSame(7, $exception->offset);
    $this->assertSame('the cell', $exception->cell);
  }

  /**
   * Tests that a lone error contributes its own description.
   */
  public function testLoneErrorKeepsItsDescription(): void {
    $exception = new MultipleParseException([new ParseException('unclosed_quote', 0, 'a', 'Only problem.')], 'the cell');

    $this->assertSame('Only problem.', $exception->description);
  }

  /**
   * Tests that a filtered list with gaps in its keys is read in order.
   */
  public function testGapKeyedListIsReadInOrder(): void {
    $exception = new MultipleParseException([3 => new ParseException('unclosed_quote', 9, 'a', 'Only problem.')], 'the cell');

    $this->assertSame('unclosed_quote', $exception->errorCode);
    $this->assertSame(9, $exception->offset);
    $this->assertSame('Only problem.', $exception->description);
  }

  /**
   * Tests that an empty list is refused.
   */
  public function testAnEmptyListIsRefused(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('MultipleParseException requires at least one error.');

    new MultipleParseException([], 'the cell');
  }

  /**
   * Tests that a previous throwable is chained.
   */
  public function testThePreviousThrowableIsChained(): void {
    $previous = new \RuntimeException('cause');
    $exception = new MultipleParseException([new ParseException('unclosed_quote', 0, 'a', 'Only problem.')], 'the cell', $previous);

    $this->assertSame($previous, $exception->getPrevious());
  }

}
