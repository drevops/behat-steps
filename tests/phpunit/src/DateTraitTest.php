<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\DateTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for DateTrait.
 */
#[CoversClass(DateTrait::class)]
class DateTraitTest extends UnitTestCase {

  /**
   * A test implementation of DateTrait.
   *
   * @var \DrevOps\BehatSteps\Tests\DateTraitTestImplementation
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
    // Use a fixed timestamp for tests: May 5, 2024 12:00:00 UTC.
    $timestamp = 1714924800;

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
    ];
  }

  public function testInvalidRelativeDateTypeThrowsException(): void {
    $this->expectException(\RuntimeException::class);
    $this->testObject::dateRelativeProcessValue('[relative:invalid date]');
  }

  public function testInvalidRelativeDateFormatThrowsException(): void {
    $this->expectException(\RuntimeException::class);
    $this->testObject::dateRelativeProcessValue('[relative:-1 day# ]');
  }

}

/**
 * Test implementation of DateTrait.
 */
class DateTraitTestImplementation {

  use DateTrait;

  /**
   * Returns fixed timestamp for testing.
   */
  protected static function dateNow(): int {
    return 1714924800;
  }

}
