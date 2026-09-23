<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Helper;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Helper\TableTransposeTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;

/**
 * Tests for TableTransposeTrait.
 */
#[CoversTrait(TableTransposeTrait::class)]
class TableTransposeTraitTest extends UnitTestCase {

  /**
   * A test implementation of TableTransposeTrait.
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

    $this->assertSame([['name' => 'John', 'age' => '30']], $this->testObject->callVertical($table));
  }

  public function testThreeColumnTableYieldsOneEntityPerValueColumn(): void {
    $table = new TableNode([['name', 'John', 'Jane'], ['age', '30', '25']]);

    $expected = [
      ['name' => 'John', 'age' => '30'],
      ['name' => 'Jane', 'age' => '25'],
    ];

    $this->assertSame($expected, $this->testObject->callVertical($table));
  }

  public function testSingleColumnTableIsRefused(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Vertical table must have at least 2 columns (field name and value).');

    $this->testObject->callVertical(new TableNode([['name']]));
  }

  public function testRepeatedFieldNameIsRefused(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Duplicate field names found: name.');

    $this->testObject->callVertical(new TableNode([['name', 'John'], ['name', 'Jane']]));
  }

  public function testBlankFieldNameIsRefused(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Field names cannot be empty.');

    $this->testObject->callVertical(new TableNode([['name', 'John'], [' ', 'Jane']]));
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

    $this->assertSame($expected, $this->testObject->callHorizontal($entities)->getRows());
  }

}

/**
 * Test implementation of TableTransposeTrait.
 *
 * Exposes the protected helper methods under the test.
 */
class TableTransposeTraitTestImplementation {

  use TableTransposeTrait;

  /**
   * Transpose a vertical table.
   *
   * @return array<int, array<string, string>>
   *   One array of values per entity.
   */
  public function callVertical(TableNode $table): array {
    return $this->tableTransposeVertical($table);
  }

  /**
   * Render transposed entities as a horizontal table.
   *
   * @param array<int, array<string, string>> $entities
   *   One array of values per entity.
   */
  public function callHorizontal(array $entities): TableNode {
    return $this->tableTransposeHorizontal($entities);
  }

}
