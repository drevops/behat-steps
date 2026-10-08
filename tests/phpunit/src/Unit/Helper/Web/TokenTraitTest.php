<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Helper\Web;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Helper\Web\TokenTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for TokenTrait.
 */
#[CoversTrait(TokenTrait::class)]
class TokenTraitTest extends UnitTestCase {

  /**
   * A test implementation of TokenTrait.
   */
  protected TokenTraitTestImplementation $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new TokenTraitTestImplementation();
  }

  #[DataProvider('dataProviderReplaceInTable')]
  public function testReplaceInTable(array $rows, array $expected): void {
    $table = new TableNode($rows);

    $replaced = $this->testObject->callReplaceInTable($table, static fn(string $cell): string => str_replace('[token]', 'value', $cell));

    $this->assertSame($expected, $replaced->getRows());
    $this->assertSame($rows, $table->getRows());
  }

  public static function dataProviderReplaceInTable(): array {
    return [
      'every cell' => [
        [['name', 'title'], ['[token]', 'A [token] here']],
        [['name', 'title'], ['value', 'A value here']],
      ],
      'no tokens' => [
        [['name'], ['plain']],
        [['name'], ['plain']],
      ],
      'empty table' => [[], []],
    ];
  }

}

/**
 * Test implementation of TokenTrait.
 *
 * Exposes the protected helper method under the test.
 */
class TokenTraitTestImplementation {

  use TokenTrait;

  public function callReplaceInTable(TableNode $table, callable $replace): TableNode {
    return $this->tokenReplaceInTable($table, $replace);
  }

}
