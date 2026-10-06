<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Helper\Web;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Helper\Web\TableTransposeTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests transposing a vertical Gherkin table into entity rows.
 */
#[CoversTrait(TableTransposeTrait::class)]
class TableTransposeTraitTest extends UnitTestCase {

  /**
   * A host composing the trait under test.
   */
  protected TableTransposeTraitTestImplementation $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new TableTransposeTraitTestImplementation();
  }

  public function testTwoColumnTableYieldsOneEntity(): void {
    $table = new TableNode([['name', 'John'], ['age', '30']]);

    $this->assertSame([['name' => 'John', 'age' => '30']], $this->testObject->tableTransposeVertical($table));
  }

  public function testThreeColumnTableYieldsOneEntityPerValueColumn(): void {
    $table = new TableNode([['name', 'John', 'Jane'], ['age', '30', '25']]);

    $expected = [
      ['name' => 'John', 'age' => '30'],
      ['name' => 'Jane', 'age' => '25'],
    ];

    $this->assertSame($expected, $this->testObject->tableTransposeVertical($table));
  }

  #[DataProvider('dataProviderInvalidTableIsRefused')]
  public function testInvalidTableIsRefused(array $rows, string $expected_message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $this->testObject->tableTransposeVertical(new TableNode($rows));
  }

  public static function dataProviderInvalidTableIsRefused(): array {
    return [
      'no rows' => [[], 'Vertical table must have at least 1 row.'],
      'row without cells' => [[[]], 'Vertical table must have at least 2 columns (field name and value).'],
      'single column' => [[['name']], 'Vertical table must have at least 2 columns (field name and value).'],
      'repeated field name' => [[['name', 'John'], ['name', 'Jane']], 'Duplicate field names found: name.'],
      'blank field name' => [[['name', 'John'], [' ', 'Jane']], 'Field names cannot be empty.'],
      'repeated blank field name' => [[['', 'John'], ['', 'Jane']], 'Field names cannot be empty.'],
    ];
  }

  public function testEntitiesAreRenderedAsHeaderRowAndValueRows(): void {
    $entities = [
      ['name' => 'John', 'age' => '30'],
      ['name' => 'Jane', 'age' => '25'],
    ];

    $expected = [
      ['name', 'age'],
      ['John', '30'],
      ['Jane', '25'],
    ];

    $this->assertSame($expected, $this->testObject->tableTransposeHorizontal($entities)->getRows());
  }

}

/**
 * Host composing the trait under test.
 */
class TableTransposeTraitTestImplementation extends WebRawContext {

  use TableTransposeTrait;

}
