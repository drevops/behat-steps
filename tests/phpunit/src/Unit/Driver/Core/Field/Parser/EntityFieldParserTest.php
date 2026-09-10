<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Driver\Core\Field\Parser;

use DrevOps\BehatSteps\Driver\Core\Field\FieldClassifierInterface;
use DrevOps\BehatSteps\Driver\Core\Field\Parser\EntityFieldParser;
use DrevOps\BehatSteps\Driver\Core\Field\Parser\Exception\MultipleParseException;
use DrevOps\BehatSteps\Driver\Core\Field\Parser\Exception\ParseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the entity-field cell grammar.
 *
 * @group core
 * @group fields
 */
#[CoversClass(EntityFieldParser::class)]
#[Group('core')]
#[Group('fields')]
class EntityFieldParserTest extends TestCase {

  /**
   * The classifier predicates that admit a field without configurable storage.
   *
   * @var array<int, string>
   */
  protected const BASE_PREDICATES = [
    'fieldIsBaseStandard',
    'fieldIsBaseComputedReadOnly',
    'fieldIsBaseComputedWritable',
    'fieldIsBaseCustomStorage',
  ];

  /**
   * The classifier predicates that need a bundle to answer.
   *
   * @var array<int, string>
   */
  protected const BUNDLE_PREDICATES = [
    'fieldIsBundleComputedReadOnly',
    'fieldIsBundleComputedWritable',
    'fieldIsBundleCustomStorage',
    'fieldIsBundleStorageBacked',
  ];

  /**
   * Tests scalar cells: bare values, lists, quoting and escapes.
   *
   * @param string $cell
   *   The cell as authored.
   * @param array<int, string> $expected
   *   The items the cell resolves to.
   */
  #[DataProvider('dataProviderScalarCell')]
  public function testScalarCell(string $cell, array $expected): void {
    $parser = new EntityFieldParser('node', $this->createClassifier(['field_a']));

    $this->assertSame(['field_a' => $expected], $parser->parse(['field_a' => $cell]));
  }

  /**
   * Data provider for scalar cells.
   *
   * @return array<string, array{string, array<int, string>}>
   *   Cell text and the expected items.
   */
  public static function dataProviderScalarCell(): array {
    return [
      'bare value' => ['Hello', ['Hello']],
      'surrounding whitespace' => ['  Hello  ', ['Hello']],
      'comma list' => ['a, b, c', ['a', 'b', 'c']],
      'comma list without spaces' => ['a,b', ['a', 'b']],
      'trailing comma yields an empty item' => ['a,', ['a', '']],
      'quoted item keeps its comma' => ['"a, b", c', ['a, b', 'c']],
      'quoted item keeps its semicolon' => ['"a; b"', ['a; b']],
      'quoted item keeps its whitespace' => ['"  padded  "', ['  padded  ']],
      'escaped quote' => ['"say \\"hi\\""', ['say "hi"']],
      'escaped backslash' => ['"a\\\\b"', ['a\\b']],
      'escaped newline, tab and return' => ['"a\\nb\\tc\\rd"', ["a\nb\tc\rd"]],
      'quote inside an unquoted item is literal' => ['<a href="x">y</a>', ['<a href="x">y</a>']],
      'whitespace-only cell' => ['   ', ['']],
      'bracketed token stays verbatim' => ['[node:1]', ['[node:1]']],
      'quoted compound-looking text stays scalar' => ['"uri:\\"https://example.com\\""', ['uri:"https://example.com"']],
    ];
  }

  /**
   * Tests compound cells: records, columns, tokens and whitespace.
   *
   * @param string $cell
   *   The cell as authored.
   * @param array<int, array<string, string>> $expected
   *   The records the cell resolves to.
   */
  #[DataProvider('dataProviderCompoundCell')]
  public function testCompoundCell(string $cell, array $expected): void {
    $parser = new EntityFieldParser('node', $this->createClassifier(['field_a']));

    $this->assertSame(['field_a' => $expected], $parser->parse(['field_a' => $cell]));
  }

  /**
   * Data provider for compound cells.
   *
   * @return array<string, array{string, array<int, array<string, string>>}>
   *   Cell text and the expected records.
   */
  public static function dataProviderCompoundCell(): array {
    return [
      'single column' => ['uri: "https://example.com"', [['uri' => 'https://example.com']]],
      'two columns' => ['uri: "https://example.com", title: "Example"', [['uri' => 'https://example.com', 'title' => 'Example']]],
      'no whitespace' => ['uri:"a",title:"b"', [['uri' => 'a', 'title' => 'b']]],
      'generous whitespace' => ["uri \t: \t\"a\" ,  title : \"b\"", [['uri' => 'a', 'title' => 'b']]],
      'two records' => ['uri: "a"; uri: "b"', [['uri' => 'a'], ['uri' => 'b']]],
      'token value' => ['target_id: [node:1]', [['target_id' => '[node:1]']]],
      'token and string columns' => ['target_id: [node:1], alt: "A"', [['target_id' => '[node:1]', 'alt' => 'A']]],
      'separators inside a quoted value' => ['title: "a, b; c"', [['title' => 'a, b; c']]],
      'separators inside a token' => ['target_id: [node:a,b]', [['target_id' => '[node:a,b]']]],
      'escapes inside a column value' => ['title: "a\\nb"', [['title' => "a\nb"]]],
      'underscored column names' => ['end_value: "2020-01-01"', [['end_value' => '2020-01-01']]],
      'repeated column keeps the last' => ['uri: "a", uri: "b"', [['uri' => 'b']]],
    ];
  }

  /**
   * Tests that a malformed cell reports its error code and offset.
   */
  #[DataProvider('dataProviderCellError')]
  public function testCellError(string $cell, string $expected_code, int $expected_offset): void {
    $parser = new EntityFieldParser('node', $this->createClassifier(['field_a']));

    try {
      $parser->parse(['field_a' => $cell]);
      $this->fail('Expected a ParseException.');
    }
    catch (ParseException $e) {
      $this->assertSame($expected_code, $e->errorCode);
      $this->assertSame($expected_offset, $e->offset);
      $this->assertSame($cell, $e->cell);
    }
  }

  /**
   * Data provider for malformed cells.
   *
   * @return array<string, array{string, string, int}>
   *   Cell text, the expected error code and the expected offset.
   */
  public static function dataProviderCellError(): array {
    return [
      'semicolon in an unquoted item' => ['a; b', 'unquoted_semicolon', 1],
      'unterminated quote' => ['"abc', 'unclosed_quote', 0],
      'dangling backslash' => ['"abc\\', 'unclosed_quote', 0],
      'unknown escape' => ['"a\\q"', 'unknown_escape', 2],
      'character after a closing quote' => ['"a"b', 'unexpected_character', 3],
      'bare compound value' => ['uri: "a", title: bare', 'unquoted_compound_value', 17],
      'compound column without a value' => ['uri: "a", title:', 'unquoted_compound_value', 16],
      'compound column that is not a pair' => ['uri: "a", title', 'invalid_column', 10],
      'empty compound record' => ['uri: "a";; uri: "b"', 'empty_record', 9],
      'empty compound column' => ['uri: "a",, title: "b"', 'empty_column', 9],
      'characters after a column quote' => ['uri: "a"x, title: "b"', 'trailing_characters', 8],
      'characters after a column token' => ['uri: [node:1]x, title: "b"', 'trailing_characters', 13],
      'unclosed token in a column' => ['uri: [node:1, title: "b"', 'unclosed_token', 5],
      'unclosed quote in a column' => ['uri: "a, title: b', 'unclosed_quote', 5],
      'column quote that closes early' => ['uri: "a, title: "b"', 'trailing_characters', 17],
    ];
  }

  /**
   * Tests that every error in one cell is reported together.
   */
  public function testAllErrorsInOneCellAreReportedTogether(): void {
    $parser = new EntityFieldParser('node', $this->createClassifier(['field_a']));

    try {
      $parser->parse(['field_a' => 'alt: "a", uri: bare, title: also_bare']);
      $this->fail('Expected a MultipleParseException.');
    }
    catch (MultipleParseException $e) {
      $this->assertCount(2, $e->errors);
      $this->assertStringContainsString('2 parse errors', $e->getMessage());
    }
  }

  /**
   * Tests that every failing record in one cell is reported together.
   */
  public function testAllFailingRecordsAreReportedTogether(): void {
    $parser = new EntityFieldParser('node', $this->createClassifier(['field_a']));

    try {
      $parser->parse(['field_a' => 'uri: "a"; uri: bare; uri: also_bare']);
      $this->fail('Expected a MultipleParseException.');
    }
    catch (MultipleParseException $e) {
      $this->assertCount(2, $e->errors);
    }
  }

  /**
   * Tests that errors from different cells are reported together.
   */
  public function testErrorsFromSeveralCellsAreReportedTogether(): void {
    $parser = new EntityFieldParser('node', $this->createClassifier(['field_a', 'field_b']));

    try {
      $parser->parse(['field_a' => '"unclosed', 'field_b' => 'a; b']);
      $this->fail('Expected a MultipleParseException.');
    }
    catch (MultipleParseException $e) {
      $this->assertCount(2, $e->errors);
      $this->assertSame('unclosed_quote', $e->errors[0]->errorCode);
      $this->assertSame('unquoted_semicolon', $e->errors[1]->errorCode);
      $this->assertSame('"unclosed', $e->cell);
    }
  }

  /**
   * Tests that a lone failing cell throws its own exception unwrapped.
   */
  public function testSingleFailingCellThrowsItsOwnException(): void {
    $parser = new EntityFieldParser('node', $this->createClassifier(['field_a', 'field_b']));

    try {
      $parser->parse(['field_a' => 'fine', 'field_b' => '"unclosed']);
      $this->fail('Expected a ParseException.');
    }
    catch (ParseException $e) {
      $this->assertNotInstanceOf(MultipleParseException::class, $e);
      $this->assertSame('unclosed_quote', $e->errorCode);
    }
  }

  /**
   * Tests that an empty configurable cell leaves the field unset.
   */
  #[DataProvider('dataProviderAnEmptyConfigurableValueIsDropped')]
  public function testAnEmptyConfigurableValueIsDropped(mixed $value): void {
    $parser = new EntityFieldParser('node', $this->createClassifier(['field_a']));

    $this->assertSame([], $parser->parse(['field_a' => $value]));
  }

  /**
   * Data provider for empty configurable values.
   *
   * @return array<string, array{mixed}>
   *   The value written into the cell.
   */
  public static function dataProviderAnEmptyConfigurableValueIsDropped(): array {
    return [
      'empty string' => [''],
      'null' => [NULL],
    ];
  }

  /**
   * Tests that a value carrying no cell grammar is handed on untouched.
   */
  #[DataProvider('dataProviderAnAssembledValueIsNotParsed')]
  public function testAnAssembledValueIsNotParsed(mixed $value): void {
    $parser = new EntityFieldParser('node', $this->createClassifier(['field_a']));

    $this->assertSame(['field_a' => $value], $parser->parse(['field_a' => $value]));
  }

  /**
   * Data provider for values a caller assembled itself.
   *
   * @return array<string, array{mixed}>
   *   The value written into the cell.
   */
  public static function dataProviderAnAssembledValueIsNotParsed(): array {
    return [
      'list of scalars' => [['alpha', 'beta']],
      'single record' => [['uri' => 'https://example.com', 'title' => 'Example']],
      'list of records' => [[['uri' => 'https://example.com']]],
      'integer' => [7],
      'boolean' => [TRUE],
    ];
  }

  /**
   * Tests that a base field's value reaches the entity untouched.
   */
  #[DataProvider('dataProviderBaseFieldValueIsNotParsed')]
  public function testBaseFieldValueIsNotParsed(string $predicate): void {
    $parser = new EntityFieldParser('node', $this->createClassifier([], $predicate));

    $this->assertSame(['title' => 'a, b'], $parser->parse(['title' => 'a, b']));
  }

  /**
   * Data provider for the base-field predicates.
   *
   * @return array<string, array{string}>
   *   The predicate that answers TRUE for the field.
   */
  public static function dataProviderBaseFieldValueIsNotParsed(): array {
    return [
      'standard' => ['fieldIsBaseStandard'],
      'computed read-only' => ['fieldIsBaseComputedReadOnly'],
      'computed writable' => ['fieldIsBaseComputedWritable'],
      'custom storage' => ['fieldIsBaseCustomStorage'],
    ];
  }

  /**
   * Tests that a bundle-scoped field is known once the bundle is supplied.
   */
  #[DataProvider('dataProviderBundleFieldIsKnownWhenTheBundleIsSupplied')]
  public function testBundleFieldIsKnownWhenTheBundleIsSupplied(string $predicate): void {
    $parser = new EntityFieldParser('node', $this->createClassifier([], $predicate), 'article');

    $this->assertSame(['field_a' => 'a, b'], $parser->parse(['field_a' => 'a, b']));
  }

  /**
   * Data provider for the bundle-scoped predicates.
   *
   * @return array<string, array{string}>
   *   The predicate that answers TRUE for the field.
   */
  public static function dataProviderBundleFieldIsKnownWhenTheBundleIsSupplied(): array {
    return [
      'computed read-only' => ['fieldIsBundleComputedReadOnly'],
      'computed writable' => ['fieldIsBundleComputedWritable'],
      'custom storage' => ['fieldIsBundleCustomStorage'],
      'storage backed' => ['fieldIsBundleStorageBacked'],
    ];
  }

  /**
   * Tests that a bundle-scoped field is unknown without a bundle.
   */
  #[DataProvider('dataProviderBundleFieldIsUnknownWithoutBundle')]
  public function testBundleFieldIsUnknownWithoutBundle(string $predicate): void {
    $parser = new EntityFieldParser('node', $this->createClassifier([], $predicate));

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Field "field_a" does not exist on entity type "node".');

    $parser->parse(['field_a' => 'a']);
  }

  /**
   * Data provider for the bundle-scoped predicates.
   *
   * @return array<string, array{string}>
   *   The predicate that answers TRUE for the field.
   */
  public static function dataProviderBundleFieldIsUnknownWithoutBundle(): array {
    return self::dataProviderBundleFieldIsKnownWhenTheBundleIsSupplied();
  }

  /**
   * Tests that a field belonging to no classification is rejected.
   */
  public function testAnUnknownFieldIsRejected(): void {
    $parser = new EntityFieldParser('node', $this->createClassifier());

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Field "field_a" does not exist on entity type "node".');

    $parser->parse(['field_a' => 'a']);
  }

  /**
   * Tests that an ignored property is accepted without classification.
   */
  public function testAnIgnoredPropertyIsAccepted(): void {
    $parser = new EntityFieldParser('node', $this->createClassifier());

    $this->assertSame(['author' => 'alice'], $parser->ignoring(['author'])->parse(['author' => 'alice']));
  }

  /**
   * Tests that the ignore list is fluent.
   */
  public function testIgnoringReturnsTheParser(): void {
    $parser = new EntityFieldParser('node', $this->createClassifier());

    $this->assertSame($parser, $parser->ignoring(['author']));
  }

  /**
   * Tests that a 'field:column' header merges into one compound field.
   */
  public function testMulticolumnHeaderBuildsOneRecord(): void {
    $parser = new EntityFieldParser('node', $this->createClassifier(['field_a']));

    $parsed = $parser->parse(['field_a:value' => 'Body text', ':format' => 'full_html']);

    $this->assertSame(['field_a' => [['value' => 'Body text', 'format' => 'full_html']]], $parsed);
  }

  /**
   * Tests that a multicolumn header pairs its columns delta by delta.
   */
  public function testMulticolumnHeaderPairsColumnsByDelta(): void {
    $parser = new EntityFieldParser('node', $this->createClassifier(['field_a']));

    $parsed = $parser->parse(['field_a:value' => 'one, two', ':format' => 'plain, full_html']);

    $this->assertSame(['field_a' => [['value' => 'one', 'format' => 'plain'], ['value' => 'two', 'format' => 'full_html']]], $parsed);
  }

  /**
   * Tests that a second 'field:column' header opens a new field.
   */
  public function testSecondMulticolumnHeaderOpensNewField(): void {
    $parser = new EntityFieldParser('node', $this->createClassifier(['field_a', 'field_b']));

    $parsed = $parser->parse(['field_a:value' => 'a', 'field_b:value' => 'b']);

    $this->assertSame(['field_a' => [['value' => 'a']], 'field_b' => [['value' => 'b']]], $parsed);
  }

  /**
   * Tests that a plain header closes the preceding multicolumn field.
   */
  public function testPlainHeaderClosesTheMulticolumnField(): void {
    $parser = new EntityFieldParser('node', $this->createClassifier(['field_a', 'field_b']));

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Field name missing for :format');

    $parser->parse(['field_a:value' => 'a', 'field_b' => 'b', ':format' => 'plain']);
  }

  /**
   * Tests that a column continuation without a preceding field is rejected.
   */
  public function testAnOrphanColumnContinuationIsRejected(): void {
    $parser = new EntityFieldParser('node', $this->createClassifier(['field_a']));

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Field name missing for :format');

    $parser->parse([':format' => 'full_html']);
  }

  /**
   * Tests that a multicolumn header on a base field is left as authored.
   */
  public function testMulticolumnHeaderOnBaseFieldIsNotParsed(): void {
    $parser = new EntityFieldParser('node', $this->createClassifier([], 'fieldIsBaseStandard'));

    $this->assertSame(['title:value' => 'a, b'], $parser->parse(['title:value' => 'a, b']));
  }

  /**
   * Tests that a numeric header is read as a field name.
   */
  public function testNumericHeaderIsReadAsFieldName(): void {
    $parser = new EntityFieldParser('node', $this->createClassifier(['0']));

    $this->assertSame(['0' => ['a']], $parser->parse(['a']));
  }

  /**
   * Builds a classifier that admits the named fields and predicate.
   *
   * @param array<int, string> $configurable
   *   Field names 'fieldIsConfigurable()' answers TRUE for.
   * @param string $true_predicate
   *   The single other predicate that answers TRUE for every field name.
   *   Empty for a classifier that recognises nothing else.
   */
  protected function createClassifier(array $configurable = [], string $true_predicate = ''): FieldClassifierInterface {
    $classifier = $this->createMock(FieldClassifierInterface::class);

    $classifier->method('fieldIsConfigurable')->willReturnCallback(
      static fn(string $entity_type, string $field_name): bool => in_array($field_name, $configurable, TRUE)
    );

    foreach (array_merge(self::BASE_PREDICATES, self::BUNDLE_PREDICATES) as $predicate) {
      $classifier->method($predicate)->willReturn($predicate === $true_predicate);
    }

    return $classifier;
  }

}
