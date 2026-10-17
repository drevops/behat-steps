<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal\Core\Field\Parser;

use DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldClassifierInterface;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\Parser\EntityFieldParser;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\Parser\Exception\MultipleParseException;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\Parser\Exception\ParseException;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the cell grammar and the field rules of the entity-field parser.
 */
#[CoversClass(EntityFieldParser::class)]
#[Group('fields')]
class EntityFieldParserTest extends UnitTestCase {

  /**
   * The kind the classifier stub reports for each field it knows.
   */
  protected const FIELD_KINDS = [
    'field_text' => 'configurable',
    'field_link' => 'configurable',
    'title' => 'base_standard',
    'moderation_state' => 'base_computed_read_only',
    'path' => 'base_computed_writable',
    'base_custom' => 'base_custom_storage',
    'bundle_computed_ro' => 'bundle_computed_read_only',
    'bundle_computed_rw' => 'bundle_computed_writable',
    'bundle_custom' => 'bundle_custom_storage',
    'bundle_backed' => 'bundle_storage_backed',
  ];

  /**
   * Tests how a scalar cell splits into items.
   *
   * @param string $cell
   *   The cell text of a configurable field.
   * @param array<int, string> $expected
   *   The items the cell parses into.
   */
  #[DataProvider('dataProviderParseScalarCell')]
  public function testParseScalarCell(string $cell, array $expected): void {
    $this->assertSame(['field_text' => $expected], $this->createParser()->parse(['field_text' => $cell]));
  }

  public static function dataProviderParseScalarCell(): \Iterator {
    yield 'plain text' => ['Hello world', ['Hello world']];
    yield 'comma-separated items' => ['a, b ,c', ['a', 'b', 'c']];
    yield 'quoted item holding a comma' => ['"a, b", c', ['a, b', 'c']];
    yield 'quoted item holding a semicolon' => ['"a; b"', ['a; b']];
    yield 'quoted item after an unquoted one' => ['a, "b"', ['a', 'b']];
    yield 'whitespace after a closing quote' => ['"a"  , b', ['a', 'b']];
    yield 'every escape in a quoted item' => ['"tab\there \"quoted\" back\\\\slash\nnew\rret"', ["tab\there \"quoted\" back\\slash\nnew\rret"]];
    yield 'quote inside an unquoted item' => ['<a href="x">link</a>', ['<a href="x">link</a>']];
    yield 'compound pattern inside a quoted item' => ['"uri: \"x\""', ['uri: "x"']];
    yield 'identifier and colon followed by plain text' => ['Note: plain text', ['Note: plain text']];
    yield 'trailing comma' => ['a,', ['a', '']];
    yield 'whitespace only' => ['   ', ['']];
  }

  /**
   * Tests that an empty value drops the field from the result.
   *
   * @param string|null $value
   *   The raw value of a configurable field.
   */
  #[DataProvider('dataProviderParseDropsEmptyValue')]
  public function testParseDropsEmptyValue(?string $value): void {
    $this->assertSame(['title' => 'Kept'], $this->createParser()->parse(['field_text' => $value, 'title' => 'Kept']));
  }

  public static function dataProviderParseDropsEmptyValue(): \Iterator {
    yield 'empty string' => [''];
    yield 'null' => [NULL];
  }

  /**
   * Tests how a compound cell splits into records and columns.
   *
   * @param string $cell
   *   The cell text of a configurable field.
   * @param array<int, array<string, string>> $expected
   *   The records the cell parses into.
   */
  #[DataProvider('dataProviderParseCompoundCell')]
  public function testParseCompoundCell(string $cell, array $expected): void {
    $this->assertSame(['field_link' => $expected], $this->createParser()->parse(['field_link' => $cell]));
  }

  public static function dataProviderParseCompoundCell(): \Iterator {
    yield 'one record' => ['uri: "http://example.com", title: "Example"', [['uri' => 'http://example.com', 'title' => 'Example']]];
    yield 'several records' => ['uri: "a"; uri: "b"', [['uri' => 'a'], ['uri' => 'b']]];
    yield 'whitespace around the colon' => ['uri : "a"', [['uri' => 'a']]];
    yield 'token value' => ['target: [term:Tag1]', [['target' => '[term:Tag1]']]];
    yield 'separator inside a token' => ['target: [term:a,b]', [['target' => '[term:a,b]']]];
    yield 'separators inside a quoted value' => ['title: "a, b; c"', [['title' => 'a, b; c']]];
    yield 'escaped quote inside a quoted value' => ['title: "say \"hi\""', [['title' => 'say "hi"']]];
  }

  /**
   * Tests that 'field:column' and ':column' headers merge into 1 field.
   *
   * @param array<string, string> $values
   *   The raw values, keyed by column header.
   * @param array<string, mixed> $expected
   *   The parsed values.
   */
  #[DataProvider('dataProviderParseMulticolumnHeaders')]
  public function testParseMulticolumnHeaders(array $values, array $expected): void {
    $this->assertSame($expected, $this->createParser()->parse($values));
  }

  public static function dataProviderParseMulticolumnHeaders(): \Iterator {
    yield 'one item' => [
      ['field_link:uri' => 'http://a', ':title' => 'A'],
      ['field_link' => [['uri' => 'http://a', 'title' => 'A']]],
    ];
    yield 'several items' => [
      ['field_link:uri' => 'http://a, http://b', ':title' => 'A, B'],
      ['field_link' => [['uri' => 'http://a', 'title' => 'A'], ['uri' => 'http://b', 'title' => 'B']]],
    ];
    yield 'a plain header in between' => [
      ['field_link:uri' => 'http://a', 'title' => 'Kept'],
      ['title' => 'Kept', 'field_link' => [['uri' => 'http://a']]],
    ];
  }

  /**
   * Tests which non-configurable fields pass through unparsed.
   *
   * @param array<string, string> $values
   *   The raw values.
   * @param string|null $bundle
   *   The bundle of the stub, or NULL for none.
   * @param array<int, string> $ignored
   *   The property names accepted without validation.
   */
  #[DataProvider('dataProviderParsePassesKnownFieldsThrough')]
  public function testParsePassesKnownFieldsThrough(array $values, ?string $bundle, array $ignored): void {
    $this->assertSame($values, $this->createParser($bundle)->ignoring($ignored)->parse($values));
  }

  public static function dataProviderParsePassesKnownFieldsThrough(): \Iterator {
    yield 'standard base field' => [['title' => 'a, "b"; c'], 'article', []];
    yield 'computed read-only base field' => [['moderation_state' => 'draft'], NULL, []];
    yield 'computed writable base field' => [['path' => '/about'], NULL, []];
    yield 'custom-storage base field' => [['base_custom' => 'value'], NULL, []];
    yield 'bundle computed read-only field' => [['bundle_computed_ro' => 'value'], 'article', []];
    yield 'bundle computed writable field' => [['bundle_computed_rw' => 'value'], 'article', []];
    yield 'bundle custom-storage field' => [['bundle_custom' => 'value'], 'article', []];
    yield 'bundle storage-backed field' => [['bundle_backed' => 'value'], 'article', []];
    yield 'ignored property' => [['author' => 'admin'], 'article', ['author']];
  }

  public function testIgnoringReturnsTheParser(): void {
    $parser = $this->createParser();

    $this->assertSame($parser, $parser->ignoring(['author']));
  }

  /**
   * Tests that a header the parser cannot place is rejected.
   *
   * @param array<string, string> $values
   *   The raw values.
   * @param string|null $bundle
   *   The bundle of the stub, or NULL for none.
   * @param string $expected_message
   *   The message the parser is expected to throw with.
   */
  #[DataProvider('dataProviderParseRejectsUnplacedHeader')]
  public function testParseRejectsUnplacedHeader(array $values, ?string $bundle, string $expected_message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $this->createParser($bundle)->parse($values);
  }

  public static function dataProviderParseRejectsUnplacedHeader(): \Iterator {
    yield 'unknown field' => [['field_missing' => 'value'], 'article', 'The field "field_missing" does not exist on entity type "node".'];
    yield 'bundle field without a bundle' => [['bundle_backed' => 'value'], NULL, 'The field "bundle_backed" does not exist on entity type "node".'];
    yield 'continuation without a field' => [[':title' => 'A'], 'article', 'Field name missing for ":title".'];
    yield 'continuation after a plain header' => [['field_link:uri' => 'http://a', 'title' => 'Kept', ':title' => 'A'], 'article', 'Field name missing for ":title".'];
  }

  /**
   * Tests that a malformed cell raises the error at its offset.
   *
   * @param string $field
   *   The configurable field the cell belongs to.
   * @param string $cell
   *   The cell text.
   * @param string $expected_code
   *   The machine-readable error code.
   * @param int $expected_offset
   *   The offset of the error within the cell.
   * @param string $expected_description
   *   The human-readable description.
   */
  #[DataProvider('dataProviderParseReportsCellError')]
  public function testParseReportsCellError(string $field, string $cell, string $expected_code, int $expected_offset, string $expected_description): void {
    try {
      $this->createParser()->parse([$field => $cell]);
      $this->fail('Expected ParseException.');
    }
    catch (ParseException $exception) {
      $this->assertNotInstanceOf(MultipleParseException::class, $exception);
      $this->assertSame($expected_code, $exception->errorCode);
      $this->assertSame($expected_offset, $exception->offset);
      $this->assertSame($expected_description, $exception->description);
      $this->assertSame($cell, $exception->cell);
    }
  }

  public static function dataProviderParseReportsCellError(): \Iterator {
    yield 'unclosed quote' => ['field_text', '"abc', 'unclosed_quote', 0, 'Quoted string is missing a closing double quote.'];
    yield 'dangling backslash' => ['field_text', '"abc\\', 'unclosed_quote', 0, 'Quoted string ends with a dangling backslash.'];
    yield 'unknown escape' => ['field_text', '"a\qb"', 'unknown_escape', 2, 'Unknown escape sequence "\q".'];
    yield 'character after a closing quote' => ['field_text', '"a" b', 'unexpected_character', 4, 'Expected "," or end of value after closing quote, found "b".'];
    yield 'unquoted semicolon' => ['field_text', 'a; b', 'unquoted_semicolon', 1, 'Semicolons are not allowed in unquoted scalar values.'];
    yield 'empty record' => ['field_link', 'uri: "a";; uri: "b"', 'empty_record', 9, 'Empty compound record (consecutive ";" or trailing ";").'];
    yield 'trailing record separator' => ['field_link', 'uri: "a";', 'empty_record', 9, 'Empty compound record (consecutive ";" or trailing ";").'];
    yield 'empty column' => ['field_link', 'uri: "a",, title: "b"', 'empty_column', 9, 'Empty compound column (consecutive "," or trailing ",").'];
    yield 'column without a key' => ['field_link', 'uri: "a", nonsense', 'invalid_column', 10, 'Compound column "nonsense" is not in "key: value" form.'];
    yield 'column without a value' => ['field_link', 'uri: "a", title:', 'unquoted_compound_value', 16, 'Compound column "title" has no value.'];
    yield 'bare column value' => ['field_link', 'uri: "a", title: bare', 'unquoted_compound_value', 17, 'Compound column "title" must use a quoted string or token.'];
    yield 'characters after a quoted value' => ['field_link', 'uri: "a"x', 'trailing_characters', 8, 'Trailing characters after closing quote in column "uri".'];
    yield 'characters after a token' => ['field_link', 'target: [term:a]x', 'trailing_characters', 16, 'Trailing characters after closing token in column "target".'];
    yield 'unclosed token' => ['field_link', 'target: [term:a', 'unclosed_token', 8, 'Token is missing a closing "]".'];
    yield 'unclosed quote in a column' => ['field_link', 'uri: "abc', 'unclosed_quote', 5, 'Quoted string is missing a closing double quote.'];
    yield 'unknown escape in a column' => ['field_link', 'uri: "a\qb"', 'unknown_escape', 7, 'Unknown escape sequence "\q".'];
  }

  public function testParseErrorMessageMarksTheOffset(): void {
    $expected = implode(PHP_EOL, [
      'a; b',
      ' ^',
      'unquoted_semicolon at offset 1: Semicolons are not allowed in unquoted scalar values.',
      'Hint: Wrap the value in double quotes: "a".',
    ]);

    $this->expectException(ParseException::class);
    $this->expectExceptionMessage($expected);

    $this->createParser()->parse(['field_text' => 'a; b']);
  }

  /**
   * Tests that several errors are raised together.
   *
   * @param array<string, string> $values
   *   The raw values.
   * @param array<int, string> $expected_codes
   *   The codes of the wrapped errors, in order.
   * @param string $expected_cell
   *   The cell the aggregated exception reports.
   */
  #[DataProvider('dataProviderParseAggregatesErrors')]
  public function testParseAggregatesErrors(array $values, array $expected_codes, string $expected_cell): void {
    try {
      $this->createParser()->parse($values);
      $this->fail('Expected MultipleParseException.');
    }
    catch (MultipleParseException $exception) {
      $this->assertSame($expected_codes, array_map(static fn(ParseException $error): string => $error->errorCode, $exception->errors));
      $this->assertSame($expected_cell, $exception->cell);
      $this->assertSame(sprintf('%d parse errors: %s', count($expected_codes), implode(', ', $expected_codes)), $exception->description);
    }
  }

  public static function dataProviderParseAggregatesErrors(): \Iterator {
    yield 'several columns of 1 record' => [
      ['field_link' => 'uri: "a", title: bare, alt: bare'],
      ['unquoted_compound_value', 'unquoted_compound_value'],
      'uri: "a", title: bare, alt: bare',
    ];
    yield 'several records' => [
      ['field_link' => 'uri: "a", title: bare; uri: "b",, alt: "c"'],
      ['unquoted_compound_value', 'empty_column'],
      'uri: "a", title: bare; uri: "b",, alt: "c"',
    ];
    yield 'several cells' => [
      ['field_text' => '"a', 'field_link' => 'uri: "a";'],
      ['unclosed_quote', 'empty_record'],
      '"a',
    ];
  }

  /**
   * Create a parser for 'node' over a classifier that knows FIELD_KINDS.
   *
   * @param string|null $bundle
   *   The bundle of the stub being parsed, or NULL for none.
   */
  protected function createParser(?string $bundle = 'article'): EntityFieldParser {
    $methods = [
      'fieldIsConfigurable' => 'configurable',
      'fieldIsBaseStandard' => 'base_standard',
      'fieldIsBaseComputedReadOnly' => 'base_computed_read_only',
      'fieldIsBaseComputedWritable' => 'base_computed_writable',
      'fieldIsBaseCustomStorage' => 'base_custom_storage',
      'fieldIsBundleComputedReadOnly' => 'bundle_computed_read_only',
      'fieldIsBundleComputedWritable' => 'bundle_computed_writable',
      'fieldIsBundleCustomStorage' => 'bundle_custom_storage',
      'fieldIsBundleStorageBacked' => 'bundle_storage_backed',
    ];

    $classifier = $this->createStub(FieldClassifierInterface::class);

    foreach ($methods as $method => $kind) {
      $classifier->method($method)->willReturnCallback(static fn(string $entity_type, string $field_name): bool => (static::FIELD_KINDS[$field_name] ?? NULL) === $kind);
    }

    return new EntityFieldParser('node', $classifier, $bundle);
  }

}
