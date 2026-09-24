<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Helper;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Behat\Context\DrupalApiInterface;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Helper\DrupalApiTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;

/**
 * Tests transposing a vertical Gherkin table into entity rows.
 */
#[CoversTrait(DrupalApiTrait::class)]
class DrupalApiTraitTablesTest extends UnitTestCase {

  /**
   * A host composing the trait under test.
   */
  protected DrupalApiTraitTablesTestImplementation $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new DrupalApiTraitTablesTestImplementation();
  }

  public function testTwoColumnTableYieldsOneEntity(): void {
    $table = new TableNode([['name', 'John'], ['age', '30']]);

    $this->assertSame([['name' => 'John', 'age' => '30']], $this->testObject->transposeVerticalTable($table));
  }

  public function testThreeColumnTableYieldsOneEntityPerValueColumn(): void {
    $table = new TableNode([['name', 'John', 'Jane'], ['age', '30', '25']]);

    $expected = [
      ['name' => 'John', 'age' => '30'],
      ['name' => 'Jane', 'age' => '25'],
    ];

    $this->assertSame($expected, $this->testObject->transposeVerticalTable($table));
  }

  public function testSingleColumnTableIsRefused(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Vertical table must have at least 2 columns (field name and value).');

    $this->testObject->transposeVerticalTable(new TableNode([['name']]));
  }

  public function testRepeatedFieldNameIsRefused(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Duplicate field names found: name.');

    $this->testObject->transposeVerticalTable(new TableNode([['name', 'John'], ['name', 'Jane']]));
  }

  public function testBlankFieldNameIsRefused(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Field names cannot be empty.');

    $this->testObject->transposeVerticalTable(new TableNode([['name', 'John'], [' ', 'Jane']]));
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

    $this->assertSame($expected, $this->testObject->buildHorizontalTable($entities)->getRows());
  }

}

/**
 * Host composing the trait under test.
 */
class DrupalApiTraitTablesTestImplementation extends WebRawContext implements DrupalApiInterface {

  use DrupalApiTrait;

}
