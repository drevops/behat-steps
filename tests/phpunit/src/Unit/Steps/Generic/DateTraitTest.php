<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Generic;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Behat\Context\RawContext;
use DrevOps\BehatSteps\Steps\Generic\DateTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for DateTrait.
 */
#[CoversTrait(DateTrait::class)]
class DateTraitTest extends UnitTestCase {

  /**
   * A test implementation of DateTrait.
   *
   * @var \DrevOps\BehatSteps\Tests\Unit\Steps\Generic\DateTraitTestImplementation
   */
  protected $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new DateTraitTestImplementation();
  }

  #[DataProvider('dataProviderDateRelativeProcessValue')]
  public function testDateRelativeProcessValue(string $input, string $expected, ?int $now = NULL): void {
    $result = $this->testObject::dateRelativeProcessValue($input, $now);
    $this->assertEquals($expected, $result);
  }

  public static function dataProviderDateRelativeProcessValue(): array {
    $timestamp = DateTraitTestImplementation::CLOCK;

    return [
      'string without token' => [
        'This string has no tokens',
        'This string has no tokens',
      ],
      'tomorrow' => [
        '[relative:+1 day]',
        (string) strtotime('+1 day', $timestamp),
      ],
      'yesterday' => [
        '[relative:-1 day]',
        (string) strtotime('-1 day', $timestamp),
      ],
      'next week' => [
        '[relative:+1 week]',
        (string) strtotime('+1 week', $timestamp),
      ],
      'yesterday with Y-m-d format' => [
        '[relative:-1 day#Y-m-d]',
        date('Y-m-d', strtotime('-1 day', $timestamp)),
      ],
      'tomorrow with custom format' => [
        '[relative:+1 day#d/m/Y]',
        date('d/m/Y', strtotime('+1 day', $timestamp)),
      ],
      'multiple tokens' => [
        'Start: [relative:-1 day#Y-m-d], End: [relative:+1 week#Y-m-d]',
        'Start: ' . date('Y-m-d', strtotime('-1 day', $timestamp)) . ', End: ' . date('Y-m-d', strtotime('+1 week', $timestamp)),
      ],
      'with custom now' => [
        '[relative:+1 day]',
        (string) strtotime('+1 day', 1715011200),
        1715011200,
      ],
      'lookalike token beside a real token is left alone' => [
        '[relative:-1 day] and [tar:-1 day]',
        strtotime('-1 day', $timestamp) . ' and [tar:-1 day]',
      ],
      'misspelled token is left alone' => [
        '[relative:-1 day] and [realtive:-1 day]',
        strtotime('-1 day', $timestamp) . ' and [realtive:-1 day]',
      ],
      'offset resolving to the epoch' => [
        '[relative:@0]',
        '0',
      ],
    ];
  }

  public function testInvalidRelativeDateTypeThrowsException(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The relative date offset cannot be evaluated: "invalid date".');
    $this->testObject::dateRelativeProcessValue('[relative:invalid date]');
  }

  public function testInvalidRelativeDateFormatThrowsException(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The relative date format produced an empty value: " ".');
    $this->testObject::dateRelativeProcessValue('[relative:-1 day# ]');
  }

  /**
   * Tests that a skipped scenario passes a token through untouched.
   */
  public function testSkippedScenarioLeavesTokensUntouched(): void {
    $this->testObject->dateBeforeScenario($this->createBeforeScenarioScope(['behat-steps-skip:DateTrait']));

    $this->assertSame('[relative:-1 day#Y-m-d]', $this->testObject->dateRelativeTransformValue('[relative:-1 day#Y-m-d]'));

    $table = new TableNode([['created'], ['[relative:-1 day#Y-m-d]']]);
    $this->assertSame([['created'], ['[relative:-1 day#Y-m-d]']], $this->testObject->dateRelativeTransformTable($table)->getRows());
  }

  /**
   * Tests that an unskipped scenario resolves tokens.
   */
  public function testUnskippedScenarioResolvesTokens(): void {
    $this->testObject->dateBeforeScenario($this->createBeforeScenarioScope());

    $expected = date('Y-m-d', (int) strtotime('-1 day', DateTraitTestImplementation::CLOCK));

    $this->assertSame($expected, $this->testObject->dateRelativeTransformValue('[relative:-1 day#Y-m-d]'));

    $table = new TableNode([['created'], ['[relative:-1 day#Y-m-d]']]);
    $this->assertSame([['created'], [$expected]], $this->testObject->dateRelativeTransformTable($table)->getRows());
  }

}

/**
 * Test implementation of DateTrait.
 */
class DateTraitTestImplementation extends RawContext {

  use DateTrait;

  /**
   * The clock this implementation pins: May 5, 2024 12:00:00 UTC.
   */
  public const CLOCK = 1714924800;

  /**
   * Returns fixed timestamp for testing.
   */
  protected static function dateNow(): int {
    return self::CLOCK;
  }

}
