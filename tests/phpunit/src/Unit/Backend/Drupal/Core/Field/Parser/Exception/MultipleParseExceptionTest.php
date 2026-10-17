<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal\Core\Field\Parser\Exception;

use DrevOps\BehatSteps\Backend\Drupal\Core\Field\Parser\Exception\MultipleParseException;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\Parser\Exception\ParseException;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the container that raises several parse errors together.
 */
#[CoversClass(MultipleParseException::class)]
#[Group('exception')]
class MultipleParseExceptionTest extends UnitTestCase {

  public function testEmptyListIsRejected(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('MultipleParseException requires at least one error.');

    new MultipleParseException([], 'cell');
  }

  public function testSingleErrorKeepsItsDescription(): void {
    $error = new ParseException('empty_record', 9, 'uri: "a";', 'Empty compound record (consecutive ";" or trailing ";").');

    $exception = new MultipleParseException([$error], 'uri: "a";');

    $this->assertSame('empty_record', $exception->errorCode);
    $this->assertSame(9, $exception->offset);
    $this->assertSame('Empty compound record (consecutive ";" or trailing ";").', $exception->description);
    $this->assertSame([$error], $exception->errors);
  }

  public function testSeveralErrorsAreSummarizedFromTheFirst(): void {
    // Keys 3 and 7 leave index 0 unset, so the first error comes from
    // iteration order.
    $errors = [
      3 => new ParseException('unclosed_quote', 4, '"a', 'Quoted string is missing a closing double quote.'),
      7 => new ParseException('empty_record', 9, 'uri: "a";', 'Empty compound record (consecutive ";" or trailing ";").'),
    ];
    $previous = new \RuntimeException('root cause');

    $exception = new MultipleParseException($errors, 'reported cell', $previous);

    $this->assertSame('unclosed_quote', $exception->errorCode);
    $this->assertSame(4, $exception->offset);
    $this->assertSame('reported cell', $exception->cell);
    $this->assertSame('2 parse errors: unclosed_quote, empty_record', $exception->description);
    $this->assertNull($exception->hint);
    $this->assertSame($errors, $exception->errors);
    $this->assertSame($previous, $exception->getPrevious());
    $this->assertStringStartsWith('reported cell' . PHP_EOL . '    ^' . PHP_EOL, $exception->getMessage());
  }

}
