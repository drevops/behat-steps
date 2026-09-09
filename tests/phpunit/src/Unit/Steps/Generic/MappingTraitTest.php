<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Generic;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Behat\Context\RawContext;
use DrevOps\BehatSteps\Steps\Generic\MappingTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for MappingTrait.
 */
#[CoversTrait(MappingTrait::class)]
class MappingTraitTest extends UnitTestCase {

  /**
   * A test implementation of MappingTrait.
   */
  protected MappingTraitTestImplementation $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new MappingTraitTestImplementation();
    $this->testObject->setParameters([
      'mappings' => [
        'User Login' => '/user/login',
        'Home' => '/',
      ],
    ]);
  }

  /**
   * Tests that a token is replaced by the value it maps to.
   *
   * @param string $argument
   *   The raw step argument.
   * @param string $expected
   *   The expected result.
   */
  #[DataProvider('dataProviderTransformValue')]
  public function testTransformValue(string $argument, string $expected): void {
    $this->assertSame($expected, $this->testObject->mappingTransformValue($argument));
  }

  public static function dataProviderTransformValue(): array {
    return [
      'token alone' => ['{{ User Login }}', '/user/login'],
      'token without padding' => ['{{User Login}}', '/user/login'],
      'token within text' => ['go to {{ Home }} now', 'go to / now'],
      'two tokens' => ['{{ Home }} then {{ User Login }}', '/ then /user/login'],
      'no token' => ['/plain/path', '/plain/path'],
    ];
  }

  public function testTransformTableResolvesEveryCell(): void {
    $table = new TableNode([
      ['path', 'label'],
      ['{{ User Login }}', 'Log in'],
      ['{{ Home }}', 'Home'],
    ]);

    $expected = [
      ['path', 'label'],
      ['/user/login', 'Log in'],
      ['/', 'Home'],
    ];

    $this->assertSame($expected, $this->testObject->mappingTransformTable($table)->getRows());
  }

  public function testUnknownKeyFailsTheStep(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No such mapping: Nonexistent Key');

    $this->testObject->mappingTransformValue('{{ Nonexistent Key }}');
  }

}

/**
 * Test implementation of MappingTrait.
 */
class MappingTraitTestImplementation extends RawContext {

  use MappingTrait;

}
